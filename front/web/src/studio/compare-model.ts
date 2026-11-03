/**
 * View model of the studio Compare page: turns the compare API's payload into a tree of changed elements, filters,
 * figures and sentences in the reader's language. No DOM here, so every case is unit-tested. The API sends English
 * element labels ("Lesson 2.1 › block 3"); the page writes its own names from the type and the numbers in them.
 */
import type { ChangeField, CompareChangeKind, CitationPassage, CompareSide, CompareSummary, CourseCompare, CourseVariantRef, CourseVersion, ElementChange, ElementRef, QuizChange } from "@ulams/sdk";
import { fill, COMPARE_STRINGS, type CompareLocale, type CompareStrings } from "./compare-i18n.ts";

export interface CompareFormatter {
  t: CompareStrings;
  number: (n: number) => string;
  dateTime: (iso: string | null | undefined) => string;
}

export function compareFormatterFor(locale: CompareLocale): CompareFormatter {
  const t = COMPARE_STRINGS[locale];
  const number = new Intl.NumberFormat(t.htmlLang);
  const dateTime = new Intl.DateTimeFormat(t.htmlLang, { dateStyle: "medium", timeStyle: "short" });
  return {
    t,
    number: (n) => number.format(n),
    dateTime: (iso) => {
      const d = iso ? new Date(iso) : null;
      return d === null || Number.isNaN(d.getTime()) ? "–" : dateTime.format(d);
    },
  };
}

/** Symbols that go with the words, so no state is shown by colour alone (the words always come too). */
export const KIND_SYMBOL: Record<CompareChangeKind, string> = { added: "+", removed: "−", changed: "~", moved: "→", reordered: "↕" };
export const KIND_ORDER: readonly CompareChangeKind[] = ["added", "removed", "changed", "moved", "reordered"];

/** What happened to an element, in the order the badges are shown: the change itself, then a move and a reorder. */
export function kindsOf(change: Pick<ElementChange, "kind" | "moved" | "reordered">): CompareChangeKind[] {
  const out: CompareChangeKind[] = [];
  if (change.kind === "added" || change.kind === "removed" || change.kind === "changed") out.push(change.kind);
  if (change.moved !== null) out.push("moved");
  if (change.reordered !== null) out.push("reordered");
  return out.length ? out : [change.kind];
}

const lastSegment = (label: string): string => label.split(" › ").pop() ?? label;

/**
 * An element's name in the reader's language: "Lesson 2.1", "Block 3", "Question 2", "Option B", "Final test". An element
 * whose label does not follow the pattern keeps its label from the API, so nothing is dropped.
 */
export function elementName(t: CompareStrings, ref: { type: string | null; label: string }): string {
  const last = lastSegment(ref.label);
  const number = /(\d+(?:\.\d+)?)\s*$/.exec(last)?.[1];
  const letter = /\b([A-Z])\s*$/.exec(last)?.[1];
  const type = ref.type ?? "";
  const noun = t.types[type];
  switch (type) {
    case "course":
      return noun ?? ref.label;
    case "quiz":
      return /^Final test/.test(ref.label) ? t.finalTest : noun ?? ref.label;
    case "interaction":
      return noun ?? ref.label;
    case "question":
      if (!number) return ref.label;
      return `${/check/i.test(last) ? t.check : noun} ${number}`;
    case "option":
      return letter && noun ? `${noun} ${letter}` : ref.label;
    case "module":
    case "lesson":
    case "block":
    case "objective":
      return number && noun ? `${noun} ${number}` : ref.label;
    default:
      return ref.label;
  }
}

/** A name with the title of the element when it has one: `Lesson 2.1: Brewing basics`. */
export const elementTitle = (t: CompareStrings, ref: { type: string | null; label: string; title?: string | null }): string => {
  const name = elementName(t, ref);
  return ref.title ? `${name}: ${ref.title}` : name;
};

export interface TreeNode {
  id: string;
  type: string | null;
  label: string;
  title: string | null;
  /** The change of the element itself; null for an unchanged parent that is shown for context. */
  change: ElementChange | null;
  parent: TreeNode | null;
  children: TreeNode[];
  depth: number;
}

