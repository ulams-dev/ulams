import { FRAGMENT_RE, ID_RE } from "./ids.ts";
import type { Sink } from "./issues.ts";
import { BLOCK_KINDS, type BlockKind, type Pos } from "./types.ts";
import { parseYamlFile, type YamlFile } from "./yaml-io.ts";

/* The lesson file grammar (normative text: GRAMMAR.md next to this file).
 * A lesson is YAML front matter followed by block directives and loose Markdown. The parser is line based,
 * keeps source positions for every node, and stores directive content verbatim. */

export interface DirectiveNode {
  type: "directive";
  kind: BlockKind;
  id?: string;
  cite?: string[];
  origin?: "ai" | "author";
  objectives?: string[];
  markdown: string;
  /** 1-based line of the opening fence. */
  line: number;
  /** 1-based line of the first content line. */
  contentLine: number;
  /** Column of each attribute on the opening line. */
  columns: Record<string, number>;
  /** The directive already has a syntax problem; the checks that need a sound block skip it. */
  invalid: boolean;
}

export interface LooseNode {
  type: "loose";
  markdown: string;
  line: number;
}

export type BodyNode = DirectiveNode | LooseNode;

export interface LessonSource {
  file: string;
  frontMatter: YamlFile | null;
  nodes: BodyNode[];
  /** False when the file has no front matter at all. */
  hasFrontMatter: boolean;
}

const OPENER = /^(:{3,})([A-Za-z][A-Za-z0-9_-]*)(.*)$/;
const COLONS_ONLY = /^(:{3,})\s*$/;

/** Tracks Markdown code fences so `:::` and blank lines inside them are ordinary content. */
export class FenceTracker {
  private open: { ch: string; len: number } | null = null;

