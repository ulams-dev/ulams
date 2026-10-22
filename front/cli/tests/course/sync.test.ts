import { describe, expect, it } from "vitest";
import { readFolder } from "../../src/course/disk.ts";
import { folderFromBlueprint } from "../../src/course/from-blueprint.ts";
import { loadCourse } from "../../src/course/load.ts";
import { canonicalJson, changesSinceBase, compareDocuments, documentHash, flatten, folderForm, makeBase, parseBase, serializeBase } from "../../src/course/sync.ts";
import { keepAuthorNames, planMaterialise } from "../../src/course/sync-disk.ts";
import type { Obj } from "../../src/course/types.ts";
import { remoteDocument } from "./fake-course-as-code.ts";
import { documentFor } from "./gen.ts";

const clone = <T>(v: T): T => JSON.parse(JSON.stringify(v)) as T;
const lesson = (doc: Obj) => ((doc.modules as Obj[])[0] as Obj).lessons as Obj[];

describe("canonical form and hashes", () => {
  it("canonicalJson ignores key order and undefined", () => {
    expect(canonicalJson({ b: 1, a: { d: [2, { y: 1, x: undefined }], c: null } })).toBe('{"a":{"c":null,"d":[2,{"y":1}]},"b":1}');
  });

  it("a document hashes the same whatever the order of keys, `meta` and the server-owned sources", () => {
    const doc = remoteDocument();
    const shuffled = Object.fromEntries(Object.entries(clone(doc)).reverse());
    (shuffled.sources as unknown[]).push({ id: "x", title: "added by the server", fragmentCount: 3 });
    (lesson(shuffled)[0] as Obj).meta = { tool: "room" };
    expect(documentHash(shuffled)).toBe(documentHash(doc));
  });

  it("empty page slots and a null final test are not content", () => {
    const doc = remoteDocument();
    const withSlots = { ...clone(doc), pages: { landing: null, header: null } };
    const without = { ...clone(doc) };
    delete without.pages;
    expect(documentHash(withSlots)).toBe(documentHash(without));
    expect(documentHash({ ...clone(doc), finalTest: null })).not.toBe(documentHash(doc));
  });

  it("flatten names every element by id with the studio's labels and keeps children out of their parent's hash", () => {
    const elements = flatten(remoteDocument());
    const first = [...elements.values()].find((e) => e.type === "lesson") as { label: string; own: Obj; summary: string };
    expect(first.label).toBe("Lesson 1.1");
    expect(first.summary).toBe("Install kubectl");
    expect(first.own).not.toHaveProperty("blocks");
    expect(first.own).not.toHaveProperty("objectives");
    expect([...elements.values()].map((e) => e.type)).toEqual(expect.arrayContaining(["course", "module", "lesson", "block", "objective", "quiz", "question", "option"]));
  });
});

describe("the base snapshot", () => {
  const doc = remoteDocument();
  const base = makeBase(doc, { sessionId: "s", courseId: 12, versionId: "v1", versionNumber: 1 });

  it("round-trips through its file and holds hashes, never the course text", () => {
    const text = serializeBase(base);
    expect(text.endsWith("\n")).toBe(true);
    expect(text).not.toContain("kubectl is the command-line tool");
    const parsed = parseBase(text);
    expect("base" in parsed && parsed.base).toEqual(base);
    expect(Object.keys(base.elements).length).toBeGreaterThan(20);
  });

  it("refuses files it cannot trust", () => {
    expect(parseBase("{nope")).toMatchObject({ error: expect.stringContaining("not valid JSON") });
    expect(parseBase('{"format":9}')).toMatchObject({ error: expect.stringContaining("format 9") });
    expect(parseBase(JSON.stringify({ ...base, baseVersionId: "" }))).toMatchObject({ error: expect.stringContaining("baseVersionId") });
  });

  it("changesSinceBase reports added, changed and removed elements by hash, and order-only changes", () => {
    expect(changesSinceBase(base, doc)).toMatchObject({ modified: false, changes: [], orderChanged: false });

    const edited = clone(doc);
    const l = lesson(edited);
    (l[0] as Obj).title = "Install the CLI";
    const removed = (l[0] as Obj).blocks as Obj[];
    const gone = removed.pop() as Obj;
    (l[1] as Obj).blocks = [...((l[1] as Obj).blocks as Obj[]), { id: "01z00000000000000000000001", kind: "paragraph", markdown: "New.", origin: "author" }];
    const since = changesSinceBase(base, edited);
    expect(since.counts).toEqual({ added: 1, changed: 1, removed: 1 });
    expect(since.changes.find((c) => c.kind === "removed")).toMatchObject({ id: gone.id, type: "block", label: expect.stringContaining("block") });
    expect(since.changes.find((c) => c.kind === "changed")).toMatchObject({ type: "lesson", summary: "Install the CLI" });

    const reordered = clone(doc);
    (reordered.modules as Obj[]).reverse();
    expect(changesSinceBase(base, reordered)).toMatchObject({ modified: true, orderChanged: true });
  });

  it("compareDocuments shows the fields that changed", () => {
    const edited = clone(doc);
    (lesson(edited)[0] as Obj).minutes = 99;
    const changes = compareDocuments(doc, edited);
    expect(changes).toHaveLength(1);
    expect(changes[0]).toMatchObject({ kind: "changed", type: "lesson", fields: [{ field: "minutes", before: 10, after: 99 }] });
  });
});

