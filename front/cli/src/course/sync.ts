import { createHash } from "node:crypto";
import { folderFromBlueprint, normalizeDocument } from "./from-blueprint.ts";
import { loadCourse, STRUCTURAL } from "./load.ts";
import { isObj, type Issue, type Obj } from "./types.ts";

/* The sync model of course-as-code (ADR 0160, plan 7.5): a Blueprint v3 document is a tree of elements matched by
 * id. `.ulams/base.json` keeps a hash per element (never a copy of the document), so "what did I change since the
 * last pull" needs no network. Everything here is pure: no file system, no server. */

export const BASE_FORMAT = 1;

/** Canonical JSON: sorted keys, no whitespace. The same value always gives the same text. */
export function canonicalJson(value: unknown): string {
  const walk = (v: unknown): unknown => {
    if (Array.isArray(v)) return v.map(walk);
    if (!isObj(v)) return v;
    return Object.fromEntries(
      Object.keys(v)
        .sort()
        .filter((k) => v[k] !== undefined)
        .map((k) => [k, walk(v[k])])
    );
  };
  return JSON.stringify(walk(value));
}

export const sha256 = (text: string): string => createHash("sha256").update(text).digest("hex");

export interface Element {
  id: string;
  type: string;
  /** A position label that reads like the studio's ("Lesson 1.2 > block 3"). */
  label: string;
  ancestors: string[];
  /** What the element holds itself, without child elements, `meta` and server-owned `sources`. */
  own: Obj;
  hash: string;
  /** One line for a person: the title, the question, or the first words of the text. */
  summary: string;
}

const dropMeta = (o: Obj, drop: string[]): Obj => Object.fromEntries(Object.entries(o).filter(([k, v]) => k !== "meta" && !drop.includes(k) && v !== undefined));

function summaryOf(own: Obj): string {
  const text = [own.title, own.stem, own.text, own.markdown, own.caption].find((v): v is string => typeof v === "string" && v.trim() !== "");
  if (!text) return "";
  const line = (text.split("\n").find((l) => l.trim() !== "") ?? "").trim();
  return line.length > 60 ? `${line.slice(0, 57)}...` : line;
}

/** `pages` without its empty slots; null when nothing is left (the server sends `{landing: null, header: null}`, the folder sends nothing). */
function pagesOf(doc: Obj): Obj | null {
  if (!isObj(doc.pages)) return null;
  const kept = Object.fromEntries(Object.entries(doc.pages).filter(([, v]) => v !== null && v !== undefined));
  return Object.keys(kept).length > 0 ? kept : null;
}

/** Walks a document the way `BlueprintDiff::flatten` does (same ids, same labels) but keeps every field of an element. */
export function flatten(doc: Obj): Map<string, Element> {
  const out = new Map<string, Element>();
  const add = (node: Obj, type: string, label: string, ancestors: string[], children: string[]) => {
    const own = dropMeta(node, children);
    out.set(String(node.id), { id: String(node.id), type, label, ancestors, own, hash: sha256(canonicalJson(own)), summary: summaryOf(own) });
  };
  const list = (v: unknown): Obj[] => (Array.isArray(v) ? v.filter(isObj) : []);

  const question = (q: Obj, label: string, ancestors: string[]) => {
    add(q, "question", label, ancestors, ["options"]);
    list(q.options).forEach((o, i) => add(o, "option", `${label} > option ${String.fromCharCode(65 + i)}`, [...ancestors, String(q.id)], []));
  };
  const quiz = (q: Obj, label: string, ancestors: string[]) => {
    add(q, "quiz", label, ancestors, ["questions"]);
    list(q.questions).forEach((x, i) => question(x, `${label.replace(/ > quiz$/, "")} > Q${i + 1}`, [...ancestors, String(q.id)]));
  };

  const course = isObj(doc.course) ? doc.course : {};
  if (typeof course.id === "string") {
    add(course, "course", "Course", [], ["objectives"]);
    list(course.objectives).forEach((o, i) => add(o, "objective", `Course outcome ${i + 1}`, [course.id as string], []));
  }
  list(doc.modules).forEach((m, mi) => {
    add(m, "module", `Module ${mi + 1}`, [], ["lessons"]);
    list(m.lessons).forEach((l, li) => {
      const label = `Lesson ${mi + 1}.${li + 1}`;
      const anc = [String(m.id)];
      add(l, "lesson", label, anc, ["objectives", "blocks", "selfChecks", "interaction", "quiz"]);
      const inside = [...anc, String(l.id)];
      list(l.objectives).forEach((o, i) => add(o, "objective", `${label} > objective ${i + 1}`, inside, []));
      list(l.blocks).forEach((b, i) => add(b, "block", `${label} > block ${i + 1}`, inside, []));
      list(l.selfChecks).forEach((c, i) => question(c, `${label} > check ${i + 1}`, inside));
      if (isObj(l.interaction) && typeof l.interaction.id === "string") add(l.interaction, "interaction", `${label} > activity`, inside, []);
      if (isObj(l.quiz)) quiz(l.quiz, `${label} > quiz`, inside);
    });
  });
  if (isObj(doc.finalTest)) quiz(doc.finalTest, "Final test", []);
  const pages = pagesOf(doc);
  if (pages) {
    const own = { pages } as Obj;
    out.set("pages", { id: "pages", type: "pages", label: "Landing and header pages", ancestors: [], own, hash: sha256(canonicalJson(own)), summary: "" });
  }
  return out;
}

