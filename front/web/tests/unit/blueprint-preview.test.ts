import { describe, expect, it, vi } from "vitest";
import { ApiError, type Blueprint, type BlueprintVersion } from "@ulams/sdk";
import { buildPreview, elementCitations, elementIds, findElement, loadSessionPreview, previewLinks } from "../../src/lib/blueprint-preview.ts";
import { elementKey, planFramePatch } from "../../src/studio/frame-patch.ts";

const F1 = "frg_aaaaaaaaaaaa";
const F2 = "frg_bbbbbbbbbbbb";
const q = (id: string, citations: string[]) => ({
  id,
  type: "single" as const,
  stem: "Stem",
  options: [{ id: `${id}o1`, text: "A", correct: true }, { id: `${id}o2`, text: "B", correct: false }],
  explanation: "Because",
  citations,
  objectiveIds: [],
});
const lesson = (n: number, withQuiz: boolean) => ({
  id: `les${n}`,
  title: `Lesson ${n}`,
  minutes: 5,
  objectives: [],
  citations: [],
  contentType: "richtext" as const,
  status: "generated" as const,
  blocks: [{ id: `blk${n}a`, kind: "paragraph" as const, markdown: "One", citations: [F1], objectiveIds: [] }, { id: `blk${n}b`, kind: "paragraph" as const, markdown: "Two", citations: [], objectiveIds: [] }],
  quiz: withQuiz ? { id: `quiz${n}`, questions: [q(`q${n}a`, [F2])] } : null,
  flags: [],
});
const doc: Blueprint = {
  schemaVersion: 1,
  sources: [],
  course: { id: "course", title: "Brewing", subtitle: "Sub", description: "Desc", language: "en", objectives: [] },
  modules: [{ id: "mod1", title: "Basics", lessons: [lesson(1, true), lesson(2, false)] }],
  finalTest: { id: "final", questions: [q("fq1", [])] },
  pages: { landing: null, header: null },
};

describe("buildPreview", () => {
  const preview = buildPreview(doc, { level: "beginner" });

  it("maps modules, lessons, quizzes and the final test to learner pages", () => {
    expect(preview.pages.map((p) => [p.key, p.kind])).toEqual([
      ["les1", "lesson"],
      ["quiz1", "quiz"],
      ["les2", "lesson"],
      ["final", "final"],
    ]);
    expect(preview.course.lessons).toHaveLength(2);
    expect(preview.course.level).toBe("beginner");
    expect(preview.course.status).toBe("draft");
  });

  it("gives every page its own topic id and finds it both ways", () => {
    const ids = preview.pages.map((p) => p.topicId);
    expect(new Set(ids).size).toBe(ids.length);
    expect(preview.byKey.get("quiz1")?.questions).toHaveLength(1);
    expect(preview.byTopic.get(ids[2]!)?.key).toBe("les2");
  });

  it("links the program to the studio preview pages, never to the learner routes", () => {
    const links = previewLinks("01m4fj5zsgfxhzh3cantwevncw", preview);
    expect(links.topic(1, preview.pages[1]!.topicId)).toBe("/studio/s/01m4fj5zsgfxhzh3cantwevncw/preview/quiz1");
    expect(links.learn(1)).toBe("/studio/s/01m4fj5zsgfxhzh3cantwevncw/preview/les1");
  });
});

describe("findElement", () => {
  it("labels elements like the studio does and says what the chat can change", () => {
    expect(findElement(doc, "course")).toMatchObject({ type: "course", label: "Course", editable: true });
    expect(findElement(doc, "mod1")).toMatchObject({ type: "module", label: "Module 1" });
    expect(findElement(doc, "les2")).toMatchObject({ type: "lesson", label: "Lesson 1.2" });
    expect(findElement(doc, "blk1b")).toMatchObject({ type: "block", label: "Lesson 1.1 › block 2" });
    expect(findElement(doc, "q1a")).toMatchObject({ type: "question", label: "Lesson 1.1 › Q1", editable: true });
    expect(findElement(doc, "fq1")).toMatchObject({ type: "question", label: "Final test › Q1" });
    expect(findElement(doc, "quiz1")).toMatchObject({ type: "quiz", editable: false });
    expect(findElement(doc, "nope")).toBeNull();
  });
  it("covers every element id the preview marks", () => {
    for (const id of elementIds(doc)) expect(findElement(doc, id), id).not.toBeNull();
  });
});

describe("elementCitations", () => {
  it("collects the cited fragments of an element, each once", () => {
    expect(elementCitations(findElement(doc, "les1")!.node)).toEqual([F1, F2]);
    expect(elementCitations(findElement(doc, "blk1a")!.node)).toEqual([F1]);
    expect(elementCitations(findElement(doc, "q1a")!.node)).toEqual([F2]);
    expect(elementCitations({ a: [F1, F1, "frg_short"], b: { c: F2 } })).toEqual([F1, F2]);
    expect(elementCitations(findElement(doc, "fq1")!.node)).toEqual([]);
  });
});

describe("loadSessionPreview", () => {
  const version = (kind: BlueprintVersion["kind"]) => ({ id: "v1", kind, document: doc }) as unknown as BlueprintVersion;
  const client = (get: () => Promise<unknown>, versionKind: BlueprintVersion["kind"] = "content") =>
    ({ sessions: { get }, versions: { get: vi.fn(async () => version(versionKind)) } }) as never;
  const state = { session: { currentVersionId: "v1" } };

  it("returns the state and the current version", async () => {
    const result = await loadSessionPreview(client(async () => state), "s");
    expect(result?.version?.id).toBe("v1");
  });
  it("has no version to preview while only the outline exists", async () => {
    expect((await loadSessionPreview(client(async () => state, "outline"), "s"))?.version).toBeNull();
    expect((await loadSessionPreview(client(async () => ({ session: { currentVersionId: null } })), "s"))?.version).toBeNull();
  });
  it("answers null when the API does not show the session to this author (other owner, other tenant, expired)", async () => {
    for (const status of [401, 403, 404]) {
      expect(await loadSessionPreview(client(async () => { throw new ApiError(status, "/x", null); }), "s")).toBeNull();
    }
  });
  it("lets server errors through", async () => {
    await expect(loadSessionPreview(client(async () => { throw new ApiError(500, "/x", null); }), "s")).rejects.toThrow();
  });
});

describe("planFramePatch", () => {
  const els = (...pairs: Array<[string, string]>) => pairs.map(([key, html]) => ({ key: elementKey(key, null, 0), html }));

  it("reports only the elements whose markup changed", () => {
    const plan = planFramePatch(els(["a", "1"], ["b", "2"]), els(["a", "1"], ["b", "3"]));
    expect(plan).toEqual({ structural: false, changed: [elementKey("b", null, 0)] });
  });
  it("asks for a reload when elements appear or disappear", () => {
    expect(planFramePatch(els(["a", "1"]), els(["a", "1"], ["b", "2"])).structural).toBe(true);
    expect(planFramePatch(els(["a", "1"], ["b", "2"]), els(["a", "1"], ["c", "2"])).structural).toBe(true);
  });
  it("tells the parts of one id apart", () => {
    expect(elementKey("course", "header", 0)).not.toBe(elementKey("course", "description", 0));
    expect(elementKey("course", undefined, 1)).toBe("course|#1");
  });
  it("changes nothing when nothing changed", () => {
    expect(planFramePatch(els(["a", "1"]), els(["a", "1"]))).toEqual({ structural: false, changed: [] });
  });
});
