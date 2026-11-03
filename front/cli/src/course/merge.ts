import { normalizeMarkdown } from "./lesson-file.ts";
import { canonicalJson } from "./sync.ts";
import { isObj, type Obj } from "./types.ts";

/* The three-way merge of course-as-code (ADR 0160, plan 7.5, milestone CAC-4). A Blueprint v3 document is a tree of
 * elements matched by id; `merge(base, ours, theirs)` decides per element, never per field (default 4 of the plan: one
 * conflict per element, no field-level merge in v1). Pure: no files, no server. The same algorithm runs on the server
 * (api/packages/course-builder, `ElementMerge`); `api/packages/course-builder/tests/fixtures/merge/*.json` is run by
 * both test suites so the two cannot drift. "ours" is the author's folder, "theirs" is the server. */

export const DOC = "$doc";

type Slot = { type: string; many: boolean };

/** Which child slots an element type has, and what lives in them. */
const SLOTS: Record<string, Record<string, Slot>> = {
  doc: { course: { type: "course", many: false }, modules: { type: "module", many: true }, finalTest: { type: "quiz", many: false }, pages: { type: "pages", many: false } },
  course: { objectives: { type: "objective", many: true } },
  module: { lessons: { type: "lesson", many: true } },
  lesson: {
    objectives: { type: "objective", many: true },
    blocks: { type: "block", many: true },
    selfChecks: { type: "question", many: true },
    interaction: { type: "interaction", many: false },
    quiz: { type: "quiz", many: false },
  },
  quiz: { questions: { type: "question", many: true } },
  question: { options: { type: "option", many: true } },
};

/** Top-level keys that are not elements: the server owns `sources`; the rest of the root is the root element's own data. */
const ROOT_ELEMENT_KEYS = ["course", "modules", "finalTest", "pages", "sources", "meta"];

export interface MNode {
  id: string;
  type: string;
  parent: string;
  slot: string;
  /** The element's own data as the document holds it (no children, no `meta`). */
  own: Obj;
  /** Canonical text of the normalised own data: equal text means equal element. */
  norm: string;
  /** Child slots that appear as keys in the document (an empty list and a null interaction are kept as they were). */
  keys: Record<string, "list" | "null" | "one">;
}

export interface Tree {
  nodes: Map<string, MNode>;
  kids: Map<string, string[]>;
}

const kidsKey = (parent: string, slot: string): string => `${parent}\u0000${slot}`;
export const kidsOf = (t: Tree, parent: string, slot: string): string[] => t.kids.get(kidsKey(parent, slot)) ?? [];

const DEFAULT_LISTS = new Set(["citations", "objectiveIds", "flags"]);

/** What the folder format cannot tell apart: null and absent, an empty list and absent, the defaults it leaves out, Markdown line ends. */
function normalise(value: unknown, key?: string): unknown {
  if (Array.isArray(value)) return value.map((v) => normalise(v));
  if (!isObj(value)) return typeof value === "string" && key === "markdown" ? normalizeMarkdown(value) : value;
  const out: Obj = {};
  for (const [k, v] of Object.entries(value)) {
    if (k === "meta" || v === null || v === undefined) continue;
    if (k === "data") {
      out[k] = v;
      continue;
    }
    if (DEFAULT_LISTS.has(k) && Array.isArray(v) && v.length === 0) continue;
    if ((k === "contentType" && v === "richtext") || (k === "status" && v === "generated") || (k === "correct" && v === false)) continue;
    out[k] = normalise(v, k);
  }
  return out;
}

export const normText = (own: Obj): string => canonicalJson(normalise(own));

