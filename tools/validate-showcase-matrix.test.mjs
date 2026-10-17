// @vitest-environment node
import { execFileSync, spawnSync } from 'node:child_process';
import { mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
  readSpecificationIds,
  run,
  validateDocumentationMatrix,
  validateGeneratedInventory,
  validateMatrix,
} from './showcase-matrix-validator.mjs';

const root = process.cwd();
const matrix = JSON.parse(readFileSync(resolve(root, 'tests/showcase/matrix.json'), 'utf8'));
const checklist = readFileSync(resolve(root, matrix.specificationSource), 'utf8');
const specIds = readSpecificationIds(checklist);
const documentation = readFileSync(resolve(root, 'docs/spec/showcase-matrix.md'), 'utf8');
const validator = resolve(root, 'tools/validate-showcase-matrix.mjs');
const temporaryDirectory = resolve(root, `.tmp/showcase-matrix-test-${process.pid}`);

afterEach(() => {
  rmSync(temporaryDirectory, { recursive: true, force: true });
});

function copyMatrix() {
  return structuredClone(matrix);
}

function validInventory(source = matrix) {
  return {
    schemaVersion: 1,
    entries: source.rows.map((row) => ({
      key: row.contentKey,
      status: 'present',
      url: row.expectedUrl,
      httpStatus: row.expectedStatus,
    })),
  };
}

function executeRun(args) {
  const previousExitCode = process.exitCode;
  const stdout = vi.spyOn(process.stdout, 'write').mockImplementation(() => true);
  const stderr = vi.spyOn(process.stderr, 'write').mockImplementation(() => true);
  process.exitCode = 0;
  try {
    run(args);
    return {
      stdout: stdout.mock.calls.map(([value]) => String(value)).join(''),
      stderr: stderr.mock.calls.map(([value]) => String(value)).join(''),
      exitCode: process.exitCode,
    };
  } finally {
    stdout.mockRestore();
    stderr.mockRestore();
    process.exitCode = previousExitCode;
  }
}