const TYPE_RANK: Record<string, number> = { course: 0, objective: 1, module: 2, lesson: 3, block: 4, question: 5, interaction: 6, quiz: 7, option: 8 };

/** The numbers (or the letter) of an element's own label, to keep siblings in document order. */
function orderKey(node: Pick<TreeNode, "type" | "label">): number[] {
  const last = lastSegment(node.label);
  const numbers = (/(\d+(?:\.\d+)?)\s*$/.exec(last)?.[1] ?? "").split(".").filter(Boolean).map(Number);
  if (numbers.length) return numbers;
  const letter = /\b([A-Z])\s*$/.exec(last)?.[1];
  return letter ? [letter.charCodeAt(0)] : [Number.MAX_SAFE_INTEGER];
}

function compareNodes(a: TreeNode, b: TreeNode): number {
  const rank = (TYPE_RANK[a.type ?? ""] ?? 99) - (TYPE_RANK[b.type ?? ""] ?? 99);
  if (rank !== 0) return rank;
  const x = orderKey(a);
  const y = orderKey(b);
  for (let i = 0; i < Math.max(x.length, y.length); i++) {
    const d = (x[i] ?? -1) - (y[i] ?? -1);
    if (d !== 0) return d;
  }
  return 0;
}

/** The parents of a change; a payload from before `path` existed has only ids, which still give the nesting. */
const pathOf = (change: ElementChange): ElementRef[] => change.path ?? change.ancestors.map((id) => ({ id, type: null, label: id, title: null }));

/**
 * The changed elements as a tree. A parent that did not change itself is a context node (no change), so a changed block
 * is always reached through its lesson and module. Siblings keep document order.
 */
export function buildTree(changes: ElementChange[]): TreeNode[] {
  const nodes = new Map<string, TreeNode>();
  const node = (ref: { id: string; type: string | null; label: string; title: string | null }): TreeNode => {
    let n = nodes.get(ref.id);
    if (!n) {
      n = { id: ref.id, type: ref.type, label: ref.label, title: ref.title, change: null, parent: null, children: [], depth: 0 };
      nodes.set(ref.id, n);
    }
    return n;
  };
  for (const change of changes) {
    let parent: TreeNode | null = null;
    for (const ref of pathOf(change)) {
      const n = node(ref);
      if (parent && n.parent === null && n !== parent) {
        n.parent = parent;
        parent.children.push(n);
      }
      parent = n;
    }
    const self = node({ id: change.id, type: change.type, label: change.label, title: change.title ?? null });
    self.type = change.type;
    self.label = change.label;
    self.title = change.title ?? self.title;
    self.change = change;
    if (parent && self.parent === null && self !== parent) {
      self.parent = parent;
      parent.children.push(self);
    }
  }
  const roots = [...nodes.values()].filter((n) => n.parent === null);
  const finish = (list: TreeNode[], depth: number): void => {
    list.sort(compareNodes);
    for (const n of list) {
      n.depth = depth;
      finish(n.children, depth + 1);
    }
  };
  finish(roots, 1);
  return roots;
}

export function walk(roots: TreeNode[], visit: (node: TreeNode) => void): void {
  for (const n of roots) {
    visit(n);
    walk(n.children, visit);
  }
}

/** The names of an element's parents, outermost first, e.g. `Module 2 › Lesson 2.1`. */
export function breadcrumb(t: CompareStrings, node: TreeNode): string {
  const names: string[] = [];
  for (let p = node.parent; p !== null; p = p.parent) names.unshift(elementName(t, p));
  return names.join(t.pathSeparator);
}

export interface Filters {
  kinds: ReadonlySet<CompareChangeKind>;
  types: ReadonlySet<string>;
}

export const NO_FILTERS: Filters = { kinds: new Set(), types: new Set() };

/** True when the change passes the filters (an empty filter group lets everything through). */
export function matches(change: ElementChange, filters: Filters): boolean {
  const kindOk = filters.kinds.size === 0 || kindsOf(change).some((k) => filters.kinds.has(k));
  const typeOk = filters.types.size === 0 || filters.types.has(change.type);
  return kindOk && typeOk;
}

