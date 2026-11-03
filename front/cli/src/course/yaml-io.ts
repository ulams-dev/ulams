import { Document, LineCounter, YAMLMap, isMap, isScalar, isSeq, parseDocument, type Node } from "yaml";
import type { Sink } from "./issues.ts";
import type { Path, Pos } from "./types.ts";

export interface YamlFile {
  file: string;
  text: string;
  doc: Document.Parsed;
  lc: LineCounter;
  /** Lines before the YAML text in the real file (front matter starts below the opening ---). */
  lineOffset: number;
  data: unknown;
}

/** Parses YAML and reports syntax errors with file, line and column. Returns null when the text cannot be parsed. */
export function parseYamlFile(file: string, text: string, sink: Sink, lineOffset = 0): YamlFile | null {
  const lc = new LineCounter();
  const doc = parseDocument(text, { lineCounter: lc, prettyErrors: false, uniqueKeys: true });
  if (doc.errors.length > 0) {
    for (const e of doc.errors) {
      const p = e.linePos?.[0];
      const message = e.message.split("\n")[0] ?? e.message;
      sink.add("YAML_SYNTAX", { file, pos: { line: (p?.line ?? 1) + lineOffset, column: p?.col ?? 1 } }, message);
    }
    return null;
  }
  return { file, text, doc, lc, lineOffset, data: doc.toJS() };
}

/** The deepest node of `path` that exists, as a line and column (keys win over their values). */
export function yamlPos(yf: YamlFile, path: Path): Pos {
  let node: unknown = yf.doc.contents;
  let range: [number, number, number] | null | undefined = (node as Node | null)?.range;
  for (const seg of path) {
    if (isMap(node)) {
      const pair = node.items.find((p) => isScalar(p.key) && String(p.key.value) === String(seg));
      if (!pair) break;
      range = (pair.key as Node).range ?? range;
      node = pair.value;
    } else if (isSeq(node) && typeof seg === "number") {
      const item = node.items[seg];
      if (!item) break;
      range = (item as Node).range ?? range;
      node = item;
    } else break;
  }
  const p = yf.lc.linePos(range?.[0] ?? 0);
  return { line: p.line + yf.lineOffset, column: p.col };
}

export const YAML_OPTIONS = { lineWidth: 0, indent: 2 } as const;

/** Key order of a file, recursively: unknown keys keep their relative order after the known ones. */
export interface KeySpec {
  keys: string[];
  child?: Record<string, KeySpec>;
  /** Maps under these keys are written as `{ a: 1, b: 2 }` when they fit on one short line. */
  flow?: string[];
  /** Keys that are left out when they hold their default (`[]` means an empty list). */
  omit?: Record<string, string | number | boolean | []>;
}

/** Keys read from a file are scalar nodes; keys added with `setIn` are plain strings. */
const keyOf = (pair: YAMLMap["items"][number]): string => String(isScalar(pair.key) ? pair.key.value : pair.key);

function holdsDefault(value: unknown, def: string | number | boolean | []): boolean {
  if (Array.isArray(def)) return isSeq(value) && value.items.length === 0;
  return isScalar(value) && value.value === def;
}

function sortMap(map: YAMLMap, spec: KeySpec): void {
  if (spec.omit) {
    const omit = spec.omit;
    map.items = map.items.filter((pair) => !(keyOf(pair) in omit && holdsDefault(pair.value, omit[keyOf(pair)] as string | number | boolean | [])));
  }
  const rank = new Map(spec.keys.map((k, i) => [k, i]));
  const rankOf = (item: YAMLMap["items"][number]) => rank.get(keyOf(item)) ?? 1000;
  map.items.sort((a, b) => rankOf(a) - rankOf(b));
  for (const pair of map.items) {
    const key = keyOf(pair);
    const child = spec.child?.[key];
    const flow = spec.flow?.includes(key);
    if (!child && !flow) continue;
    const visit = (n: unknown) => {
      if (!isMap(n)) return;
      if (child) sortMap(n, child);
      if (flow) n.flow = fitsOnOneLine(n);
    };
    if (isSeq(pair.value)) pair.value.items.forEach(visit);
    else visit(pair.value);
  }
}

function fitsOnOneLine(map: YAMLMap): boolean {
  const scalarOnly = map.items.every((p) => isScalar(p.value) && typeof p.value.value !== "object");
  if (!scalarOnly) return false;
  const text = map.items.map((p) => `${keyOf(p)}: ${String((p.value as { value?: unknown }).value)}`).join(", ");
  return text.length <= 100 && !text.includes("\n");
}

/** One canonical style: block collections, scalars quoted only where YAML needs it. Comments stay. */
function normalizeStyle(node: unknown): void {
  if (isMap(node) || isSeq(node)) {
    node.flow = node.items.length === 0;
    const items = node.items as unknown[];
    for (const item of items) {
      if (isMap(node)) {
        const pair = item as YAMLMap["items"][number];
        if (isScalar(pair.key)) pair.key.type = undefined;
        normalizeStyle(pair.value);
      } else normalizeStyle(item);
    }
  } else if (isScalar(node)) node.type = undefined;
}

export function canonicalize(doc: Document, spec: KeySpec): void {
  normalizeStyle(doc.contents);
  if (isMap(doc.contents)) sortMap(doc.contents, spec);
}

export function stringifyDoc(doc: Document): string {
  return doc.toString(YAML_OPTIONS);
}

/** Canonical text of a document that was edited in place: print, re-read (so edits become ordinary nodes), order the keys, print. */
export function settle(doc: Document, spec: KeySpec): string {
  const again = parseDocument(stringifyDoc(doc));
  canonicalize(again, spec);
  return stringifyDoc(again);
}

/** A plain value written as canonical YAML (what `from-blueprint` uses; `fmt` produces the same text from parsed files). */
export function stringifyValue(value: unknown, spec: KeySpec): string {
  const doc = new Document(value);
  canonicalize(doc, spec);
  return stringifyDoc(doc);
}