describe('showcase matrix static contract', () => {
  it('rejects a non-object matrix and an empty row set', () => {
    expect(validateMatrix(null)).toEqual(['matrix: expected a JSON object']);
    expect(validateMatrix({ ...matrix, rows: [] })).toContain('rows: must be a non-empty array');
  });

  it('parses all checklist requirement ids, including numeric 404 ids', () => {
    expect(readSpecificationIds(checklist)).toContain('404-08');
    expect(specIds).toHaveLength(68);
    expect(new Set(specIds).size).toBe(specIds.length);
  });

  it('validates the complete matrix and its current source checklist', () => {
    expect(validateMatrix(matrix, { expectedSpecIds: specIds, rootDir: root })).toEqual([]);
    expect(matrix.rows).toHaveLength(146);
  });

  it('keeps the human-readable row ids, content, routes and references synchronized', () => {
    expect(validateDocumentationMatrix(matrix, documentation)).toEqual([]);
    expect(validateDocumentationMatrix(matrix, documentation.replace('`HOME-SHELL`', '`UNKNOWN-ROW`'))).toContain(
      'documentation: missing row HOME-SHELL'
    );
    expect(
      validateDocumentationMatrix(
        matrix,
        documentation.replace('Published post under 300 words with one paragraph and one heading', 'stale content')
      )
    ).toContain('documentation.POST-SHORT: column 3 differs from matrix.json');
  });

  it('rejects duplicate row ids and content keys', () => {
    const invalid = copyMatrix();
    invalid.rows[1].id = invalid.rows[0].id;
    invalid.rows[1].contentKey = invalid.rows[0].contentKey;
    const errors = validateMatrix(invalid, { expectedSpecIds: specIds });
    expect(errors.some((error) => error.includes('.id: is duplicated'))).toBe(true);
    expect(errors.some((error) => error.includes('.contentKey: is duplicated'))).toBe(true);
  });

  it('rejects rows with no content, route, test reference or manual owner', () => {
    const invalid = copyMatrix();
    invalid.rows[0].contentRequired = [];
    invalid.rows[0].expectedUrl = '';
    invalid.rows[0].automatedTest.id = '';
    invalid.rows[0].manualCheckOwner = '';
    invalid.rows[0].checkedBy = '';
    const errors = validateMatrix(invalid, { expectedSpecIds: specIds });
    expect(errors).toEqual(
      expect.arrayContaining([
        expect.stringContaining('.contentRequired: must contain'),
        expect.stringContaining('.expectedUrl: must be'),
        expect.stringContaining('.automatedTest: must name'),
        expect.stringContaining('.manualCheckOwner: must be'),
        expect.stringContaining('.checkedBy: must be'),
      ])
    );
  });

  it('requires every current checklist id to map to a row and rejects unknown ids', () => {
    const invalid = copyMatrix();
    invalid.rows.forEach((row) => {
      row.specIds = row.specIds.filter((id) => id !== 'HOME-01');
    });
    invalid.rows[0].specIds = ['UNKNOWN-99'];
    const errors = validateMatrix(invalid, { expectedSpecIds: specIds });
    expect(errors).toContain('rows[0] (HOME-SHELL).specIds: unknown specification id UNKNOWN-99');
    expect(errors).toContain('specification coverage: no matrix row maps HOME-01');
    expect(errors.some((error) => error.includes('missing source id'))).toBe(false);
  });

  it('detects specification ids added to the source but absent from the matrix index', () => {
    const errors = validateMatrix(matrix, { expectedSpecIds: [...specIds, 'FUTURE-01'] });
    expect(errors).toContain('specificationIds: missing source id FUTURE-01');
  });

  it('rejects missing browser dimensions, schemes, locale profiles, and profile references', () => {
    const invalid = copyMatrix();
    invalid.verificationProfiles.ltr.browsers = ['Chromium'];
    invalid.verificationProfiles.rtl.schemes = ['dark'];
    invalid.verificationProfiles.rtl.overflowSweepWidths = [390];
    invalid.rows[0].verificationProfiles = ['unknown'];
    const errors = validateMatrix(invalid, { expectedSpecIds: specIds });
    expect(errors.some((error) => error.includes('verificationProfiles.ltr.browsers: missing WebKit'))).toBe(true);
    expect(errors.some((error) => error.includes('verificationProfiles.rtl.schemes: missing paper'))).toBe(true);
    expect(errors.some((error) => error.includes('overflowSweepWidths'))).toBe(true);
    expect(errors).toContain('rows[0] (HOME-SHELL).verificationProfiles: must include ltr');
    expect(errors).toContain('rows[0] (HOME-SHELL).verificationProfiles: must include rtl');
    expect(errors).toContain('rows[0] (HOME-SHELL).verificationProfiles: unknown profile unknown');
  });

  it('requires the Chinese locale profile for the Chinese content row', () => {
    const invalid = copyMatrix();
    const index = invalid.rows.findIndex((row) => row.id === 'POST-CHINESE');
    invalid.rows[index].verificationProfiles = ['ltr', 'rtl'];
    expect(validateMatrix(invalid, { expectedSpecIds: specIds })).toContain(
      `rows[${index}] (POST-CHINESE).verificationProfiles: Chinese sample must include the zh_CN profile`
    );
  });

  it('requires planned tests to name a delivery ticket and implemented test files to exist', () => {
    const planned = copyMatrix();
    planned.rows[0].automatedTest.trackingTicket = '';
    expect(validateMatrix(planned, { expectedSpecIds: specIds })).toContain(
      'rows[0] (HOME-SHELL).automatedTest.trackingTicket: planned tests must name their delivery ticket'
    );
    const implemented = copyMatrix();
    implemented.rows[0].automatedTest.status = 'implemented';
    implemented.rows[0].automatedTest.file = 'tests/missing.spec.ts';
    expect(validateMatrix(implemented, { expectedSpecIds: specIds, rootDir: root })).toContain(
      'rows[0] (HOME-SHELL).automatedTest.file: implemented test file does not exist inside the repository'
    );
  });
});

