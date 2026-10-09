/** Valid props for every builder component, shaped like what the API streams. */
const cite = (n: number) => ({ fragmentId: `frg_aaaaaaaaaaa${n}`, label: `§${n} Section ${n}` });
const counts = (n: number) => ({ courses: n, lessons: n, topics: n, questions: n, pages: n });

export const builderFixtures: Record<string, Record<string, unknown>> = {
  Column: { gap: "md" },
  Text: { text: "I read your handbook.", variant: "body" },
  ChoiceChips: {
    questionKey: "assessments",
    label: "How should learners check their progress?",
    why: "Short quizzes help learners remember the key numbers.",
    status: "open",
    step: 5,
    total: 6,
    options: [
      { value: "quiz", label: "Quiz after each lesson" },
      { value: "final", label: "Final test" },
    ],
    multiple: true,
    allowCustom: false,
    defaultValue: ["quiz"],
  },
  SingleChoice: {
    questionKey: "level",
    label: "What level should it start at?",
    status: "open",
    options: [
      { value: "beginner", label: "Beginner" },
      { value: "intermediate", label: "Intermediate" },
      { value: "advanced", label: "Advanced" },
    ],
    defaultValue: "beginner",
  },
  DurationSlider: {
    questionKey: "duration",
    label: "How long should the course be?",
    status: "open",
    totalOptions: [30, 60, 120],
    lessonOptions: [5, 10, 15],
    defaultValue: { totalMinutes: 60, lessonMinutes: 10 },
  },
  LanguagePicker: {
    questionKey: "language",
    label: "Which language should the course use?",
    status: "answered",
    decidedBy: "default",
    options: [
      { value: "en", label: "English" },
      { value: "pl", label: "Polski" },
    ],
    value: "en",
    defaultValue: "en",
  },
  DecideForMe: { label: "Decide the rest for me", open: 3 },
  SourceCard: {
    sourceId: "01src",
    name: "coffee-brewing.md",
    status: "ready",
    sizeBytes: 6400,
    tokens: 3900,
    fragmentCount: 22,
    sections: [{ fragmentId: "frg_aaaaaaaaaaa1", label: "§1.1 What extraction means", level: 1 }],
  },
  CitationChip: cite(1),
  OutlineDiff: {
    versionId: "01v",
    number: 1,
    status: "proposed",
    editable: true,
    targetMinutes: 30,
    summary: { modules: 1, lessons: 1, minutes: 10, objectives: 2 },
    course: { title: "Coffee Brewing Fundamentals", objectives: [{ id: "o0", text: "Brew a balanced cup", change: "added", citations: [cite(1)] }] },
    modules: [
      {
        id: "m1",
        title: "Extraction",
        change: "added",
        lessons: [{ id: "l1", title: "What extraction means", minutes: 10, change: "added", objectives: [{ id: "o1", text: "Explain extraction", citations: [cite(1)] }], citations: [cite(1)] }],
      },
    ],
    removed: [],
  },
  GenerationProgress: {
    runId: "01run",
    status: "needs_attention",
    stages: [
      { key: "lessons", label: "Lessons", status: "running" },
      { key: "quizzes", label: "Quizzes", status: "pending" },
    ],
    lessons: [
      { id: "l1", title: "1.1 What extraction means", module: "Extraction", status: "done", citations: 3, questions: 2, costMicroUsd: 30000 },
      { id: "l2", title: "1.2 Strength", module: "Extraction", status: "failed", stepId: "01step", error: "The model declined this step." },
    ],
    cost: { usedMicroUsd: 120000, budgetMicroUsd: 5000000, inputTokens: 1000, outputTokens: 400, cacheReadTokens: 3000 },
  },
  LessonPreviewCard: {
    lessonId: "l1",
    title: "What extraction means",
    minutes: 10,
    blocks: [
      { id: "b1", kind: "paragraph", markdown: "Brewing is **extraction**. <script>alert(1)</script>", citations: [cite(1)] },
      { id: "b2", kind: "table", markdown: "| A | B |\n|---|---|\n| 1 | 2 |", citations: [cite(2)] },
    ],
    flags: [],
  },
  QuizQuestionCard: {
    questionId: "q1",
    type: "single",
    stem: "How much water for 20 g of coffee at 1:16?",
    options: [
      { id: "a", text: "300 ml", correct: false },
      { id: "b", text: "320 ml", correct: true },
    ],
    explanation: "20 × 16 = 320.",
    citations: [cite(2)],
  },
  DiffView: {
    versionId: "01p",
    elementId: "q1",
    elementLabel: "Lesson 2.1 › Q2",
    reason: "Make the distractors less obvious",
    status: "proposed",
    changes: [{ path: "a.text", label: "Option A · Text", kind: "changed", before: "350 ml", after: "310 ml" }],
    citations: [cite(2)],
  },
  ApplySummary: { versionId: "01c", status: "proposed", firstApply: true, create: counts(1), update: counts(0), delete: counts(0), warnings: ["Lesson 1.2: Possibly unsupported claim"] },
  VersionList: {
    currentVersionId: "v3",
    versions: [
      { id: "v2", number: 2, kind: "content", origin: "ai", status: "approved", reason: "Generated lessons" },
      { id: "v3", number: 3, kind: "patch", origin: "ai", status: "approved", reason: "Shorter" },
    ],
  },
  CostMeter: { usedMicroUsd: 710000, budgetMicroUsd: 5000000, tokens: 120000, label: "Sonnet" },
};
