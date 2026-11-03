import { describe, expect, it } from "vitest";
import type { BlueprintLesson } from "@ulams/sdk";
import { formatLine, h5pSize, lessonExtras } from "../../src/studio/formats.ts";

const base = { id: "l", title: "T", minutes: 5, objectives: [], citations: [], status: "generated", blocks: [], quiz: null, flags: [] };
const cite = (ids: string[]) => ids.map((fragmentId) => ({ fragmentId, label: fragmentId }));

describe("lesson formats in the preview", () => {
  it("rich text has no format line and no extras", () => {
    expect(lessonExtras({ ...base, contentType: "richtext" } as unknown as BlueprintLesson, cite)).toEqual({});
  });

  it("LiaScript counts its self-checks and lists them as editable", () => {
    const lesson = { ...base, contentType: "liascript", selfChecks: [{ id: "q1", type: "single", stem: "Why?", options: [], explanation: "", citations: ["frg_a"], objectiveIds: [] }] } as unknown as BlueprintLesson;
    expect(formatLine(lesson)).toBe("LiaScript · 1 self-check");
    expect(lessonExtras(lesson, cite).extras).toEqual([{ id: "q1", kind: "check", label: "Self-check 1", text: "Why?", editable: true, citations: [{ fragmentId: "frg_a", label: "frg_a" }] }]);
  });

  it("H5P names the library and its size", () => {
    const interaction = { id: "i1", kind: "h5p", library: "H5P.Blanks", title: "Practice", data: { instruction: "Fill in.", items: [{ blanks: [{}, {}] }, { blanks: [{}] }] }, citations: ["frg_a"], objectiveIds: [] };
    expect(h5pSize(interaction as Parameters<typeof h5pSize>[0])).toBe("3 blanks");
    const lesson = { ...base, contentType: "h5p", interaction } as unknown as BlueprintLesson;
    expect(formatLine(lesson)).toBe("H5P · Fill in the blanks · 3 blanks");
    expect(lessonExtras(lesson, cite).extras?.[0]).toMatchObject({ kind: "activity", text: "Fill in.", editable: false });
  });
});