describe('generated showcase inventory', () => {
  it('accepts a generated inventory with every expected key, URL and response status', () => {
    expect(validateGeneratedInventory(matrix, validInventory())).toEqual([]);
  });

  it('reports missing content, unpopulated entries, URL drift, and HTTP status drift', () => {
    const inventory = validInventory();
    inventory.entries = inventory.entries.slice(1);
    inventory.entries[0].status = 'missing';
    inventory.entries[0].url = '/wrong/';
    inventory.entries[0].httpStatus = 500;
    const errors = validateGeneratedInventory(matrix, inventory);
    expect(errors.some((error) => error.includes('missing generated content key'))).toBe(true);
    expect(errors.some((error) => error.includes('.status: expected present'))).toBe(true);
    expect(errors.some((error) => error.includes('.url: expected'))).toBe(true);
    expect(errors.some((error) => error.includes('.httpStatus: expected'))).toBe(true);
  });

  it('rejects malformed inventory and duplicate content keys', () => {
    expect(validateGeneratedInventory(matrix, { schemaVersion: 2, entries: [] })).toContain(
      'inventory: expected schemaVersion 1 and an entries array'
    );
    const inventory = validInventory();
    inventory.entries[1].key = inventory.entries[0].key;
    expect(validateGeneratedInventory(matrix, inventory).some((error) => error.includes('duplicate key'))).toBe(true);
  });

  it('reports malformed entries inside an otherwise valid inventory', () => {
    const inventory = validInventory();
    inventory.entries[0] = null;
    inventory.entries[1].key = '';
    const errors = validateGeneratedInventory(matrix, inventory);
    expect(errors).toContain('inventory.entries[0].key: must be a non-empty string');
    expect(errors).toContain('inventory.entries[1].key: must be a non-empty string');
  });

  it('runs from the command line and can check the JOOS-93 inventory contract', () => {
    const inventoryDirectory = resolve(root, `.tmp/showcase-matrix-test-${process.pid}`);
    mkdirSync(inventoryDirectory, { recursive: true });
    const inventoryFile = resolve(inventoryDirectory, 'inventory.json');
    writeFileSync(inventoryFile, JSON.stringify(validInventory()));
    const staticOutput = execFileSync(process.execPath, [validator], { cwd: root, encoding: 'utf8' });
    expect(staticOutput).toContain('Generated-site content was not checked');
    const output = execFileSync(process.execPath, [validator, '--inventory', inventoryFile], {
      cwd: root,
      encoding: 'utf8',
    });
    expect(output).toContain('146 rows, 68 specification ids');
    expect(output).toContain('Generated-site inventory covers every matrix content key');
    const invalid = spawnSync(process.execPath, [validator, '--unknown'], { cwd: root, encoding: 'utf8' });
    expect(invalid.status).toBe(1);
    expect(invalid.stderr).toContain('unknown argument');
    const missingValue = spawnSync(process.execPath, [validator, '--inventory'], { cwd: root, encoding: 'utf8' });
    expect(missingValue.status).toBe(1);
    expect(missingValue.stderr).toContain('--inventory needs a file path');
    writeFileSync(inventoryFile, JSON.stringify({ schemaVersion: 1, entries: [] }));
    const incompleteInventory = spawnSync(process.execPath, [validator, '--inventory', inventoryFile], {
      cwd: root,
      encoding: 'utf8',
    });
    expect(incompleteInventory.status).toBe(1);
    expect(incompleteInventory.stderr).toContain('missing generated content key');
  });

  it('runs both static and generated-inventory checks directly', () => {
    const inventoryDirectory = resolve(root, `.tmp/showcase-matrix-test-${process.pid}`);
    mkdirSync(inventoryDirectory, { recursive: true });
    const inventoryFile = resolve(inventoryDirectory, 'inventory.json');
    const staticRun = executeRun([]);
    expect(staticRun.exitCode).toBe(0);
    expect(staticRun.stdout).toContain('146 rows, 68 specification ids');
    expect(staticRun.stdout).toContain('Generated-site content was not checked');

    writeFileSync(inventoryFile, JSON.stringify(validInventory()));
    const completeRun = executeRun(['--inventory', inventoryFile]);
    expect(completeRun.exitCode).toBe(0);
    expect(completeRun.stdout).toContain('Generated-site inventory covers every matrix content key');

    writeFileSync(inventoryFile, JSON.stringify({ schemaVersion: 1, entries: [] }));
    const incompleteRun = executeRun(['--inventory', inventoryFile]);
    expect(incompleteRun.exitCode).toBe(1);
    expect(incompleteRun.stderr).toContain('missing generated content key');
    expect(() => run(['--unknown'])).toThrow('unknown argument: --unknown');
    expect(() => run(['--inventory'])).toThrow('--inventory needs a file path');

    writeFileSync(inventoryFile, '{ invalid JSON');
    expect(() => run(['--inventory', inventoryFile])).toThrow(SyntaxError);
  });
});
