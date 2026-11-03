import { readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";
import { conflictFileName, parseElement, readConflict, renderConflict, renderElement, REMOVED } from "../../src/course/conflicts.ts";
import { buildDocument, DOC, mergeDocuments, takeFile, takeRemote, treeOf, type Conflict } from "../../src/course/merge.ts";
import { unifiedDiff } from "../../src/course/textdiff.ts";
import type { Obj } from "../../src/course/types.ts";
import { mulberry32 } from "./gen.ts";

/* The three-way merge, element by element (plan 7.5; one conflict per element, default 4). The fixture file is shared with
   the server (api/packages/course-builder/tests/Feature/ElementMergeTest.php runs the same cases), so the two
   implementations cannot drift. Below it: the properties the plan names (disjoint edits merge, same-element edits
   conflict, delete against edit, ordering and moves), checked on seeded random edits. */

const here = dirname(fileURLToPath(import.meta.url));
interface Fixture {
  name: string;
  description: string;
  base: Obj;
  ours: Obj;
  theirs: Obj;
  expected: { doc: Obj; conflicts: Array<{ id: string; kind: string; held: string[]; fields: string[] }>; notes: Array<{ code: string; id: string }>; fromOurs: unknown[]; fromTheirs: unknown[] };
}
const cases = JSON.parse(readFileSync(join(here, "../../../../api/packages/course-builder/tests/fixtures/merge/cases.json"), "utf8")) as Fixture[];

const clone = <T>(x: T): T => JSON.parse(JSON.stringify(x)) as T;
const lessonsOf = (doc: Obj, m = 0): Obj[] => ((doc.modules as Obj[])[m] as Obj).lessons as Obj[];
const blocksOf = (doc: Obj, l = 0): Obj[] => (lessonsOf(doc)[l] as Obj).blocks as Obj[];
const idsOf = (list: Obj[]): string[] => list.map((x) => String(x.id));
const block = (id: string, markdown: string): Obj => ({ id, kind: "paragraph", markdown, citations: ["frg_aaaaaaaaaaaa"] });

describe("shared merge cases", () => {
  it("has the cases", () => expect(cases.length).toBeGreaterThanOrEqual(30));
  for (const c of cases) {
    it(`${c.name}: ${c.description}`, () => {
      const r = mergeDocuments(c.base, c.ours, c.theirs);
      expect(r.doc).toEqual(c.expected.doc);
      expect(r.conflicts.map((x) => ({ id: x.id, kind: x.kind, held: x.held, fields: x.fields.map((f) => f.field) }))).toEqual(c.expected.conflicts);
      expect(r.notes.map((n) => ({ code: n.code, id: n.id }))).toEqual(c.expected.notes);
      expect(r.fromOurs).toEqual(c.expected.fromOurs);
      expect(r.fromTheirs).toEqual(c.expected.fromTheirs);
    });
  }
});

const base = (): Obj => (cases[0] as Fixture).base;

describe("what the merge table of plan 7.5 says", () => {
  const byName = (name: string): Fixture => cases.find((c) => c.name === name) as Fixture;
  const run = (name: string) => {
    const c = byName(name);
    return { c, r: mergeDocuments(c.base, c.ours, c.theirs) };
  };

  it("takes the side that changed an element", () => {
    const a = run("only ours edits a block");
    expect(a.r.conflicts).toEqual([]);
    expect(blocksOf(a.r.doc)[0]?.markdown).toBe("First block, edited here.");
    const b = run("only theirs edits a block");
    expect(blocksOf(b.r.doc)[1]?.markdown).toBe("Second block, edited there.");
  });

  it("merges disjoint edits and reports where each came from", () => {
    const { r } = run("disjoint edits merge");
    expect(r.conflicts).toEqual([]);
    expect(blocksOf(r.doc).map((b) => b.markdown)).toEqual(["Ours edits the first block.", "Second block.", "Theirs edits the third block."]);
    expect(r.fromOurs).toEqual([{ id: "b1", kind: "changed" }]);
    expect(r.fromTheirs).toEqual([{ id: "b3", kind: "changed" }]);
  });

  it("makes one conflict per element, even when different fields changed", () => {
    const { r } = run("different fields of one element");
    expect(r.conflicts).toHaveLength(1);
    expect(r.conflicts[0]).toMatchObject({ id: "l1", kind: "edit-edit" });
    expect(r.conflicts[0]?.fields.map((f) => f.field)).toEqual(["minutes", "title"]);
    // ours is kept until the author decides
    expect(lessonsOf(r.doc)[0]).toMatchObject({ title: "Install it", minutes: 10 });
  });

  it("holds a removal back when the other side changed the element or anything below it", () => {
    const ours = run("ours deletes what theirs edited").r;
    expect(ours.conflicts[0]).toMatchObject({ id: "b2", kind: "removed-ours" });
    expect(idsOf(blocksOf(ours.doc))).toEqual(["b1", "b3"]);
    const theirs = run("ours edits what theirs deleted").r;
    expect(theirs.conflicts[0]).toMatchObject({ id: "b2", kind: "removed-theirs" });
    expect(blocksOf(theirs.doc)[1]?.markdown).toBe("Ours improved it.");
    const deep = run("theirs deletes a lesson ours edited a block of").r;
    expect(deep.conflicts).toHaveLength(1);
    expect(deep.conflicts[0]).toMatchObject({ id: "l2", kind: "removed-theirs", held: ["b4", "lo2"] });
    expect(blocksOf(deep.doc, 1)[0]?.markdown).toBe("Pods run containers, always.");
    const added = run("a removal with an addition below it").r;
    expect(added.conflicts[0]).toMatchObject({ id: "l2", kind: "removed-theirs" });
    expect(idsOf(blocksOf(added.doc, 1))).toEqual(["b4", "b6"]);
  });

  it("removes what one side removed and the other left alone", () => {
    expect(idsOf(blocksOf(run("ours deletes an untouched element").r.doc))).toEqual(["b1", "b2"]);
    expect(idsOf(blocksOf(run("theirs deletes an untouched element").r.doc))).toEqual(["b2", "b3"]);
    expect(run("both delete the same element").r.conflicts).toEqual([]);
  });

  it("orders: the reordering side wins, the server's order when both did, new elements stay beside their neighbour", () => {
    expect(idsOf(blocksOf(run("only theirs reorders").r.doc))).toEqual(["b3", "b2", "b1"]);
    expect(idsOf(blocksOf(run("ours reorders, theirs edits").r.doc))).toEqual(["b3", "b2", "b1"]);
    const both = run("both reorder differently");
    expect(idsOf(blocksOf(both.r.doc))).toEqual(["b3", "b2", "b1"]);
    expect(both.r.notes).toEqual([expect.objectContaining({ code: "ORDER_BOTH", id: "l1" })]);
    expect(idsOf(blocksOf(run("both add elements at different places").r.doc))).toEqual(["b1", "n1", "b2", "b3", "n2"]);
  });

  it("moves an element one side moved, and conflicts when both moved it apart", () => {
    const ours = run("ours moves a lesson to another module").r;
    expect(idsOf(lessonsOf(ours.doc, 1))).toEqual(["l2", "l3"]);
    const both = run("both move to different places").r;
    expect(both.conflicts[0]).toMatchObject({ id: "l2", kind: "move-move" });
    const split = run("ours renames, theirs moves").r;
    expect(split.conflicts).toEqual([]);
    expect(lessonsOf(split.doc, 1).find((l) => l.id === "l2")).toMatchObject({ title: "Pods, renamed" });
  });

  it("keeps one element in a single slot and reports the other", () => {
    const { r } = run("both replace the interaction");
    expect(r.conflicts).toEqual([expect.objectContaining({ id: "i3", kind: "slot" })]);
    expect((lessonsOf(r.doc)[0] as Obj).interaction).toMatchObject({ id: "i2" });
  });
});

describe("properties on seeded random edits", () => {
  /** Edits `count` distinct blocks' text across the base document, returning the ids touched. */
  function editSome(doc: Obj, rnd: () => number, tag: string, avoid: Set<string>): Set<string> {
    const all = lessonsOf(doc)
      .concat(lessonsOf(doc, 1))
      .flatMap((l) => l.blocks as Obj[]);
    const touched = new Set<string>();
    for (const b of all) {
      if (avoid.has(String(b.id)) || rnd() < 0.5) continue;
      b.markdown = `${String(b.markdown)} (${tag} ${Math.floor(rnd() * 1000)})`;
      touched.add(String(b.id));
    }
    return touched;
  }
  const textOf = (doc: Obj): Record<string, string> => Object.fromEntries([...lessonsOf(doc), ...lessonsOf(doc, 1)].flatMap((l) => (l.blocks as Obj[]).map((b) => [String(b.id), String(b.markdown)])));

  it("merge(B, B, B) is B, and a side that changed nothing yields the other side, for 200 random documents", () => {
    for (let seed = 1; seed <= 200; seed++) {
      const rnd = mulberry32(seed);
      const b = clone(base());
      const x = clone(b);
      editSome(x, rnd, "x", new Set());
      expect(mergeDocuments(b, b, b).doc).toEqual(b);
      expect(mergeDocuments(b, x, b).doc).toEqual(x);
      expect(mergeDocuments(b, b, x).doc).toEqual(x);
      expect(mergeDocuments(b, x, x).doc).toEqual(x);
      expect(mergeDocuments(b, x, x).conflicts).toEqual([]);
    }
  });

  it("disjoint edits always merge, both sides' texts are kept, and the merge does not depend on which side is which", () => {
    for (let seed = 1; seed <= 200; seed++) {
      const rnd = mulberry32(seed * 7);
      const b = clone(base());
      const ours = clone(b);
      const theirs = clone(b);
      const mine = editSome(ours, rnd, "ours", new Set());
      const yours = editSome(theirs, rnd, "theirs", mine);
      const r = mergeDocuments(b, ours, theirs);
      expect(r.conflicts).toEqual([]);
      const text = textOf(r.doc);
      for (const id of mine) expect(text[id]).toBe(textOf(ours)[id]);
      for (const id of yours) expect(text[id]).toBe(textOf(theirs)[id]);
      expect(mergeDocuments(b, theirs, ours).doc).toEqual(r.doc);
    }
  });

  it("editing the same element on both sides is a conflict for exactly those elements, and ours is kept", () => {
    for (let seed = 1; seed <= 200; seed++) {
      const rnd = mulberry32(seed * 13);
      const b = clone(base());
      const ours = clone(b);
      const theirs = clone(b);
      const mine = editSome(ours, rnd, "ours", new Set());
      editSome(theirs, rnd, "theirs", new Set());
      const clash = new Set([...mine].filter((id) => textOf(theirs)[id] !== textOf(b)[id]));
      const r = mergeDocuments(b, ours, theirs);
      expect(new Set(r.conflicts.map((c) => c.id))).toEqual(clash);
      for (const id of clash) expect(textOf(r.doc)[id]).toBe(textOf(ours)[id]);
    }
  });

  it("an addition and an unrelated removal on opposite sides both land, in any lesson, in either direction", () => {
    for (let seed = 1; seed <= 100; seed++) {
      const rnd = mulberry32(seed * 31);
      const b = clone(base());
      const ours = clone(b);
      const theirs = clone(b);
      const at = Math.floor(rnd() * 3);
      blocksOf(ours, 0).splice(at, 0, block("n1", "added by ours"));
      blocksOf(theirs, 1).pop();
      const r = mergeDocuments(b, ours, theirs);
      expect(r.conflicts).toEqual([]);
      expect(idsOf(blocksOf(r.doc, 0))).toEqual(idsOf(blocksOf(ours, 0)));
      expect(idsOf(blocksOf(r.doc, 1))).toEqual([]);
      expect(mergeDocuments(b, theirs, ours).doc).toEqual(r.doc);
    }
  });

  it("removing an element against an edit of it is a conflict whichever side does which", () => {
    for (const id of ["b1", "b2", "b3"]) {
      const b = clone(base());
      const del = clone(b);
      const edit = clone(b);
      blocksOf(del).splice(blocksOf(del).findIndex((x) => x.id === id), 1);
      (blocksOf(edit).find((x) => x.id === id) as Obj).markdown = "changed";
      expect(mergeDocuments(b, del, edit).conflicts.map((c) => [c.id, c.kind])).toEqual([[id, "removed-ours"]]);
      expect(mergeDocuments(b, edit, del).conflicts.map((c) => [c.id, c.kind])).toEqual([[id, "removed-theirs"]]);
    }
  });
});

describe("resolving a conflict in the merged tree", () => {
  const scenario = (name: string) => {
    const c = cases.find((x) => x.name === name) as Fixture;
    const r = mergeDocuments(c.base, c.ours, c.theirs);
    return { c, r, conflict: r.conflicts[0] as Conflict, remote: treeOf(c.theirs) };
  };
  const doc = (t: ReturnType<typeof treeOf>): Obj => buildDocument(t);

  it("--take remote replaces the element with the server's", () => {
    const { r, conflict, remote } = scenario("same element edited differently");
    const after = doc(takeRemote({ conflict, tree: r.tree, remote }));
    expect(blocksOf(after)[1]?.markdown).toBe("Theirs wording.");
  });

  it("--take remote on a removal that ours made brings the element back where the server has it", () => {
    const { r, conflict, remote } = scenario("ours deletes what theirs edited");
    const after = doc(takeRemote({ conflict, tree: r.tree, remote }));
    expect(idsOf(blocksOf(after))).toEqual(["b1", "b2", "b3"]);
    expect(blocksOf(after)[1]?.markdown).toBe("Theirs improved it.");
  });

  it("--take remote on a removal that theirs made removes the element and everything below it", () => {
    const { r, conflict, remote } = scenario("theirs deletes a lesson ours edited a block of");
    const after = doc(takeRemote({ conflict, tree: r.tree, remote }));
    expect(idsOf(lessonsOf(after))).toEqual(["l1"]);
    expect(JSON.stringify(after)).not.toContain("b4");
  });

  it("--take remote brings a whole removed subtree back", () => {
    const { r, conflict, remote } = scenario("ours deletes a lesson theirs edited a block of");
    const after = doc(takeRemote({ conflict, tree: r.tree, remote }));
    expect(idsOf(lessonsOf(after))).toEqual(["l1", "l2"]);
    expect(blocksOf(after, 1)[0]?.markdown).toBe("Pods run containers, always.");
    expect((lessonsOf(after)[1] as Obj).objectives).toHaveLength(1);
  });

  it("--take remote on a move puts the element where the server has it", () => {
    const { r, conflict, remote } = scenario("both move to different places");
    const after = doc(takeRemote({ conflict, tree: r.tree, remote }));
    expect(idsOf((after.modules as Obj[]).flatMap((m) => m.lessons as Obj[]))).toContain("l2");
    expect(idsOf(lessonsOf(after, 2))).toEqual(["l2"]);
  });

  it("--take remote on a single slot replaces the occupant", () => {
    const { r, conflict, remote } = scenario("both replace the interaction");
    const after = doc(takeRemote({ conflict, tree: r.tree, remote }));
    expect((lessonsOf(after)[0] as Obj).interaction).toMatchObject({ id: "i3" });
  });

  it("--take file writes the edited element, or removes it", () => {
    const { r, conflict, remote } = scenario("same element edited differently");
    const merged = { conflict, tree: r.tree, remote };
    const edited = doc(takeFile(merged, { id: "b2", kind: "paragraph", markdown: "A bit of both.", citations: ["frg_aaaaaaaaaaaa"] }));
    expect(blocksOf(edited)[1]?.markdown).toBe("A bit of both.");
    expect(idsOf(blocksOf(doc(takeFile(merged, null))))).toEqual(["b1", "b3"]);
    expect(() => takeFile(merged, { id: "other", kind: "paragraph", markdown: "x", citations: [] })).toThrow(/id/);
  });

  it("resolving every conflict with --take remote gives the server's version of every conflicted element", () => {
    for (const c of cases.filter((x) => x.expected.conflicts.length > 0)) {
      const r = mergeDocuments(c.base, c.ours, c.theirs);
      let tree = r.tree;
      const remote = treeOf(c.theirs);
      for (const conflict of r.conflicts) tree = takeRemote({ conflict, tree, remote });
      const after = buildDocument(tree);
      const now = treeOf(after);
      for (const conflict of r.conflicts) {
        const theirs = remote.nodes.get(conflict.id);
        const mine = now.nodes.get(conflict.id);
        if (theirs) expect(mine?.norm, `${c.name}: ${conflict.id}`).toBe(theirs.norm);
        else expect(mine, `${c.name}: ${conflict.id}`).toBeUndefined();
      }
    }
  });
});

describe("conflict files", () => {
  const BID = "0b2".padEnd(26, "0");
  const sample = () => {
    const own = (markdown: string): Obj => ({ id: BID, kind: "paragraph", markdown, citations: ["frg_aaaaaaaaaaaa"] });
    const conflict: Conflict = { id: BID, kind: "edit-edit", type: "block", base: own("Second block."), ours: own("Ours wording."), theirs: own("Theirs wording."), fields: [{ field: "markdown", base: "Second block.", ours: "Ours wording.", theirs: "Theirs wording." }], held: [] };
    const meta = { element: BID, type: "block", kind: conflict.kind, base: { versionId: "v1", versionNumber: 1 }, remote: { versionId: "v3", versionNumber: 3 } };
    return { conflict, meta, text: renderConflict(conflict, meta, "Lesson 1.1 > block 2") };
  };

  it("shows base, yours, the server's, a diff and a resolution the author can edit", () => {
    const { text } = sample();
    expect(text).toContain("# Conflict: Lesson 1.1 > block 2");
    expect(text).toContain("## Base (version 1)");
    expect(text).toContain("## Yours (folder)");
    expect(text).toContain("## Server (version 3)");
    expect(text).toContain("-Ours wording.");
    expect(text).toContain("+Theirs wording.");
    expect(text).toContain(`ulams course resolve ${BID} --take file`);
    expect(text).toContain(`:::paragraph{#${BID} cite="frg_aaaaaaaaaaaa"}\nOurs wording.\n:::`);
  });

  it("reads back the meta and the resolution, and a block survives the trip", () => {
    const { text } = sample();
    const read = readConflict(text);
    expect(read.meta).toMatchObject({ element: BID, type: "block", kind: "edit-edit", remote: { versionId: "v3" } });
    expect(parseElement("block", BID, read.resolution)).toEqual({ id: BID, kind: "paragraph", markdown: "Ours wording.", citations: ["frg_aaaaaaaaaaaa"], objectiveIds: [] });
    const edited = text.replace(/(## Resolution[\s\S]*?\n)Ours wording\./, "$1Ours and theirs, combined.");
    expect(parseElement("block", BID, readConflict(edited).resolution)).toMatchObject({ markdown: "Ours and theirs, combined." });
  });

  it("handles Markdown that looks like the file's own structure", () => {
    const own = { id: BID, kind: "code", markdown: "## Resolution\n\n~~~~\nnot a fence of ours\n~~~~\n```bash\nkubectl get pods\n```", citations: [] };
    const c: Conflict = { id: BID, kind: "edit-edit", type: "block", base: own, ours: own, theirs: { ...own, markdown: "other" }, fields: [], held: [] };
    const text = renderConflict(c, { element: BID, type: "block", kind: "edit-edit", base: { versionId: "v1", versionNumber: 1 }, remote: { versionId: "v2", versionNumber: 2 } }, "Block");
    expect(parseElement("block", BID, readConflict(text).resolution)).toMatchObject({ kind: "code", markdown: own.markdown });
  });

  it("renders other elements as YAML and a removal as a word", () => {
    const lesson = { id: "l1", title: "Install: now", minutes: 10 };
    const text = renderElement("lesson", "l1", lesson);
    expect(text).toBe("id: l1\ntitle: \"Install: now\"\nminutes: 10");
    expect(parseElement("lesson", "l1", text)).toEqual(lesson);
    expect(renderElement("lesson", "l1", null)).toBe(REMOVED);
    expect(parseElement("lesson", "l1", REMOVED)).toBeNull();
    expect(() => parseElement("lesson", "l1", "just words")).toThrow(/YAML/);
    expect(() => parseElement("block", "b1", "not a directive")).toThrow(/directive/);
  });

  it("names the root element's file without a dollar sign", () => {
    expect(conflictFileName(DOC)).toBe("_root");
    expect(conflictFileName("b2")).toBe("b2");
  });

  it("refuses a damaged file with a sentence", () => {
    expect(() => readConflict("no front matter")).toThrow(/front matter/);
    expect(() => readConflict("---\nelement: b2\ntype: block\nkind: edit-edit\n---\n\n# x\n")).toThrow(/Resolution/);
  });
});

describe("unified diff", () => {
  it("is empty for equal texts and shows changed lines with context", () => {
    expect(unifiedDiff("a\nb", "a\nb", "x", "y")).toBe("");
    const d = unifiedDiff("one\ntwo\nthree\nfour\nfive\nsix\nseven\neight\nnine\nten", "one\ntwo\nthree\nfour\nFIVE\nsix\nseven\neight\nnine\nten", "was", "now");
    expect(d.split("\n").slice(0, 3)).toEqual(["--- was", "+++ now", "@@ -2,7 +2,7 @@"]);
    expect(d).toContain("-five\n+FIVE");
    expect(d).not.toContain(" one\n");
  });
});