export function treeOf(doc: Obj): Tree {
  const t: Tree = { nodes: new Map(), kids: new Map() };
  const add = (node: Obj, id: string, type: string, parent: string, slot: string, childSlots: Record<string, Slot>, skip: string[]): MNode => {
    const own: Obj = {};
    const keys: MNode["keys"] = {};
    for (const [k, v] of Object.entries(node)) {
      if (k === "meta" || v === undefined || skip.includes(k)) continue;
      if (k in childSlots) {
        keys[k] = Array.isArray(v) ? "list" : v === null ? "null" : "one";
        continue;
      }
      own[k] = v;
    }
    const n: MNode = { id, type, parent, slot, own, norm: normText(own), keys };
    t.nodes.set(id, n);
    if (parent !== "") {
      const key = kidsKey(parent, slot);
      t.kids.set(key, [...(t.kids.get(key) ?? []), id]);
    }
    return n;
  };
  const walk = (node: Obj, id: string, type: string, parent: string, slot: string): void => {
    const childSlots = SLOTS[type] ?? {};
    add(node, id, type, parent, slot, childSlots, []);
    for (const [name, spec] of Object.entries(childSlots)) {
      const v = node[name];
      const items = spec.many ? (Array.isArray(v) ? v : []) : isObj(v) ? [v] : [];
      for (const item of items) if (isObj(item) && typeof item.id === "string") walk(item, item.id, spec.type, id, name);
    }
  };

  const root: Obj = Object.fromEntries(Object.entries(doc).filter(([k]) => !ROOT_ELEMENT_KEYS.includes(k)));
  const rootSlots = SLOTS.doc as Record<string, Slot>;
  const rootNode: MNode = { id: DOC, type: "doc", parent: "", slot: "", own: root, norm: normText(root), keys: doc.finalTest === null ? { finalTest: "null" } : {} };
  t.nodes.set(DOC, rootNode);
  for (const [name, spec] of Object.entries(rootSlots)) {
    const v = doc[name];
    if (name === "pages") {
      // the landing and header pages are one element: both sides edit one JSON document each
      const n = isObj(v) ? normalise(v) : null;
      if (isObj(v) && n !== null && Object.keys(n as Obj).length > 0) {
        t.nodes.set("pages", { id: "pages", type: "pages", parent: DOC, slot: "pages", own: v, norm: canonicalJson(n), keys: {} });
        t.kids.set(kidsKey(DOC, "pages"), ["pages"]);
      } else if (isObj(v)) rootNode.own = { ...rootNode.own, pages: v }; // empty slots: kept as they were, not compared
      continue;
    }
    const items = spec.many ? (Array.isArray(v) ? v : []) : isObj(v) ? [v] : [];
    for (const item of items) if (isObj(item) && typeof item.id === "string") walk(item, item.id, spec.type, DOC, name);
  }
  return t;
}

/** The document a tree describes (root fields, then each slot in its order). */
export function buildDocument(t: Tree): Obj {
  const make = (id: string): Obj => {
    const n = t.nodes.get(id) as MNode;
    const out: Obj = { ...n.own };
    for (const [name, spec] of Object.entries(SLOTS[n.type] ?? {})) {
      const list = kidsOf(t, id, name);
      if (spec.many) {
        if (list.length > 0 || n.keys[name] === "list") out[name] = list.map(make);
      } else if (list.length > 0) out[name] = make(list[0] as string);
      else if (n.keys[name] === "null") out[name] = null;
    }
    return out;
  };
  const root = t.nodes.get(DOC) as MNode;
  const doc: Obj = { ...root.own };
  for (const [name, spec] of Object.entries(SLOTS.doc as Record<string, Slot>)) {
    const list = kidsOf(t, DOC, name);
    if (name === "pages") {
      if (list.length > 0) doc.pages = (t.nodes.get("pages") as MNode).own;
    } else if (spec.many) doc[name] = list.map(make);
    else if (list.length > 0) doc[name] = make(list[0] as string);
    else if (root.keys[name] === "null") doc[name] = null;
  }
  return doc;
}

/* ---------------------------------------------------------------- the merge */

export type ConflictKind = "edit-edit" | "add-add" | "move-move" | "removed-ours" | "removed-theirs" | "slot" | "orphan";

