import { describe, expect, it } from "vitest";
import { folderFromBlueprint } from "../../src/course/from-blueprint.ts";
import { validateFiles } from "../../src/course/validate.ts";
import { summarize } from "../../src/course/issues.ts";
import type { AssetLookup, Obj } from "../../src/course/types.ts";
import { remoteDocument } from "./fake-course-as-code.ts";

/* What `course adopt` writes on the server: lessons that stand for an existing topic (assetRef, no blocks, an embed address),
   author-written blocks with no citations and a quiz of the four shapes. The folder must hold it and give it back unchanged. */

const id = (n: string): string => ("0".repeat(26) + n).slice(-26);
const noAssets: AssetLookup = { has: () => false, read: () => null } as unknown as AssetLookup;

function adopted(): Obj {
  const doc = remoteDocument();
  const objective = (n: string) => [{ id: id(`obj${n}`), text: "Work through the lesson", citations: [] }];
  const lesson = (n: string, extra: Obj): Obj => ({ id: id(`les${n}`), title: `Lesson ${n}`, minutes: 12, objectives: objective(n), citations: [], contentType: "richtext", status: "generated", blocks: [], quiz: null, flags: [], origin: "author", ...extra });
  (doc.modules as Obj[])[0] = {
    id: id("mod1"),
    title: "Basics",
    lessons: [
      lesson("a", { blocks: [{ id: id("blk1"), kind: "paragraph", markdown: "# Install\n\nInstall **kubectl**.", citations: [], objectiveIds: [id("obja")], origin: "author" }], quiz: { id: id("qz1"), passScore: 60, questions: [{ id: id("q1"), type: "truefalse", stem: "kubectl is a CLI.", options: [{ id: id("o1"), text: "True", correct: true }, { id: id("o2"), text: "False", correct: false }], explanation: "No explanation was written for this question.", citations: [], objectiveIds: [], origin: "author" }] } }),
      lesson("b", { contentType: "embed", embed: { url: "https://www.youtube.com/watch?v=abc", title: "Talk" }, assetRef: { entityType: "topic", entityId: 41 } }),
      lesson("c", { contentType: "h5p", assetRef: { entityType: "topic", entityId: 42 } }),
      lesson("d", { contentType: "liascript", assetRef: { entityType: "topic", entityId: 43 } }),
    ],
  };
  doc.sources = [];
  return doc;
}

describe("a course adopted from the LMS as a folder", () => {
  it("validates, and the folder gives the same document back", () => {
    const doc = adopted();
    const files = folderFromBlueprint(doc);
    const first = validateFiles(files, noAssets);
    const errors = first.issues.filter((i) => i.severity === "error");
    expect(errors, JSON.stringify(errors, null, 1)).toEqual([]);
    expect(summarize(first.issues).errors).toBe(0);
    const lessons = ((first.doc.modules as Obj[])[0] as Obj).lessons as Obj[];
    expect(lessons.map((l) => l.contentType)).toEqual(["richtext", "embed", "h5p", "liascript"]);
    expect(lessons[1]).toMatchObject({ assetRef: { entityType: "topic", entityId: 41 }, embed: { url: "https://www.youtube.com/watch?v=abc" }, blocks: [] });
    expect(lessons[0]).toMatchObject({ origin: "author", quiz: { passScore: 60 } });
    // the round trip: files -> document is the document that went in (the folder form fills defaults)
    const again = folderFromBlueprint(first.doc);
    expect([...again.keys()].sort()).toEqual([...files.keys()].sort());
    for (const [name, text] of files) expect(again.get(name), name).toBe(text);
  });
});
