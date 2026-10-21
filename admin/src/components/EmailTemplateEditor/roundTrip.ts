/**
 * Representability check of the visual e-mail editor (plan 4.6, ADR 0107).
 *
 * The visual editor reads MJML with the HTML parser of the browser, which drops or moves markup it
 * does not know how to place (self-closing tags, table rows inside `mj-table`, ...). Rule: never
 * silently lose markup. Before a template is opened in Visual mode it is loaded into the editor,
 * exported again and both versions are compared as trees. Only an equal result opens Visual mode.
 *
 * This file has no dependency on GrapesJS: the editor is passed in as `exporter`. The comparison
 * does not use the browser parser either (it is the lossy part), it has its own small tokenizer.
 */

type ElementNode = {
  type: 'tag';
  name: string;
  attrs: [string, string][];
  children: TreeNode[];
};
type TreeNode = ElementNode | { type: 'text'; value: string } | { type: 'comment'; value: string };

const VOID_TAGS = new Set([
  'area',
  'base',
  'br',
  'col',
  'embed',
  'hr',
  'img',
  'input',
  'link',
  'meta',
  'source',
  'track',
  'wbr',
]);
/** Elements whose content is not markup. */
const RAW_TEXT_TAGS = new Set(['style', 'script', 'mj-style']);

const collapse = (text: string) => text.replace(/\s+/g, ' ').trim();

const decodeEntities = (text: string): string => {
  if (!text.includes('&')) return text;
  if (typeof document === 'undefined') return text;
  const area = document.createElement('textarea');
  area.innerHTML = text;
  return area.value;
};

const normaliseStyle = (value: string) =>
  value
    .split(';')
    .map((part) => part.replace(/\s*:\s*/, ':').trim())
    .filter(Boolean)
    .join(';');

const normaliseAttr = (name: string, raw: string): string => {
  const value = collapse(decodeEntities(raw));
  return name === 'style' ? normaliseStyle(value) : value;
};

const ATTRIBUTE = /([^\s"'<>/=]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'=<>`]+)))?/g;

const parseAttributes = (source: string): [string, string][] => {
  const attrs: [string, string][] = [];
  ATTRIBUTE.lastIndex = 0;
  let match = ATTRIBUTE.exec(source);
  while (match) {
    const name = match[1].toLowerCase();
    attrs.push([name, normaliseAttr(name, match[2] ?? match[3] ?? match[4] ?? '')]);
    match = ATTRIBUTE.exec(source);
  }
  return attrs.sort(([a], [b]) => (a < b ? -1 : a > b ? 1 : 0));
};

const TAG_OPEN = /^<([A-Za-z][^\s/>]*)((?:"[^"]*"|'[^']*'|[^'">])*?)(\/?)>/;
const TAG_CLOSE = /^<\/([A-Za-z][^\s/>]*)\s*>/;

/** Tolerant parser: unknown input never throws, unclosed tags are closed at the end. */
export const parseMarkup = (source: string): TreeNode[] => {
  const root: ElementNode = { type: 'tag', name: '#root', attrs: [], children: [] };
  const stack: ElementNode[] = [root];
  const top = () => stack[stack.length - 1];
  const addText = (raw: string) => {
    const value = collapse(decodeEntities(raw));
    if (value) top().children.push({ type: 'text', value });
  };

  let pos = 0;
  while (pos < source.length) {
    const lt = source.indexOf('<', pos);
    if (lt === -1) {
      addText(source.slice(pos));
      break;
    }
    if (lt > pos) addText(source.slice(pos, lt));
    const rest = source.slice(lt);

    if (rest.startsWith('<!--')) {
      const end = rest.indexOf('-->');
      const stop = end === -1 ? rest.length : end + 3;
      top().children.push({
        type: 'comment',
        value: collapse(rest.slice(4, end === -1 ? undefined : end)),
      });
      pos = lt + stop;
    } else if (rest.startsWith('<![CDATA[')) {
      const end = rest.indexOf(']]>');
      const stop = end === -1 ? rest.length : end + 3;
      addText(rest.slice(9, end === -1 ? undefined : end));
      pos = lt + stop;
    } else if (rest.startsWith('<?') || rest.startsWith('<!')) {
      const end = rest.indexOf('>');
      pos = lt + (end === -1 ? rest.length : end + 1);
    } else {
      const close = TAG_CLOSE.exec(rest);
      const open = close ? null : TAG_OPEN.exec(rest);
      if (close) {
        const name = close[1].toLowerCase();
        const index = stack.map((n) => n.name).lastIndexOf(name);
        if (index > 0) stack.length = index;
        pos = lt + close[0].length;
      } else if (open) {
        const name = open[1].toLowerCase();
        const node: ElementNode = {
          type: 'tag',
          name,
          attrs: parseAttributes(open[2]),
          children: [],
        };
        top().children.push(node);
        pos = lt + open[0].length;
        if (RAW_TEXT_TAGS.has(name) && !open[3]) {
          const closing = new RegExp(`</${name}\\s*>`, 'i').exec(source.slice(pos));
          const end = closing ? pos + closing.index : source.length;
          const value = collapse(source.slice(pos, end));
          if (value) node.children.push({ type: 'text', value });
          pos = closing ? end + closing[0].length : end;
        } else if (!open[3] && !VOID_TAGS.has(name)) {
          stack.push(node);
        }
      } else {
        // A stray "<" is text.
        addText('<');
        pos = lt + 1;
      }
    }
  }
  return root.children;
};

/**
 * One line per node, prefixed by the path of its ancestors. The `<tbody>` that the browser inserts
 * into tables is transparent: its children count as children of the table.
 */
