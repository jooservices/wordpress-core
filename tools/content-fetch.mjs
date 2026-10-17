#!/usr/bin/env node
import { readFileSync, mkdirSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { fromRoot, ROOT } from './lib/paths.mjs';
import { createTransport } from './content-fetch/transport.mjs';
import {
  auditSnapshot,
  createSnapshot,
  fetchCatalog,
  licenseCoverage,
  manifestHash,
  readSnapshot,
  replaySnapshot,
  stableStringify,
  writeSnapshot,
} from './content-fetch/pipeline.mjs';

const scriptPath = fileURLToPath(import.meta.url);
const workspaceRoot = resolve(ROOT, '../../../../');
const artifactRoot = join(workspaceRoot, 'artifacts/jooservices-wp-themes/content-fetch');
const catalogPath = fromRoot('tools/content-fetch/sources.json');

/** CLI for explicit content refresh, frozen snapshot audit, coverage, and deterministic replay. */
/** @typedef {{stdout: {write: (text: string) => unknown}, stderr: {write: (text: string) => unknown}}} CliIo */
/** @param {string[]} [args] @param {CliIo} [io] */
export async function runCli(args = process.argv.slice(2), io = process) {
  const [command, ...rest] = args;
  const options = parseOptions(rest);
  const snapshotPath = resolve(options.snapshot ?? join(artifactRoot, 'content-snapshot.json.gz'));
  if (command === 'refresh') {
    const catalog = JSON.parse(readFileSync(catalogPath, 'utf8'));
    const sourceIds = options.source
      ? options.source
          .split(',')
          .map((sourceId) => sourceId.trim())
          .filter(Boolean)
      : [];
    const selected = sourceIds.length
      ? catalog.sources.filter((source) => sourceIds.includes(source.id))
      : catalog.sources;
    const unknownSources = sourceIds.filter((sourceId) => !catalog.sources.some((source) => source.id === sourceId));
    if (unknownSources.length) throw new Error(`Unknown source id(s): ${unknownSources.join(', ')}`);
    const maxItems = options['max-items'] ? Number(options['max-items']) : null;
    if (maxItems !== null && (!Number.isInteger(maxItems) || maxItems < 1 || maxItems > 500))
      throw new Error('--max-items must be an integer from 1 to 500');
    const sources = selected.map((source) => ({
      ...source,
      ...(maxItems ? { max_items: Math.min(source.max_items, maxItems) } : {}),
    }));
    const cacheDir = join(artifactRoot, 'content-cache');
    const transport = createTransport({ cacheDir, userAgent: catalog.user_agent });
    const { records, failures } = await fetchCatalog({ ...catalog, sources }, transport, {
      refresh: options.cached !== 'true',
    });
    if (records.length === 0) throw new Error(`No items were fetched. Source failures: ${JSON.stringify(failures)}`);
    const snapshot = createSnapshot(records, manifestHash(catalogPath), failures);
    mkdirSync(dirname(snapshotPath), { recursive: true });
    const evidence = writeSnapshot(snapshotPath, snapshot);
    io.stdout.write(
      `Fetched ${snapshot.item_count} items from ${sources.filter((source) => source.enabled).length} configured sources.\n`
    );
    io.stdout.write(`Snapshot: ${snapshotPath}\nSHA-256: ${evidence.sha256}\n`);
    io.stdout.write(`License counts: ${JSON.stringify(licenseCoverage(snapshot.items))}\n`);
    if (failures.length) io.stdout.write(`Source warnings: ${JSON.stringify(failures)}\n`);
    return 0;
  }

  if (command === 'audit') {
    const tier = String(options.tier ?? 'A').toUpperCase();
    const snapshot = readSnapshot(snapshotPath);
    const errors = auditSnapshot(snapshot, tier);
    if (errors.length) {
      io.stderr.write(`${errors.join('\n')}\n`);
      return 1;
    }
    io.stdout.write(`Tier ${tier} audit passed for ${snapshot.item_count} snapshot items.\n`);
    return 0;
  }

  if (command === 'coverage') {
    const snapshot = readSnapshot(snapshotPath);
    const output = stableStringify(snapshot.coverage);
    if (options.out) {
      const outPath = resolve(options.out);
      mkdirSync(dirname(outPath), { recursive: true });
      await import('node:fs/promises').then(({ writeFile }) => writeFile(outPath, output));
      io.stdout.write(`Coverage report: ${outPath}\n`);
    } else io.stdout.write(output);
    return 0;
  }

  if (command === 'replay') {
    const tier = String(options.tier ?? 'A').toUpperCase();
    const snapshot = readSnapshot(snapshotPath);
    const records = replaySnapshot(snapshot, tier);
    const outPath = resolve(options.out ?? join(artifactRoot, `replay-tier-${tier}.json`));
    mkdirSync(dirname(outPath), { recursive: true });
    const { writeFile } = await import('node:fs/promises');
    await writeFile(outPath, stableStringify({ format_version: 1, tier, items: records }));
    io.stdout.write(
      `Replayed ${records.length} items from frozen snapshot.\nOutput: ${outPath}\nSHA-256: ${manifestHash(outPath)}\n`
    );
    return 0;
  }

  io.stderr.write(
    'Usage: node tools/content-fetch.mjs <refresh|audit|coverage|replay> [--snapshot FILE] [--tier A|B] [--source ID] [--max-items N] [--cached true] [--out FILE]\n'
  );
  return 2;
}

function parseOptions(args) {
  const options = {};
  for (let index = 0; index < args.length; index += 1) {
    const token = args[index];
    if (!token.startsWith('--')) throw new Error(`Unexpected argument: ${token}`);
    const key = token.slice(2);
    const value = args[index + 1];
    if (!value || value.startsWith('--')) throw new Error(`Option --${key} needs a value`);
    options[key] = value;
    index += 1;
  }
  return options;
}

if (process.argv[1] && resolve(process.argv[1]) === scriptPath) {
  runCli()
    .then((code) => {
      process.exitCode = code;
    })
    .catch((error) => {
      process.stderr.write(`${error.message}\n`);
      process.exitCode = 1;
    });
}