/** Ids of the nodes to show: the ones that pass the filters and, for context, every parent of one that does. */
export function visibleIds(roots: TreeNode[], filters: Filters): { ids: Set<string>; shown: number } {
  const ids = new Set<string>();
  let shown = 0;
  walk(roots, (n) => {
    if (n.change && matches(n.change, filters)) {
      shown++;
      for (let p: TreeNode | null = n; p !== null; p = p.parent) ids.add(p.id);
    }
  });
  return { ids, shown };
}

/** The element types that occur among the changes, in tree order, for the type filter. */
export function typesPresent(changes: ElementChange[]): string[] {
  const seen = new Set(changes.map((c) => c.type));
  return Object.keys(TYPE_RANK).filter((type) => seen.has(type)).concat([...seen].filter((type) => !(type in TYPE_RANK)));
}

export function kindsPresent(changes: ElementChange[]): CompareChangeKind[] {
  const seen = new Set(changes.flatMap((c) => kindsOf(c)));
  return KIND_ORDER.filter((k) => seen.has(k));
}

export interface Figure {
  label: string;
  value: string;
}

/** The counts of the summary bar, as "label: number" pairs (zero counts are left out, except when nothing else shows). */
export function summaryFigures(f: CompareFormatter, s: CompareSummary): Figure[] {
  const t = f.t;
  const rows: Array<[string, number]> = [
    [t.figAdded, s.counts.added],
    [t.figRemoved, s.counts.removed],
    [t.figChanged, s.counts.changed],
    [t.figMoved, s.counts.moved],
    [t.figReordered, s.counts.reordered],
    [t.figCitAdded, s.citations.added],
    [t.figCitRemoved, s.citations.removed],
    [t.figCitRepointed, s.citations.repointed],
    [t.figKeys, s.quiz.answerKeysChanged],
    [t.figReworded, s.quiz.reworded],
    [t.figQuestionsAdded, s.quiz.questionsAdded],
    [t.figQuestionsRemoved, s.quiz.questionsRemoved],
  ];
  return rows.filter(([, n]) => n > 0).map(([label, n]) => ({ label, value: f.number(n) }));
}

/** What learners would notice (always all six figures: a zero is information here). */
export function impactFigures(f: CompareFormatter, s: CompareSummary): Figure[] {
  const t = f.t;
  const i = s.learnerImpact;
  return [
    [t.impKeeping, i.lessonsKeepingCompletion],
    [t.impUpdated, i.lessonsWithUpdatedNotice],
    [t.impSilent, i.lessonsChangedSilently],
    [t.impRemoved, i.lessonsRemoved],
    [t.impAdded, i.lessonsAdded],
    [t.impReattempt, i.reattemptNotices],
  ].map(([label, n]) => ({ label: label as string, value: f.number(n as number) }));
}

/** True when the two sides have the same content. */
export const isEmpty = (c: Pick<CourseCompare, "changes" | "quiz">): boolean => c.changes.length === 0 && c.quiz.length === 0;

export function fieldName(t: CompareStrings, field: Pick<ChangeField, "field" | "label">): string {
  return t.fields[field.field] ?? field.label;
}

/** A field value as text: booleans as yes or no, who wrote it in words, nothing as "(empty)". */
export function fieldValue(t: CompareStrings, field: string, value: ChangeField["before"]): string {
  if (value === null || value === undefined || value === "") return t.empty;
  if (typeof value === "boolean") return value ? t.yes : t.no;
  if (field === "origin") return t.origins[String(value)] ?? String(value);
  return String(value);
}

/** True for a field the page shows as a word diff (text that a person wrote). */
export const isTextField = (field: string): boolean => !["correct", "origin", "minutes", "kind", "type", "contentType", "library", "packageId", "startStep", "endStep", "asset"].includes(field);

/** The citation fields are shown in their own panel. */
export const fieldsToShow = (change: ElementChange): ChangeField[] => change.fields.filter((f) => f.field !== "citations");

export interface CitationRow {
  kind: "added" | "removed" | "repointed";
  /** `added` and `removed` have one passage, `repointed` the passage before and after. */
  before: CitationPassage | null;
  after: CitationPassage | null;
  id: string;
}