export interface ConflictField {
  field: string;
  base: unknown;
  ours: unknown;
  theirs: unknown;
}

export interface Conflict {
  id: string;
  kind: ConflictKind;
  type: string;
  /** Own fields (without children) of the element on each side; null where the element does not exist. */
  base: Obj | null;
  ours: Obj | null;
  theirs: Obj | null;
  fields: ConflictField[];
  /** Elements the conflict holds back with it (a removed element's children). */
  held: string[];
}

export interface Taken {
  id: string;
  kind: "added" | "changed" | "removed" | "moved";
}

export interface MergeNote {
  code: "ORDER_BOTH";
  id: string;
  message: string;
}

export interface MergeResult {
  /** The merged document. Elements in conflict hold their "ours" form (or are absent when ours removed them). */
  doc: Obj;
  tree: Tree;
  conflicts: Conflict[];
  notes: MergeNote[];
  /** What each side contributed without a conflict. */
  fromOurs: Taken[];
  fromTheirs: Taken[];
}

const placed = (a: MNode, b: MNode): boolean => a.parent === b.parent && a.slot === b.slot;

function descendants(t: Tree, id: string): string[] {
  const out: string[] = [];
  const walk = (parent: string) => {
    for (const slot of Object.keys(SLOTS[t.nodes.get(parent)?.type ?? ""] ?? {})) {
      for (const k of kidsOf(t, parent, slot)) {
        out.push(k);
        walk(k);
      }
    }
  };
  walk(id);
  return out;
}

/** True when the element is `ancestor` or sits below it in the tree. */
function below(t: Tree, id: string, ancestor: string): boolean {
  for (let cur: string | undefined = id; cur !== undefined && cur !== ""; cur = t.nodes.get(cur)?.parent) {
    if (cur === ancestor) return true;
  }
  return false;
}

function fieldChanges(base: Obj | null, ours: Obj | null, theirs: Obj | null): ConflictField[] {
  const keys = new Set([...Object.keys(base ?? {}), ...Object.keys(ours ?? {}), ...Object.keys(theirs ?? {})]);
  const text = (o: Obj | null, k: string) => canonicalJson(normalise({ [k]: o?.[k] ?? null }));
  const out: ConflictField[] = [];
  for (const field of [...keys].sort()) {
    if (ours !== null && theirs !== null && text(ours, field) === text(theirs, field)) continue;
    out.push({ field, base: base?.[field] ?? null, ours: ours?.[field] ?? null, theirs: theirs?.[field] ?? null });
  }
  return out;
}

/** The order of a list whose members came from three lists (see the plan, 7.5: "take remote order, append local-only"). */
function mergeOrder(b: string[], l: string[], r: string[], members: Set<string>): { order: string[]; both: boolean } {
  const only = (list: string[]) => list.filter((x) => members.has(x));
  const [bb, ll, rr] = [only(b), only(l), only(r)];
  const sameOrder = (x: string[], y: string[]) => {
    const common = new Set(x.filter((e) => y.includes(e)));
    const a = x.filter((e) => common.has(e));
    const c = y.filter((e) => common.has(e));
    return a.length === c.length && a.every((e, i) => e === c[i]);
  };
  const lMoved = !sameOrder(bb, ll);
  const rMoved = !sameOrder(bb, rr);
  const primary = lMoved && !rMoved ? ll : rr;
  const secondary = primary === ll ? rr : ll;
  const result = [...primary];
  const placedIds = new Set(result);
  const rest = [...members].filter((m) => !placedIds.has(m));
  // members known to neither list (cannot happen for well-formed input) go last
  for (const m of secondary) {
    if (placedIds.has(m)) continue;
    const at = secondary.indexOf(m);
    let anchor = -1;
    for (let i = at - 1; i >= 0; i--) {
      const p = result.indexOf(secondary[i] as string);
      if (p >= 0) {
        anchor = p;
        break;
      }
    }
    result.splice(anchor + 1, 0, m);
    placedIds.add(m);
  }
  for (const m of rest) if (!placedIds.has(m)) result.push(m);
  return { order: result, both: lMoved && rMoved && !sameOrder(ll, rr) };
}

