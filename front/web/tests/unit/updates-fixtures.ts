import type { ProposalDetail, ProposalItem } from "@ulams/sdk";

export const item = (over: Partial<ProposalItem> = {}): ProposalItem => ({
  id: "i1", groupKey: "lesson:l1", elementId: "b1", type: "block", label: "Lesson 1.1 › Paragraph 1", kind: "update", reason: "Section 3.2 changed the ratio.", severity: "minor",
  before: { id: "b1", kind: "paragraph", markdown: "Use 15 g of coffee.", citations: ["frg_aaaaaaaaaaa1"] },
  after: { id: "b1", kind: "paragraph", markdown: "Use 16 g of coffee.", citations: ["frg_aaaaaaaaaaa2"] },
  changeClass: "minor", answerStatus: null, answerCheck: false, status: "pending", flags: {}, regenerations: 0,
  fragments: [{ fragmentId: "frg_aaaaaaaaaaa2", label: "§3.2 Ratios" }], changeIds: [1], decidedAt: null, ...over,
});

export const question = (): ProposalItem =>
  item({
    id: "i2", elementId: "q1", type: "question", label: "Lesson 1.1 › Q1", changeClass: "answer_changed", answerStatus: "changed", answerCheck: true, flags: { grounding: ["Possibly unsupported: filtered water"], signals: ["number"], checkAnswer: true },
    before: { id: "q1", type: "single", stem: "Ratio?", explanation: "Because.", options: [{ id: "o1", text: "1:15", correct: true }, { id: "o2", text: "1:17", correct: false }] },
    after: { id: "q1", type: "single", stem: "Ratio?", explanation: "Because.", options: [{ id: "o1", text: "1:16", correct: true }, { id: "o2", text: "1:17", correct: false }, { id: "", text: "1:18", correct: false }] },
  });

export const detail = (items: ProposalItem[], over: Partial<ProposalDetail> = {}): ProposalDetail =>
  ({
    id: "p1", number: 1, sessionId: "s", sourceId: "src", status: "ready", trigger: "manual", counts: { items: items.length, elements: 2, remaps: 1, uncovered: 0, major: 1, answerChecks: 1, groups: 1 },
    fromRevision: { id: "r1", number: 1 }, toRevision: { id: "r2", number: 2, detectedAt: null }, runId: "run1", baseVersionId: null, resultVersionId: null, estimatedCostMicroUsd: 420000, costMicroUsd: 130000,
    decisions: {}, learnerNote: null, learnerImpact: { learners: { topic_updated: 12, question_reattempt: 3, topic_retired: 0, course_extended: 0 }, note: "Section 3.2 changed the ratio." }, error: null, createdAt: null, decidedAt: null, appliedAt: null, steps: [],
    groups: [{ key: "lesson:l1", label: "Lesson 1.1: Ratios", items }], items, ...over,
  }) as ProposalDetail;
