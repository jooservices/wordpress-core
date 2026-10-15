// Read-only virtual file system built from the JSON of GET /joo/v1/terminal/fs. Pure: no DOM, no network.
// Node: { t: 'd'|'f'|'l', n: name, parent, k: children (dirs), meta, post, text, target (links) }.

const MAX_LINK_DEPTH = 8;

/**
 * Builds the tree.
 * @param {{nodes: object[], site?: object}} data FS JSON.
 * @returns {{root: object, site: object, count: {posts: number, dirs: number}}} The tree.
 */
export function buildFs(data) {
  const root = { t: 'd', n: '', k: {}, meta: { home: true }, parent: null };
  root.parent = root;
  const nodes = Array.isArray(data?.nodes) ? data.nodes : [];
  const ensureDir = (path, meta) => {
    let dir = root;
    for (const segment of path.split('/').filter(Boolean)) {
      if (!dir.k[segment]) dir.k[segment] = { t: 'd', n: segment, k: {}, meta: {}, parent: dir };
      dir = dir.k[segment];
      if (dir.t !== 'd') return null;
    }
    if (meta) Object.assign(dir.meta, meta);
    return dir;
  };
  let posts = 0;
  const ordered = nodes
    .filter((n) => n && typeof n.p === 'string')
    .sort((a, b) => String(a.p).length - String(b.p).length);
  for (const node of ordered) {
    if (typeof node?.p !== 'string' || !node.p.startsWith('/')) continue;
    if (node.t === 'd') {
      ensureDir(node.p, { cat: node.cat, tag: node.tag, url: node.url });
      continue;
    }
    const slash = node.p.lastIndexOf('/');
    const parent = ensureDir(node.p.slice(0, slash) || '/');
    const name = node.p.slice(slash + 1);
    if (!parent || !name) continue;
    if (node.t === 'l') {
      parent.k[name] = { t: 'l', n: name, target: String(node.to ?? ''), parent };
    } else if (node.t === 'f') {
      const file = { t: 'f', n: name, parent, hidden: Boolean(node.hidden), page: node.page, url: node.url };
      if (node.id !== undefined) {
        file.post = {
          id: node.id,
          title: String(node.title ?? name),
          ex: String(node.ex ?? ''),
          d: String(node.d ?? ''),
          u: String(node.u ?? ''),
          m: Number(node.m) || 1,
          tg: Array.isArray(node.tg) ? node.tg.map(String) : [],
          url: node.url,
          c: node.c,
        };
        posts += 1;
      } else {
        file.text = String(node.text ?? node.ex ?? '');
        file.title = node.title;
      }
      parent.k[name] = file;
    }
  }
  let dirs = 0;
  walk(root, (n) => n.t === 'd' && (dirs += 1));
  return {
    root,
    site: { name: 'site', home: '', user: 'visitor', host: 'localhost', ...(data?.site ?? {}) },
    count: { posts, dirs },
  };
}

/** Absolute path of a node. */
export function pathOf(node) {
  const parts = [];
  for (let n = node; n && n.parent && n.parent !== n; n = n.parent) parts.unshift(n.n);
  return '/' + parts.join('/');
}

/** Path shown in the prompt ("~" or "~/ai"). */
export const display = (node) => (pathOf(node) === '/' ? '~' : '~' + pathOf(node));

/**
 * Resolves a path (`.`, `..`, `~`, absolute and relative) from a directory. Links are followed with a loop guard.
 * @returns {object|null} The node or null.
 */
export function resolve(root, path, cwd, depth = 0) {
  if (depth > MAX_LINK_DEPTH) return null;
  let node = cwd;
  if (path === undefined || path === null || path === '' || path === '~') return root;
  let rest = String(path);
  if (rest.startsWith('/') || rest.startsWith('~')) {
    node = root;
    rest = rest.replace(/^~?\/?/, '');
  }
  for (const segment of rest.split('/').filter(Boolean)) {
    if (segment === '.') continue;
    if (segment === '..') {
      node = node.parent ?? root;
      continue;
    }
    if (node.t !== 'd') return null;
    let next = node.k[segment];
    if (!next) return null;
    if (next.t === 'l') next = resolve(root, next.target, root, depth + 1);
    if (!next) return null;
    node = next;
  }
  return node;
}

/** The node a link points to (or the node itself). */
export const target = (root, node) => (node.t === 'l' ? resolve(root, node.target, root, 1) : node);

/** Children sorted case-insensitively; dotfiles only with `all`. */
export function list(dir, all = false) {
  return Object.keys(dir.k)
    .filter((key) => all || key[0] !== '.')
    .sort((a, b) => a.toLowerCase().localeCompare(b.toLowerCase()))
    .map((key) => dir.k[key]);
}

/** Depth-first walk (links are visited but not followed). */
export function walk(node, fn, depth = 0) {
  fn(node, depth);
  if (node.t === 'd')
    for (const child of list(node)) child.t === 'l' ? fn(child, depth + 1) : walk(child, fn, depth + 1);
}

/** stat(): fake size from the reading time for posts. */
export function stat(node) {
  const f = node.post ?? null;
  const size = node.t === 'd' ? 4096 : f ? f.m * 1180 : (node.text ?? '').length || 120;
  return {
    type: node.t,
    size,
    mtime: f?.u || f?.d || '',
    mode: node.t === 'd' ? 'drwxr-xr-x' : node.t === 'l' ? 'lrwxrwxrwx' : '-r--r--r--',
  };
}