export function mergeDocuments(baseDoc: Obj, oursDoc: Obj, theirsDoc: Obj): MergeResult {
  const B = treeOf(baseDoc);
  const L = treeOf(oursDoc);
  const R = treeOf(theirsDoc);
  const ids = [...new Set([...B.nodes.keys(), ...L.nodes.keys(), ...R.nodes.keys()])];
  const conflicts: Conflict[] = [];
  const fromOurs: Taken[] = [];
  const fromTheirs: Taken[] = [];
  const raw = (n: MNode | undefined): Obj | null => (n ? n.own : null);

  const edited = (s: MNode | undefined, b: MNode | undefined): boolean => Boolean(s && b && s.norm !== b.norm);
  const moved = (s: MNode | undefined, b: MNode | undefined): boolean => Boolean(s && b && !placed(s, b));

  /* 1. an element one side removed while the other side still holds it: a conflict when the other side touched it or
     anything below it, a plain removal when it did not */
  const held = new Map<string, string>(); // element id -> the conflict id that holds it
  const removalConflicts: Array<{ id: string; kind: "removed-ours" | "removed-theirs" }> = [];
  for (const id of B.nodes.keys()) {
    if (id === DOC) continue;
    for (const [name, remover, keeper] of [["removed-ours", L, R], ["removed-theirs", R, L]] as const) {
      if (remover.nodes.has(id) || !keeper.nodes.has(id)) continue;
      const subtree = new Set([id, ...descendants(B, id), ...[...keeper.nodes.keys()].filter((k) => below(keeper, k, id))]);
      const touched = [...subtree].some((f) => {
        const k = keeper.nodes.get(f);
        const b = B.nodes.get(f);
        return k !== undefined && (b === undefined || edited(k, b) || moved(k, b));
      });
      if (touched) removalConflicts.push({ id, kind: name });
    }
  }
  const conflictIds = new Set(removalConflicts.map((c) => c.id));
  for (const c of removalConflicts) {
    // a conflict inside another removal conflict belongs to the outer one
    if ([...B.nodes.keys()].some((a) => a !== c.id && conflictIds.has(a) && below(B, c.id, a))) continue;
    const keeper = c.kind === "removed-ours" ? R : L;
    const subtree = new Set([c.id, ...descendants(B, c.id), ...[...keeper.nodes.keys()].filter((k) => below(keeper, k, c.id))]);
    for (const f of subtree) held.set(f, c.id);
    const node = (tree: Tree) => raw(tree.nodes.get(c.id));
    conflicts.push({ id: c.id, kind: c.kind, type: (B.nodes.get(c.id) as MNode).type, base: node(B), ours: node(L), theirs: node(R), fields: fieldChanges(node(B), node(L), node(R)), held: [...subtree].filter((x) => x !== c.id).sort() });
  }

  /* 2. every other element: which side's data and place it takes */
  interface Decision {
    own: MNode;
    parent: string;
    slot: string;
  }
  const decided = new Map<string, Decision>();
  const take = (side: "ours" | "theirs", id: string, kind: Taken["kind"]) => (side === "ours" ? fromOurs : fromTheirs).push({ id, kind });
  for (const id of ids) {
    const b = B.nodes.get(id);
    const l = L.nodes.get(id);
    const r = R.nodes.get(id);
    if (id === DOC) {
      const own = edited(l, b) && !edited(r, b) ? l : !edited(l, b) && edited(r, b) ? r : l;
      decided.set(id, { own: own as MNode, parent: "", slot: "" });
      if (edited(l, b) && edited(r, b) && l?.norm !== r?.norm) conflicts.push({ id, kind: "edit-edit", type: "doc", base: raw(b), ours: raw(l), theirs: raw(r), fields: fieldChanges(raw(b), raw(l), raw(r)), held: [] });
      continue;
    }
    if (held.has(id)) {
      // held back by a removal conflict: ours decides until the author resolves it
      if (l) decided.set(id, { own: l, parent: l.parent, slot: l.slot });
      continue;
    }
    if (!b) {
      if (l && r) {
        if (l.norm === r.norm && placed(l, r)) decided.set(id, { own: l, parent: l.parent, slot: l.slot });
        else {
          decided.set(id, { own: l, parent: l.parent, slot: l.slot });
          conflicts.push({ id, kind: "add-add", type: l.type, base: null, ours: raw(l), theirs: raw(r), fields: fieldChanges(null, raw(l), raw(r)), held: [] });
        }
      } else if (l) {
        decided.set(id, { own: l, parent: l.parent, slot: l.slot });
        take("ours", id, "added");
      } else if (r) {
        decided.set(id, { own: r, parent: r.parent, slot: r.slot });
        take("theirs", id, "added");
      }
      continue;
    }
    if (!l && !r) continue;
    if (!l || !r) {
      // removed on one side and not touched on the other (the touched case is a conflict above)
      take(l ? "theirs" : "ours", id, "removed");
      continue;
    }
    const lc = edited(l, b);
    const rc = edited(r, b);
    const lm = moved(l, b);
    const rm = moved(r, b);
    let own = l;
    let conflictKind: ConflictKind | null = null;
    if (lc && rc) {
      if (l.norm !== r.norm) conflictKind = "edit-edit";
    } else if (rc) {
      own = r;
      take("theirs", id, "changed");
    } else if (lc) take("ours", id, "changed");
    let at: { parent: string; slot: string } = { parent: l.parent, slot: l.slot };
    if (lm && rm) {
      if (!placed(l, r)) conflictKind ??= "move-move";
    } else if (rm) {
      at = { parent: r.parent, slot: r.slot };
      take("theirs", id, "moved");
    } else if (lm) take("ours", id, "moved");
    decided.set(id, { own, parent: at.parent, slot: at.slot });
    if (conflictKind) conflicts.push({ id, kind: conflictKind, type: l.type, base: raw(b), ours: raw(l), theirs: raw(r), fields: fieldChanges(raw(b), raw(l), raw(r)), held: [] });
  }

  /* 3. members of every container, reachability from the root, one element per single slot */
  const dropped = new Set<string>();
  const members = new Map<string, Set<string>>();
  const conflicted = new Set(conflicts.map((c) => c.id));
  for (const [id, d] of decided) {
    if (id === DOC) continue;
    const key = kidsKey(d.parent, d.slot);
    members.set(key, (members.get(key) ?? new Set<string>()).add(id));
  }
  const out: Tree = { nodes: new Map(), kids: new Map() };
  const rootDecision = decided.get(DOC) as Decision;
  out.nodes.set(DOC, { ...(rootDecision.own as MNode), parent: "", slot: "" });
  const notes: MergeNote[] = [];
  const emit = (parent: string): void => {
    const pType = out.nodes.get(parent)?.type ?? "";
    for (const [slot, spec] of Object.entries(SLOTS[pType] ?? {})) {
      const set = members.get(kidsKey(parent, slot));
      if (!set || set.size === 0) continue;
      let list = [...set];
      if (!spec.many && list.length > 1) {
        const keep = list.find((x) => L.nodes.has(x) && decided.get(x)?.own === L.nodes.get(x)) ?? (list[0] as string);
        for (const x of list) {
          if (x === keep) continue;
          dropped.add(x);
          if (!conflicted.has(x)) {
            const n = (decided.get(x) as Decision).own;
            conflicts.push({ id: x, kind: "slot", type: n.type, base: raw(B.nodes.get(x)), ours: raw(L.nodes.get(x)), theirs: raw(R.nodes.get(x)), fields: [], held: [] });
            conflicted.add(x);
          }
        }
        list = [keep];
      }
      const merged = spec.many ? mergeOrder(kidsOf(B, parent, slot), kidsOf(L, parent, slot), kidsOf(R, parent, slot), set) : { order: list, both: false };
      if (merged.both) notes.push({ code: "ORDER_BOTH", id: parent, message: `Both sides reordered the ${slot} of ${parent}; the server's order was kept and your new elements were placed after their neighbours.` });
      const order = spec.many ? merged.order : list;
      for (const id of order) {
        if (dropped.has(id)) continue;
        const d = decided.get(id) as Decision;
        out.nodes.set(id, { ...d.own, parent, slot });
        out.kids.set(kidsKey(parent, slot), [...(out.kids.get(kidsKey(parent, slot)) ?? []), id]);
        emit(id);
      }
    }
  };
  emit(DOC);
  for (const [id, d] of decided) {
    if (id === DOC || out.nodes.has(id) || dropped.has(id)) continue;
    // an element whose parent did not make it into the merge: report it instead of losing it quietly
    const ancestor = [...below_chain(d.parent, decided)].find((a) => conflicted.has(a) || held.has(a));
    if (ancestor === undefined && !conflicted.has(id)) {
      conflicts.push({ id, kind: "orphan", type: d.own.type, base: raw(B.nodes.get(id)), ours: raw(L.nodes.get(id)), theirs: raw(R.nodes.get(id)), fields: [], held: [] });
      conflicted.add(id);
    }
  }
  return { doc: buildDocument(out), tree: out, conflicts: sortConflicts(conflicts, B, L, R), notes, fromOurs, fromTheirs };
}