export function citationRows(change: ElementChange, fragments: Record<string, CitationPassage>): CitationRow[] {
  const c = change.citations;
  if (!c) return [];
  return [
    ...c.added.map((id) => ({ kind: "added" as const, before: null, after: fragments[id] ?? null, id })),
    ...c.removed.map((id) => ({ kind: "removed" as const, before: fragments[id] ?? null, after: null, id })),
    ...c.repointed.map((p) => ({ kind: "repointed" as const, before: fragments[p.from] ?? null, after: fragments[p.to] ?? null, id: p.to })),
  ];
}

/** The quiz change of a question element (the question may be unchanged itself while one of its options changed). */
export const quizFor = (quiz: QuizChange[], id: string): QuizChange | null => quiz.find((q) => q.questionId === id) ?? null;

export function quizHeadline(t: CompareStrings, q: QuizChange): string {
  const parts: string[] = [];
  if (q.answerKeyChanged) parts.push(t.quizKey);
  if (q.wording) parts.push(t.quizWording);
  if (q.type !== null) parts.push(t.quizTypeChanged);
  if (!parts.length) parts.push(t.quizWording);
  return parts.join(", ");
}

/** "Version 7, release #3": one side of the comparison. */
export function sideName(f: CompareFormatter, side: Pick<CompareSide, "versionNumber" | "releaseNumber">): string {
  const version = fill(f.t.versionLabel, { n: f.number(side.versionNumber) });
  return side.releaseNumber === null ? version : `${version}, ${fill(f.t.versionReleased, { n: f.number(side.releaseNumber) })}`;
}

export function sideLine(f: CompareFormatter, side: CompareSide): string {
  const t = f.t;
  const release = side.releaseNumber === null ? t.versionNotReleased : fill(t.versionReleased, { n: f.number(side.releaseNumber) });
  return fill(t.sideLine, { version: fill(t.versionLabel, { n: f.number(side.versionNumber) }), release, date: f.dateTime(side.releasedAt ?? side.createdAt) });
}

/** The text of a version in a picker: number, release, state, date and branch. */
export function versionOptionLabel(f: CompareFormatter, v: CourseVersion, variants: CourseVariantRef[]): string {
  const t = f.t;
  const parts = [
    fill(t.versionLabel, { n: f.number(v.number) }),
    v.releaseNumber === null ? t.versionNotReleased : fill(t.versionReleased, { n: f.number(v.releaseNumber) }),
  ];
  if (v.status !== "approved") parts.push(t.statuses[v.status] ?? v.status);
  parts.push(f.dateTime(v.createdAt));
  if (variants.length) parts.push(v.variantId === null ? t.baseCourse : variants.find((x) => x.id === v.variantId)?.label ?? t.variantsOnly);
  return parts.join(" · ");
}

/**
 * The latest version learners got on a branch (the newest release), else its newest approved version. `versions` is newest
 * first, as the API sends it.
 */
export function latestOfBranch(versions: CourseVersion[], variantId: string | null): CourseVersion | null {
  const own = versions.filter((v) => v.variantId === variantId);
  return own.find((v) => v.releaseId !== null) ?? own.find((v) => v.status === "approved") ?? null;
}

/** The base course against one variant: the latest version of each. Null when either has none. */
export function variantPair(versions: CourseVersion[], variantId: string): { from: string; to: string } | null {
  const base = latestOfBranch(versions, null);
  const variant = latestOfBranch(versions, variantId);
  return base && variant ? { from: base.id, to: variant.id } : null;
}

/**
 * The two sides of a comparison ordered from the older version to the newer by version number, so that "From" is always
 * the older side whichever order the author ticked them in.
 */
export function orderPair<T extends { number: number }>(a: T, b: T): [T, T] {
  return a.number <= b.number ? [a, b] : [b, a];
}

/** The path of the compare page for two version or release ids. */
export function comparePath(courseId: number, from: string, to: string, lang?: string): string {
  const q = new URLSearchParams({ from, to });
  if (lang) q.set("lang", lang);
  return `/studio/courses/${courseId}/compare?${q.toString()}`;
}