describe("folder form", () => {
  it("a server document and the folder written from it hash the same, defaults and all", () => {
    const doc = remoteDocument();
    // what a builder document looks like on the server: defaults spelled out, empty lists, empty page slots
    const spelled = clone(doc);
    for (const l of lesson(spelled)) Object.assign(l as Obj, { contentType: "richtext", status: "generated", flags: [], citations: [] });
    (spelled as Obj).pages = { landing: null, header: null };
    const form = folderForm(spelled);
    expect(form.errors).toEqual([]);
    expect(documentHash(form.doc)).toBe(documentHash(spelled));
    expect(documentHash(folderForm(form.doc).doc)).toBe(documentHash(form.doc));
  });

  it.each([3, 17, 42, 99])("generated document %i survives the trip", (seed) => {
    const doc = documentFor(seed);
    const form = folderForm(doc);
    expect(form.errors).toEqual([]);
    expect(documentHash(form.doc)).toBe(documentHash(doc));
  });
});

describe("keeping the author's file names on a pull", () => {
  it("a lesson the author renamed keeps its name; the number prefix follows the new order", () => {
    const doc = remoteDocument();
    const theirs = folderFromBlueprint(doc);
    theirs.delete("ulams.yaml");
    const installPath = [...theirs.keys()].find((p) => p.endsWith("-install.md")) as string;
    const mine = new Map([...theirs].map(([p, t]) => [p === installPath ? installPath.replace("install", "install-kubectl-fast") : p, t]));

    const merged = keepAuthorNames(theirs, mine);
    expect([...merged.keys()]).toContain(installPath.replace("install", "install-kubectl-fast"));
    expect([...merged.keys()]).not.toContain(installPath);
    // the quiz next to it follows
    expect([...merged.keys()].some((p) => p.endsWith("install-kubectl-fast.quiz.yaml"))).toBe(true);
    // the content is untouched
    expect(merged.get(installPath.replace("install", "install-kubectl-fast"))).toBe(theirs.get(installPath));
  });

  it("a new first module shifts the prefix of the others but not their names", () => {
    const doc = remoteDocument();
    const before = folderFromBlueprint(doc);
    before.delete("ulams.yaml");
    const next = clone(doc);
    (next.modules as Obj[]).unshift({ id: "01z00000000000000000000009", key: "intro", title: "Intro", lessons: [] });
    const generated = folderFromBlueprint(next);
    generated.delete("ulams.yaml");
    const merged = keepAuthorNames(generated, before);
    const dirs = new Set([...merged.keys()].filter((p) => p.startsWith("modules/")).map((p) => p.split("/")[1]));
    expect([...dirs].sort()).toEqual(["01-intro", "02-basics", "03-workloads"]);
  });

  it("planMaterialise lists new, changed and removed managed files and leaves the author's own files alone", () => {
    const existing = new Map([
      ["course.yaml", "a"],
      ["modules/01-x/01-gone.md", "x"],
      ["modules/01-x/notes.txt", ""],
      ["modules/01-x/01-keep.md", "same"],
    ]);
    const next = new Map([
      ["course.yaml", "b"],
      ["modules/01-x/01-keep.md", "same"],
      ["modules/01-x/02-new.md", "n"],
    ]);
    expect(planMaterialise(existing, next)).toEqual({ written: ["modules/01-x/02-new.md"], updated: ["course.yaml"], removed: ["modules/01-x/01-gone.md"], unchanged: 1 });
  });
});

describe("the example folder itself", () => {
  it("loads into a document the sync model can hash", () => {
    const folder = readFolder(new URL("../../examples/kubernetes-101", import.meta.url).pathname);
    const loaded = loadCourse(folder.files, folder.assets);
    expect(documentHash(loaded.doc)).toMatch(/^[0-9a-f]{64}$/);
  });
});