function* below_chain(parent: string, decided: Map<string, { parent: string }>): Generator<string> {
  const seen = new Set<string>();
  for (let cur = parent; cur !== "" && !seen.has(cur); cur = decided.get(cur)?.parent ?? "") {
    seen.add(cur);
    yield cur;
  }
}

/** Conflicts in document order (ours first, then theirs, then base), so files and reports read top to bottom. */
function sortConflicts(list: Conflict[], B: Tree, L: Tree, R: Tree): Conflict[] {
  const position = (id: string): number => {
    for (const t of [L, R, B]) {
      if (!t.nodes.has(id)) continue;
      let n = 0;
      for (const k of t.nodes.keys()) {
        if (k === id) return n;
        n++;
      }
    }
    return Number.MAX_SAFE_INTEGER;
  };
  return [...list].sort((a, b) => position(a.id) - position(b.id));
}

/* ---------------------------------------------------------------- resolving a conflict in a merged tree */

export type Take = "local" | "remote";

export interface ResolveInput {
  conflict: Conflict;
  /** The folder as a tree (the merge result with the author's edits since). */
  tree: Tree;
  /** The server's version the folder is now based on. */
  remote: Tree;
}

/** Puts `id` into `slot` of `parent` after the element that precedes it in `reference` (or first/last when none is present). */
function placeAfterReference(t: Tree, id: string, parent: string, slot: string, reference: Tree): void {
  const key = kidsKey(parent, slot);
  const list = (t.kids.get(key) ?? []).filter((x) => x !== id);
  const refList = kidsOf(reference, parent, slot);
  const at = refList.indexOf(id);
  let anchor = -1;
  for (let i = at - 1; i >= 0; i--) {
    const p = list.indexOf(refList[i] as string);
    if (p >= 0) {
      anchor = p;
      break;
    }
  }
  list.splice(anchor + 1, 0, id);
  t.kids.set(key, list);
}