/** What the files can represent of a document and what the server owns left out: the form that gets hashed and compared. */
export function comparable(doc: Obj): Obj {
  const { sources: _sources, pages: _pages, finalTest, ...rest } = normalizeDocument(doc);
  const pages = pagesOf(doc);
  return { ...rest, ...(isObj(finalTest) ? { finalTest } : {}), ...(pages ? { pages } : {}) };
}

/**
 * A server document in the shape the folder holds it: written to files and read back. Defaults the folder leaves out
 * (a richtext lesson, status "generated", empty citation lists) come back the same way on both sides, so a pull followed
 * by a push of an untouched folder is never a change. Throws the issues when the folder format cannot hold the document.
 */
export function folderForm(doc: Obj): { doc: Obj; errors: Issue[] } {
  const loaded = loadCourse(folderFromBlueprint(doc), () => null);
  // only what the files cannot hold; a lesson without citations is the validator's business, not a loss of data
  const errors = loaded.issues.filter((i) => i.severity === "error" && STRUCTURAL.test(i.code));
  return { doc: loaded.doc, errors };
}

export const documentHash = (doc: Obj): string => sha256(canonicalJson(comparable(doc)));

export interface FieldChange {
  field: string;
  before: unknown;
  after: unknown;
}
export interface Change {
  id: string;
  type: string;
  label: string;
  summary: string;
  kind: "added" | "changed" | "removed";
  ancestors: string[];
  /** Field-level detail; empty when only hashes were compared. */
  fields: FieldChange[];
}

/** Scalars as they are, lists of scalars joined, anything nested as canonical JSON (the way the server's diff shows them). */
function shown(value: unknown): unknown {
  if (Array.isArray(value)) return value.every((v) => v === null || typeof v !== "object") ? value.join(", ") : canonicalJson(value);
  if (isObj(value)) return canonicalJson(value);
  return value;
}

/** Element-aware diff of two documents with field detail (what `GET versions/{v}/diff` returns, computed locally). */
export function compareDocuments(before: Obj, after: Obj): Change[] {
  const a = flatten(before);
  const b = flatten(after);
  const changes: Change[] = [];
  for (const [id, node] of b) {
    const old = a.get(id);
    const base = { id, type: node.type, label: node.label, summary: node.summary, ancestors: node.ancestors };
    if (!old) {
      changes.push({ ...base, kind: "added", fields: Object.entries(node.own).map(([field, v]) => ({ field, before: null, after: shown(v) })) });
      continue;
    }
    if (old.hash === node.hash) continue;
    const fields: FieldChange[] = [];
    for (const key of new Set([...Object.keys(old.own), ...Object.keys(node.own)])) {
      const x = shown(old.own[key] ?? null);
      const y = shown(node.own[key] ?? null);
      if (canonicalJson(x) !== canonicalJson(y)) fields.push({ field: key, before: x, after: y });
    }
    changes.push({ ...base, kind: "changed", fields });
  }
  for (const [id, node] of a) {
    if (!b.has(id)) changes.push({ id, type: node.type, label: node.label, summary: node.summary, ancestors: node.ancestors, kind: "removed", fields: Object.entries(node.own).map(([field, v]) => ({ field, before: shown(v), after: null })) });
  }
  return changes;
}

export interface Counts {
  added: number;
  changed: number;
  removed: number;
}
export const countChanges = (changes: Array<{ kind: Change["kind"] }>): Counts => {
  const c: Counts = { added: 0, changed: 0, removed: 0 };
  for (const x of changes) c[x.kind]++;
  return c;
};