export const treeLines = (nodes: TreeNode[], path = '', out: string[] = []): string[] => {
  const prefix = path ? `${path} > ` : '';
  nodes.forEach((node) => {
    if (node.type === 'text') {
      out.push(`${prefix}#text ${node.value}`);
    } else if (node.type === 'comment') {
      out.push(`${prefix}#comment ${node.value}`);
    } else if (node.name === 'tbody') {
      treeLines(node.children, path, out);
    } else {
      const attrs = node.attrs.map(([k, v]) => `${k}="${v}"`).join(' ');
      out.push(`${prefix}<${node.name}${attrs ? ` ${attrs}` : ''}>`);
      treeLines(node.children, `${prefix}${node.name}`, out);
    }
  });
  return out;
};

/** Indented, attribute-sorted rendering, used to show a readable diff of two MJML texts. */
export const prettyLines = (nodes: TreeNode[], depth = 0, out: string[] = []): string[] => {
  const pad = '  '.repeat(depth);
  nodes.forEach((node) => {
    if (node.type === 'text') {
      out.push(`${pad}${node.value}`);
    } else if (node.type === 'comment') {
      out.push(`${pad}<!-- ${node.value} -->`);
    } else if (node.name === 'tbody') {
      prettyLines(node.children, depth, out);
    } else {
      const attrs = node.attrs.map(([k, v]) => ` ${k}="${v}"`).join('');
      if (node.children.length === 0) {
        out.push(`${pad}<${node.name}${attrs}></${node.name}>`);
      } else {
        out.push(`${pad}<${node.name}${attrs}>`);
        prettyLines(node.children, depth + 1, out);
        out.push(`${pad}</${node.name}>`);
      }
    }
  });
  return out;
};

/**
 * Rewrites self-closing MJML tags (`<mj-font ... />`) as `<mj-font ...></mj-font>`. The browser
 * parser ignores the slash on custom elements, so the siblings that follow would become children.
 * Comments are skipped, quoted attribute values may contain `>`.
 */
export const normalizeSelfClosing = (mjml: string): string =>
  mjml.replace(
    /<!--[\s\S]*?-->|<(mj-[\w-]+)((?:"[^"]*"|'[^']*'|[^'">])*?)\s*\/>/gi,
    (whole, name?: string, attrs?: string) => (name ? `<${name}${attrs ?? ''}></${name}>` : whole),
  );

/** True for a document whose root element is `<mjml>`; other content is plain HTML. */
export const isMjmlDocument = (content: string): boolean =>
  /^\s*(<\?xml[^>]*>\s*)?(<!--[\s\S]*?-->\s*)*<mjml[\s>]/i.test(content);

export type DiffLine = { type: 'same' | 'del' | 'add'; text: string };

const MAX_CELLS = 4_000_000;

/** Line diff by longest common subsequence; huge inputs fall back to "everything differs". */
export const lineDiff = (before: string[], after: string[]): DiffLine[] => {
  const n = before.length;
  const m = after.length;
  if (n * m > MAX_CELLS) {
    return [
      ...before.map((text) => ({ type: 'del' as const, text })),
      ...after.map((text) => ({ type: 'add' as const, text })),
    ];
  }
  const width = m + 1;
  const table = new Uint32Array((n + 1) * width);
  for (let i = n - 1; i >= 0; i -= 1) {
    for (let j = m - 1; j >= 0; j -= 1) {
      table[i * width + j] =
        before[i] === after[j]
          ? table[(i + 1) * width + j + 1] + 1
          : Math.max(table[(i + 1) * width + j], table[i * width + j + 1]);
    }
  }
  const out: DiffLine[] = [];
  let i = 0;
  let j = 0;
  while (i < n && j < m) {
    if (before[i] === after[j]) {
      out.push({ type: 'same', text: before[i] });
      i += 1;
      j += 1;
    } else if (table[(i + 1) * width + j] >= table[i * width + j + 1]) {
      out.push({ type: 'del', text: before[i] });
      i += 1;
    } else {
      out.push({ type: 'add', text: after[j] });
      j += 1;
    }
  }
  while (i < n) out.push({ type: 'del', text: before[i++] });
  while (j < m) out.push({ type: 'add', text: after[j++] });
  return out;
};

export type Analysis =
  /** The editor keeps every node. `source` is what has to be loaded into it. */
  | { kind: 'representable'; source: string; exported: string }
  /** Not an MJML document (plain HTML templates). */
  | { kind: 'html' }
  /** The editor would drop or change markup. Nothing was changed. */
  | {
      kind: 'lossy';
      exported: string;
      lost: string[];
      added: string[];
      /** Readable rendering of both sides for the confirmation diff. */
      diff: DiffLine[];
    };

/** Loads `source` into the editor and returns the MJML the editor exports. */
export type MjmlExporter = (source: string) => Promise<string>;

export const analyseMjml = async (content: string, exporter: MjmlExporter): Promise<Analysis> => {
  if (!isMjmlDocument(content)) return { kind: 'html' };

  const source = normalizeSelfClosing(content);
  const exported = await exporter(source);
  const before = parseMarkup(source);
  const after = parseMarkup(exported);
  const a = treeLines(before);
  const b = treeLines(after);
  const same = a.length === b.length && a.every((line, i) => line === b[i]);
  if (same) return { kind: 'representable', source, exported };

  const changes = lineDiff(a, b);
  return {
    kind: 'lossy',
    exported,
    lost: changes.filter((c) => c.type === 'del').map((c) => c.text),
    added: changes.filter((c) => c.type === 'add').map((c) => c.text),
    diff: lineDiff(prettyLines(before), prettyLines(after)),
  };
};