function detach(t: Tree, id: string): void {
  const n = t.nodes.get(id);
  if (!n) return;
  const key = kidsKey(n.parent, n.slot);
  t.kids.set(key, (t.kids.get(key) ?? []).filter((x) => x !== id));
}

function removeSubtree(t: Tree, id: string): void {
  for (const d of [id, ...descendants(t, id)]) {
    detach(t, d);
    t.nodes.delete(d);
  }
  for (const key of [...t.kids.keys()]) if (!t.nodes.has(key.split("\u0000")[0] as string)) t.kids.delete(key);
}

function copySubtree(from: Tree, to: Tree, id: string): void {
  const n = from.nodes.get(id) as MNode;
  if (to.nodes.has(id)) detach(to, id);
  to.nodes.set(id, { ...n });
  to.kids.set(kidsKey(n.parent, n.slot), [...(to.kids.get(kidsKey(n.parent, n.slot)) ?? []).filter((x) => x !== id), id]);
  for (const slot of Object.keys(SLOTS[n.type] ?? {})) {
    for (const k of kidsOf(from, id, slot)) copySubtree(from, to, k);
  }
}

export class ResolveError extends Error {}

/** `--take remote`: the element (and what it holds) becomes what the server has. `--take local` changes nothing. */
export function takeRemote({ conflict, tree, remote }: ResolveInput): Tree {
  const t: Tree = { nodes: new Map([...tree.nodes].map(([k, v]) => [k, { ...v }])), kids: new Map([...tree.kids].map(([k, v]) => [k, [...v]])) };
  const id = conflict.id;
  const theirs = remote.nodes.get(id);
  if (conflict.kind === "removed-theirs" || (conflict.kind === "orphan" && !theirs)) {
    removeSubtree(t, id);
    return t;
  }
  if (!theirs) throw new ResolveError(`The server's version has no element ${id}.`);
  if (!t.nodes.has(theirs.parent) && theirs.parent !== DOC) throw new ResolveError(`The element belongs under ${theirs.parent}, which is not in the folder (resolve or restore that element first).`);
  const slotSpec = SLOTS[t.nodes.get(theirs.parent)?.type ?? ""]?.[theirs.slot];
  if (slotSpec && !slotSpec.many) for (const other of kidsOf(t, theirs.parent, theirs.slot)) if (other !== id) removeSubtree(t, other);
  if (conflict.kind === "removed-ours" || conflict.kind === "slot" || !t.nodes.has(id)) {
    if (t.nodes.has(id)) removeSubtree(t, id);
    copySubtree(remote, t, id);
  } else {
    const keep = (t.nodes.get(id) as MNode).keys;
    detach(t, id);
    t.nodes.set(id, { ...theirs, keys: { ...keep, ...theirs.keys } });
  }
  placeAfterReference(t, id, theirs.parent, theirs.slot, remote);
  return t;
}