/** `.ulams/base.json`: where the folder was last in sync with the server. A hash snapshot, never a copy of the course. */
export interface BaseFile {
  format: number;
  sessionId: string;
  courseId: number | null;
  baseVersionId: string;
  baseVersionNumber: number;
  documentHash: string;
  elements: Record<string, string>;
  /** Type and position label per element (no text), so a removed element can still be named. */
  labels: Record<string, { type: string; label: string }>;
  assets: Record<string, string>;
}

function assetHashes(doc: Obj): Record<string, string> {
  const out: Record<string, string> = {};
  const lessons = (Array.isArray(doc.modules) ? doc.modules : []).flatMap((m) => (isObj(m) && Array.isArray(m.lessons) ? m.lessons : []));
  for (const l of lessons) {
    const asset = isObj(l) && isObj(l.asset) ? l.asset : null;
    if (asset && typeof asset.path === "string" && typeof asset.sha256 === "string") out[asset.path] = asset.sha256;
  }
  return out;
}

export function makeBase(doc: Obj, v: { sessionId: string; courseId: number | null; versionId: string; versionNumber: number }): BaseFile {
  const elements = flatten(doc);
  return {
    format: BASE_FORMAT,
    sessionId: v.sessionId,
    courseId: v.courseId,
    baseVersionId: v.versionId,
    baseVersionNumber: v.versionNumber,
    documentHash: documentHash(doc),
    elements: Object.fromEntries([...elements].map(([id, e]) => [id, e.hash]).sort(([x], [y]) => String(x).localeCompare(String(y)))),
    labels: Object.fromEntries([...elements].map(([id, e]) => [id, { type: e.type, label: e.label }]).sort(([x], [y]) => String(x as string).localeCompare(String(y as string)))) as BaseFile["labels"],
    assets: assetHashes(doc),
  };
}

export const serializeBase = (base: BaseFile): string => `${JSON.stringify(base, null, 2)}\n`;

/** Parses `.ulams/base.json`; null with a reason when it is not a usable base. */
export function parseBase(text: string): { base: BaseFile } | { error: string } {
  let raw: unknown;
  try {
    raw = JSON.parse(text);
  } catch (e) {
    return { error: `is not valid JSON: ${(e as Error).message}` };
  }
  if (!isObj(raw)) return { error: "must hold a JSON object" };
  if (raw.format !== BASE_FORMAT) return { error: `has format ${String(raw.format)}; this CLI reads format ${BASE_FORMAT}` };
  for (const key of ["sessionId", "baseVersionId", "documentHash"]) if (typeof raw[key] !== "string" || raw[key] === "") return { error: `is missing "${key}"` };
  if (typeof raw.baseVersionNumber !== "number") return { error: 'is missing "baseVersionNumber"' };
  if (!isObj(raw.elements)) return { error: 'is missing "elements"' };
  return {
    base: {
      format: BASE_FORMAT,
      sessionId: raw.sessionId as string,
      courseId: typeof raw.courseId === "number" ? raw.courseId : null,
      baseVersionId: raw.baseVersionId as string,
      baseVersionNumber: raw.baseVersionNumber,
      documentHash: raw.documentHash as string,
      elements: raw.elements as Record<string, string>,
      labels: (isObj(raw.labels) ? raw.labels : {}) as BaseFile["labels"],
      assets: (isObj(raw.assets) ? raw.assets : {}) as Record<string, string>,
    },
  };
}

/** Local or remote document against the base, by hash only: which elements were added, changed or removed. */
export function changesSinceBase(base: BaseFile, doc: Obj): { changes: Change[]; counts: Counts; orderChanged: boolean; modified: boolean } {
  const now = flatten(doc);
  const changes: Change[] = [];
  for (const [id, e] of now) {
    const was = base.elements[id];
    const meta = { id, type: e.type, label: e.label, summary: e.summary, ancestors: e.ancestors, fields: [] as FieldChange[] };
    if (was === undefined) changes.push({ ...meta, kind: "added" });
    else if (was !== e.hash) changes.push({ ...meta, kind: "changed" });
  }
  for (const id of Object.keys(base.elements)) {
    if (now.has(id)) continue;
    const l = base.labels[id];
    changes.push({ id, type: l?.type ?? "element", label: l?.label ?? id, summary: "", ancestors: [], kind: "removed", fields: [] });
  }
  const modified = documentHash(doc) !== base.documentHash;
  return { changes, counts: countChanges(changes), orderChanged: modified && changes.length === 0, modified };
}