  /** Feeds one line; true when the line belongs to a fenced code block (including its fence lines). */
  feed(line: string): boolean {
    if (this.open) {
      const m = /^ {0,3}(`{3,}|~{3,})\s*$/.exec(line);
      if (m && (m[1] as string)[0] === this.open.ch && (m[1] as string).length >= this.open.len) this.open = null;
      return true;
    }
    const m = /^ {0,3}(`{3,}|~{3,})(.*)$/.exec(line);
    if (m && !((m[1] as string)[0] === "`" && (m[2] as string).includes("`"))) {
      this.open = { ch: (m[1] as string)[0] as string, len: (m[1] as string).length };
      return true;
    }
    return false;
  }

  get inside(): boolean {
    return this.open !== null;
  }
}

export function normalizeText(text: string): string {
  return text.replace(/^\uFEFF/, "").replace(/\r\n?/g, "\n");
}

/** Markdown stored in a block: LF line ends, no leading blank lines, no trailing whitespace at the end. */
export function normalizeMarkdown(text: string): string {
  return normalizeText(text).replace(/^(?:[ \t]*\n)+/, "").replace(/\s+$/, "");
}

function splitFrontMatter(lines: string[]): { yamlLines: string[] | null; bodyStart: number; closed: boolean; present: boolean } {
  if ((lines[0] ?? "").trimEnd() !== "---") return { yamlLines: null, bodyStart: 0, closed: false, present: false };
  for (let i = 1; i < lines.length; i++) {
    const t = (lines[i] as string).trimEnd();
    if (t === "---" || t === "...") return { yamlLines: lines.slice(1, i), bodyStart: i + 1, closed: true, present: true };
  }
  return { yamlLines: lines.slice(1), bodyStart: lines.length, closed: false, present: true };
}

/** Parses `{#id cite="a,b" origin=author}`; `text` starts at the opening brace. */
function parseAttrs(text: string, file: string, line: number, startColumn: number, sink: Sink, node: { id?: string; cite?: string[]; origin?: "ai" | "author"; objectives?: string[]; columns: Record<string, number> }): void {
  const at = (i: number): Pos => ({ line, column: startColumn + i });
  const inner = text.slice(1, -1);
  let i = 0;
  const seen = new Set<string>();
  while (i < inner.length) {
    if (/\s/.test(inner[i] as string)) {
      i++;
      continue;
    }
    const start = i;
    const column = startColumn + 1 + start;
    if (inner[i] === "#") {
      let j = i + 1;
      while (j < inner.length && !/\s/.test(inner[j] as string)) j++;
      const id = inner.slice(i + 1, j);
      if (seen.has("id")) sink.add("DIRECTIVE_ATTR_INVALID", { file, pos: at(1 + start) }, "The id is given twice.");
      seen.add("id");
      node.columns.id = column;
      if (ID_RE.test(id)) node.id = id;
      else sink.add("DIRECTIVE_ATTR_INVALID", { file, pos: at(1 + start) }, `"${id}" is not an element id (26 lowercase letters and digits).`);
      i = j;
      continue;
    }
    const m = /^([A-Za-z][A-Za-z0-9_-]*)=/.exec(inner.slice(i));
    if (!m) {
      sink.add("DIRECTIVE_SYNTAX", { file, pos: at(1 + start) }, `Cannot read the attribute "${inner.slice(i).split(/\s/)[0]}"; write name=value or #id.`);
      return;
    }
    const name = m[1] as string;
    i += (m[0] as string).length;
    let value: string;
    if (inner[i] === '"') {
      const end = inner.indexOf('"', i + 1);
      if (end < 0) {
        sink.add("DIRECTIVE_SYNTAX", { file, pos: at(1 + start) }, `The value of "${name}" is missing its closing quote.`);
        return;
      }
      value = inner.slice(i + 1, end);
      i = end + 1;
    } else {
      let j = i;
      while (j < inner.length && !/\s/.test(inner[j] as string)) j++;
      value = inner.slice(i, j);
      i = j;
    }
    const where = { file, pos: at(1 + start) };
    if (seen.has(name)) sink.add("DIRECTIVE_ATTR_INVALID", where, `"${name}" is given twice.`);
    seen.add(name);
    node.columns[name] = column;
    const list = value.split(",").map((s) => s.trim()).filter(Boolean);
    switch (name) {
      case "cite": {
        const bad = list.filter((c) => !FRAGMENT_RE.test(c));
        if (bad.length) sink.add("DIRECTIVE_ATTR_INVALID", where, `"${bad[0]}" is not a fragment id (frg_ and 12 characters a-z, 2-7).`);
        node.cite = list.filter((c) => FRAGMENT_RE.test(c));
        break;
      }
      case "objectives": {
        const bad = list.filter((c) => !ID_RE.test(c));
        if (bad.length) sink.add("DIRECTIVE_ATTR_INVALID", where, `"${bad[0]}" is not an objective id (26 lowercase letters and digits).`);
        node.objectives = list.filter((c) => ID_RE.test(c));
        break;
      }
      case "origin":
        if (value === "ai" || value === "author") node.origin = value;
        else sink.add("DIRECTIVE_ATTR_INVALID", where, `origin must be ai or author, not "${value}".`);
        break;
      default:
        sink.add("DIRECTIVE_ATTR_UNKNOWN", where, `Unknown attribute "${name}".`);
    }
  }
}

/** Splits loose Markdown into top-level chunks at blank lines; fenced code never splits. */
function looseChunks(lines: Array<{ line: number; text: string }>): LooseNode[] {
  const out: LooseNode[] = [];
  const fence = new FenceTracker();
  let current: Array<{ line: number; text: string }> = [];
  const flush = () => {
    if (current.length) out.push({ type: "loose", markdown: normalizeMarkdown(current.map((l) => l.text).join("\n")), line: (current[0] as { line: number }).line });
    current = [];
  };
  for (const l of lines) {
    const inFence = fence.feed(l.text);
    if (!inFence && l.text.trim() === "") flush();
    else current.push(l);
  }
  flush();
  return out.filter((n) => n.markdown !== "");
}

export function parseLessonFile(file: string, raw: string, sink: Sink): LessonSource {
  const lines = normalizeText(raw).split("\n");
  const fm = splitFrontMatter(lines);
  let frontMatter: YamlFile | null = null;
  if (!fm.present) {
    sink.add("FRONT_MATTER_MISSING", { file, pos: { line: 1, column: 1 } }, "The file does not start with a --- front matter block.");
  } else {
    if (!fm.closed) sink.add("FRONT_MATTER_UNCLOSED", { file, pos: { line: 1, column: 1 } }, "The front matter starting on line 1 is never closed.");
    frontMatter = parseYamlFile(file, `${(fm.yamlLines as string[]).join("\n")}\n`, sink, 1);
  }

  const nodes: BodyNode[] = [];
  let loose: Array<{ line: number; text: string }> = [];
  const fence = new FenceTracker();
  const flushLoose = () => {
    nodes.push(...looseChunks(loose));
    loose = [];
  };

  let i = fm.bodyStart;
  while (i < lines.length) {
    const text = lines[i] as string;
    const lineNo = i + 1;
    if (fence.feed(text)) {
      loose.push({ line: lineNo, text });
      i++;
      continue;
    }
    if (!text.startsWith(":::")) {
      loose.push({ line: lineNo, text });
      i++;
      continue;
    }
    if (COLONS_ONLY.test(text)) {
      sink.add("DIRECTIVE_STRAY_CLOSER", { file, pos: { line: lineNo, column: 1 } }, "A closing fence with no directive open.");
      i++;
      continue;
    }
    const m = OPENER.exec(text);
    const rest = m?.[3] ?? "";
    const attrText = rest.trimEnd();
    if (!m || (attrText !== "" && !(attrText.startsWith("{") && attrText.endsWith("}")))) {
      sink.add("DIRECTIVE_SYNTAX", { file, pos: { line: lineNo, column: 1 } }, `"${text.slice(0, 40)}" is not a directive line.`);
      i++;
      continue;
    }
    flushLoose();
    const colons = (m[1] as string).length;
    const kindName = m[2] as string;
    const node: DirectiveNode = { type: "directive", kind: "paragraph", markdown: "", line: lineNo, contentLine: lineNo + 1, columns: {}, invalid: false };
    const issuesBefore = sink.issues.length;
    if (!(BLOCK_KINDS as readonly string[]).includes(kindName)) {
      sink.add("DIRECTIVE_KIND_UNKNOWN", { file, pos: { line: lineNo, column: colons + 1 } }, `Unknown block kind "${kindName}".`);
    } else node.kind = kindName as BlockKind;
    if (attrText !== "") parseAttrs(attrText, file, lineNo, colons + kindName.length + 1, sink, node);

    const closer = new RegExp(`^:{${colons}}\\s*$`);
    const inner = new FenceTracker();
    let j = i + 1;
    let closed = false;
    while (j < lines.length) {
      const l = lines[j] as string;
      if (!inner.feed(l)) {
        if (closer.test(l)) {
          closed = true;
          break;
        }
        const nested = OPENER.exec(l);
        if (nested && (nested[1] as string).length === colons) {
          sink.add("DIRECTIVE_NESTED", { file, pos: { line: j + 1, column: 1 } }, `A directive opens on line ${j + 1} while the one from line ${lineNo} is still open.`);
        }
      }
      j++;
    }
    if (!closed) {
      sink.add("DIRECTIVE_UNCLOSED", { file, pos: { line: lineNo, column: 1 } }, `The directive opened on line ${lineNo} is never closed.`);
    }
    const rawContent = lines.slice(i + 1, j);
    const lead = rawContent.findIndex((l) => l.trim() !== "");
    node.contentLine = lineNo + 1 + Math.max(lead, 0);
    node.markdown = normalizeMarkdown(rawContent.join("\n"));
    if (node.markdown === "" && closed) sink.add("DIRECTIVE_EMPTY", { file, pos: { line: lineNo, column: 1 } }, "The directive has no content.");
    node.invalid = sink.issues.length > issuesBefore;
    nodes.push(node);
    i = closed ? j + 1 : lines.length;
  }
  flushLoose();
  return { file, frontMatter, nodes, hasFrontMatter: fm.present };
}

/** The fence for content: longer than every colon-only or opener line inside it (outside code fences). */
function fenceFor(markdown: string): number {
  let longest = 2;
  const fence = new FenceTracker();
  for (const line of markdown.split("\n")) {
    if (fence.feed(line)) continue;
    const m = /^(:{3,})(?:[A-Za-z].*)?\s*$/.exec(line);
    if (m && ((m[1] as string).length > longest)) longest = (m[1] as string).length;
  }
  return Math.max(3, longest + 1);
}

export function serializeDirective(d: { kind: string; id?: string; cite?: string[]; origin?: string; objectives?: string[]; markdown: string }): string {
  const attrs: string[] = [];
  if (d.id) attrs.push(`#${d.id}`);
  if (d.cite && d.cite.length) attrs.push(`cite="${d.cite.join(",")}"`);
  if (d.origin) attrs.push(`origin=${d.origin}`);
  if (d.objectives && d.objectives.length) attrs.push(`objectives="${d.objectives.join(",")}"`);
  const fence = ":".repeat(fenceFor(d.markdown));
  return `${fence}${d.kind}${attrs.length ? `{${attrs.join(" ")}}` : ""}\n${d.markdown}\n${fence}`;
}

/** A whole lesson file: front matter text (already canonical YAML) and the serialized blocks. */
export function serializeLesson(frontMatterYaml: string, blocks: string[]): string {
  const yaml = frontMatterYaml.endsWith("\n") ? frontMatterYaml : `${frontMatterYaml}\n`;
  return `---\n${yaml}---\n${blocks.length ? `\n${blocks.join("\n\n")}\n` : ""}`;
}
