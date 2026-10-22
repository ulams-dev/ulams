import type { CourseCompare, ElementChange, ElementRef } from "@ulams/sdk";

/** A compare payload with every kind of change the screen shows: added, removed, changed (text, number, citations), moved, reordered, a question whose answer key changed, a reworded question and a very long field. */
const ref = (id: string, type: string, label: string, title: string | null = null): ElementRef => ({ id, type, label, title });
export const M1 = ref("mod_1", "module", "Module 1", "Water and brewing");
export const M2 = ref("mod_2", "module", "Module 2", "Grinding");
export const L1 = ref("les_1", "lesson", "Lesson 1.1", "Brewing basics");
export const QZ = ref("qz_1", "quiz", "Lesson 1.1 › quiz");
export const Q1 = ref("q_1", "question", "Lesson 1.1 › Q1");

const field = (name: string, label: string, before: ElementChange["fields"][number]["before"], after: ElementChange["fields"][number]["after"], extra: Partial<ElementChange["fields"][number]> = {}) => ({ field: name, label, before, after, ...extra });
export const change = (c: Partial<ElementChange> & Pick<ElementChange, "id" | "type" | "label" | "kind">): ElementChange => ({
  title: null, path: [], moved: null, reordered: null, citations: null, description: "", fields: [], ...c, ancestors: (c.path ?? []).map((p) => p.id),
});

export const changes: ElementChange[] = [
  change({ id: "les_3", type: "lesson", label: "Lesson 1.3", title: "Choosing a kettle", kind: "added", path: [M1], fields: [field("title", "Title", null, "Choosing a kettle"), field("minutes", "Minutes", null, 6), field("citations", "Citations", null, "frg_added000001")], citations: { added: ["frg_added000001"], removed: [], repointed: [] } }),
  change({ id: "blk_old", type: "block", label: "Lesson 1.1 › block 2", kind: "removed", path: [M1, L1], fields: [field("markdown", "Content", "Use a cheap blade grinder.", null)] }),
  change({ id: "les_1", type: "lesson", label: "Lesson 1.1", title: "Brewing basics", kind: "changed", path: [M1], fields: [field("title", "Title", "Brewing basic", "Brewing basics"), field("minutes", "Minutes", 5, 6), field("citations", "Citations", "a, b", "c")], citations: { added: ["frg_added000002"], removed: ["frg_removed00001"], repointed: [{ from: "frg_old00000001", to: "frg_new000000001" }] } }),
  change({ id: "blk_1", type: "block", label: "Lesson 1.1 › block 1", kind: "changed", path: [M1, L1], fields: [field("markdown", "Content", "Heat the water to ninety degrees.", "Warm the water to ninety-five degrees now.")] }),
  change({ id: "blk_big", type: "block", label: "Lesson 1.1 › block 3", kind: "changed", path: [M1, L1], fields: [field("markdown", "Content", null, null, { truncated: true, lineDiff: { removed: ["old line"], added: ["new line", "another"], omitted: 12 } })] }),
  change({ id: "les_2", type: "lesson", label: "Lesson 2.1", title: "Grind size", kind: "moved", path: [M2], moved: { from: { id: "mod_1", label: "Module 1" }, to: { id: "mod_2", label: "Module 2" } } }),
  change({ id: "les_5", type: "lesson", label: "Lesson 2.3", title: "Burr or blade", kind: "reordered", path: [M2], reordered: { parentId: "mod_2", from: 3, to: 1 } }),
  change({ id: "o_a", type: "option", label: "Lesson 1.1 › Q1 › option A", kind: "changed", path: [M1, L1, QZ, Q1], fields: [field("correct", "Correct answer", true, false)] }),
  change({ id: "o_b", type: "option", label: "Lesson 1.1 › Q1 › option B", kind: "changed", path: [M1, L1, QZ, Q1], fields: [field("correct", "Correct answer", false, true)] }),
  change({ id: "q_2", type: "question", label: "Lesson 1.1 › Q2", kind: "changed", path: [M1, L1, QZ], fields: [field("stem", "Question", "Which temperature?", "Which water temperature?")] }),
];

const zero = { added: 0, changed: 0, removed: 0, moved: 0, reordered: 0 };

export const payload = (over: Partial<CourseCompare> = {}): CourseCompare => ({
  diffVersion: 1,
  courseId: 5,
  sessionId: "01s0000000000000000000abcd",
  from: { versionId: "01v00000000000000000000002", versionNumber: 2, kind: "author", status: "approved", variantId: null, releaseId: "01r00000000000000000000001", releaseNumber: 1, releasedAt: "2026-10-18T09:00:00+00:00", createdAt: "2026-10-18T09:00:00+00:00" },
  to: { versionId: "01v00000000000000000000006", versionNumber: 6, kind: "author", status: "approved", variantId: null, releaseId: "01r00000000000000000000003", releaseNumber: 3, releasedAt: "2026-10-20T09:00:00+00:00", createdAt: "2026-10-20T09:00:00+00:00" },
  summary: {
    first: false,
    counts: { added: 1, changed: 5, removed: 1, moved: 1, reordered: 1 },
    types: { lesson: { ...zero, added: 1, changed: 1, moved: 1, reordered: 1 }, block: { ...zero, changed: 2, removed: 1 } },
    citations: { added: 2, removed: 1, repointed: 1 },
    quiz: { answerKeysChanged: 1, reworded: 1, questionsAdded: 0, questionsRemoved: 0 },
    learnerImpact: { lessonsKeepingCompletion: 5, lessonsWithUpdatedNotice: 1, lessonsChangedSilently: 1, lessonsRemoved: 0, lessonsAdded: 1, reattemptNotices: 1 },
    lines: [],
    text: "1 lesson added.",
  },
  changes,
  quiz: [
    { questionId: "q_1", label: "Lesson 1.1 › Q1", lessonId: "les_1", stem: null, type: null, explanation: false, options: { added: [], removed: [], reworded: [], keyChanged: [{ id: "o_a", text: "Pressure", before: true, after: false }, { id: "o_b", text: "Temperature", before: false, after: true }] }, answerKeyChanged: true, wording: false, correctBefore: ["Pressure"], correctAfter: ["Temperature"], consequence: "x" },
    { questionId: "q_2", label: "Lesson 1.1 › Q2", lessonId: "les_1", stem: { before: "Which temperature?", after: "Which water temperature?" }, type: null, explanation: false, options: { added: [], removed: [], reworded: [], keyChanged: [] }, answerKeyChanged: false, wording: true, correctBefore: [], correctAfter: [], consequence: null },
  ],
  fragments: {
    frg_added000001: { label: "Kettles.md", text: "A gooseneck spout pours slowly.", sourceRevision: { id: "rev3", number: 3 } },
    frg_added000002: { label: "Water.md", text: "Water between 92 and 96 degrees.", sourceRevision: { id: "rev3", number: 3 } },
    frg_removed00001: { label: "Water.md old", text: "Boiling water is fine.", sourceRevision: null },
    frg_old00000001: { label: "Water.md r2", text: "Use water at 90 degrees.", sourceRevision: { id: "rev2", number: 2 } },
    frg_new000000001: { label: "Water.md r3", text: "Use water at 95 degrees.", sourceRevision: { id: "rev3", number: 3 } },
  },
  ...over,
});