/** `--take file`: the element's own data becomes `own` (or the element is removed when `own` is null). */
export function takeFile({ conflict, tree, remote }: ResolveInput, own: Obj | null): Tree {
  const t: Tree = { nodes: new Map([...tree.nodes].map(([k, v]) => [k, { ...v }])), kids: new Map([...tree.kids].map(([k, v]) => [k, [...v]])) };
  const id = conflict.id;
  if (own === null) {
    removeSubtree(t, id);
    return t;
  }
  const withId = { ...own, id: own.id ?? id };
  if (String(withId.id) !== id && id !== "pages" && id !== DOC) throw new ResolveError(`The resolution changes the element's id (${String(withId.id)}); ids are the identity of an element and cannot change.`);
  const existing = t.nodes.get(id);
  if (existing) {
    t.nodes.set(id, { ...existing, own: withId, norm: normText(withId) });
    return t;
  }
  const theirs = remote.nodes.get(id);
  if (!theirs) throw new ResolveError(`Element ${id} is not in the folder and the server's version does not have it either, so there is no place to put it.`);
  if (!t.nodes.has(theirs.parent) && theirs.parent !== DOC) throw new ResolveError(`The element belongs under ${theirs.parent}, which is not in the folder.`);
  t.nodes.set(id, { ...theirs, own: withId, norm: normText(withId) });
  placeAfterReference(t, id, theirs.parent, theirs.slot, remote);
  return t;
}
