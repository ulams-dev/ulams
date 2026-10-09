/**
 * Builder catalogue: the components the Course Builder streams as A2UI v0.9 surfaces (ADR 0011).
 *
 * Each entry has a description written for the model, a JSON Schema of its props (the subset in
 * ../schema.ts; every object closes with `additionalProperties: false`) and a plain-text fallback.
 * `yarn workspace @ulams/ui builder-manifest` writes catalogue/manifest.json, which the API copies to
 * api/packages/course-builder/resources/catalogue/manifest.json and validates model choices against
 * (a test fails when the two drift).
 *
 * Props are built by server code from validated blueprint data. The model only *chooses* a
 * component in two places: the interview controls and the element chat reply.
 */
import type { JsonSchema } from "../schema.ts";

export const BUILDER_CATALOGUE_ID = "https://ulams.dev/catalogue/builder/v1";
export const A2UI_VERSION = "v0.9";

const str = (description: string, maxLength = 2000): JsonSchema => ({ type: "string", description, maxLength });
const int = (description: string, extra: Partial<JsonSchema> = {}): JsonSchema => ({ type: "integer", description, minimum: 0, ...extra });
const bool = (description: string): JsonSchema => ({ type: "boolean", description });
const oneOf = (values: ReadonlyArray<string>, description: string): JsonSchema => ({ type: "string", enum: values, description });
const list = (items: JsonSchema, description: string, maxItems = 200): JsonSchema => ({ type: "array", items, description, maxItems });
const obj = (properties: Record<string, JsonSchema>, required: string[], description?: string): JsonSchema => ({
  type: "object",
  properties,
  required,
  additionalProperties: false,
  ...(description ? { description } : {}),
});

const CHANGE = oneOf(["added", "changed", "removed", "unchanged"], "Change against the previous version");
const DECIDED_BY = oneOf(["author", "default"], "Who decided the value");
const QUESTION_STATUS = oneOf(["open", "answered", "upcoming"], "Card state");
export const CITATION = obj(
  { fragmentId: str("Fragment id (frg_…)", 16), label: str("Section label shown on the chip, e.g. §2.3 Brewing ratios", 120) },
  ["fragmentId", "label"],
  "A source citation"
);
const OPTION = obj({ value: str("Value sent back when chosen", 120), label: str("Visible text", 120) }, ["value", "label"]);
const QUESTION_BASE = {
  questionKey: str("Brief field the question fills", 40),
  label: str("The question, short and plain", 200),
  why: str("One sentence on why the question matters for this source", 300),
  status: QUESTION_STATUS,
  decidedBy: DECIDED_BY,
  step: int("Position in the interview, from 1", { minimum: 1, maximum: 10 }),
  total: int("Number of questions", { minimum: 1, maximum: 10 }),
};
const OBJECTIVE = obj(
  {
    id: str("Objective id", 32),
    text: str("Measurable learning objective", 400),
    change: CHANGE,
    before: str("Previous text when changed", 400),
    citations: list(CITATION, "Fragments the objective comes from", 20),
  },
  ["id", "text", "citations"]
);
const STAGE_STATUS = oneOf(["pending", "running", "done", "failed"], "Stage state");
const COUNTS = obj(
  {
    courses: int("Courses"),
    lessons: int("Lessons (modules)"),
    topics: int("Topics (lesson pages and quizzes)"),
    questions: int("Quiz questions"),
    pages: int("Pages"),
  },
  ["courses", "lessons", "topics", "questions", "pages"]
);
const BLOCK = obj(
  {
    id: str("Block id", 32),
    kind: oneOf(["paragraph", "callout", "steps", "code", "table", "example"], "Block kind"),
    markdown: str("Block content (Markdown subset, no HTML)", 20000),
    citations: list(CITATION, "Cited fragments", 20),
  },
  ["id", "kind", "markdown", "citations"]
);

export interface BuilderComponentSpec {
  description: string;
  /** Interview controls and chat replies are the only components the model may choose. */
  modelSelectable: "interview" | "chat-reply" | false;
  children: boolean;
  props: JsonSchema;
  fallback: (props: Record<string, unknown>) => string;
}

const s = (v: unknown): string => (typeof v === "string" ? v : v === undefined || v === null ? "" : String(v));
const arr = (v: unknown): Record<string, unknown>[] => (Array.isArray(v) ? (v as Record<string, unknown>[]) : []);
const usd = (micro: unknown): string => `$${(Number(micro ?? 0) / 1_000_000).toFixed(2)}`;

export const builderCatalogue = {
  Column: {
    description: "Vertical layout of its children (A2UI basic layout).",
    modelSelectable: false,
    children: true,
    props: obj({ gap: oneOf(["sm", "md", "lg"], "Space between children") }, []),
    fallback: () => "",
  },
  Text: {
    description: "A short assistant message in plain text. Never course content.",
    modelSelectable: false,
    children: false,
    props: obj({ text: str("Plain text", 4000), variant: oneOf(["body", "lead", "muted"], "Emphasis") }, ["text"]),
    fallback: (p) => s(p.text),
  },
  ChoiceChips: {
    description:
      "Interview question answered with chips. Use for short categorical answers (tone, audience, assessments); `multiple` allows several chips. The author can also type a custom answer when `allowCustom` is true.",
    modelSelectable: "interview",
    children: false,
    props: obj(
      {
        ...QUESTION_BASE,
        options: list(OPTION, "Chips, 2 to 6", 6),
        multiple: bool("Several chips can be selected"),
        allowCustom: bool("Offer a free-text answer"),
        value: list(str("Selected value", 120), "Selected values", 6),
        defaultValue: list(str("Default value", 120), "Values used by Decide for me", 6),
      },
      ["questionKey", "label", "options", "status", "defaultValue"]
    ),
    fallback: (p) => `${s(p.label)} Options: ${arr(p.options).map((o) => s(o.label)).join(", ")}.`,
  },
  SingleChoice: {
    description: "Interview question with one answer from a short list shown as radio cards (e.g. level).",
    modelSelectable: "interview",
    children: false,
    props: obj(
      {
        ...QUESTION_BASE,
        options: list(OPTION, "Choices, 2 to 5", 5),
        value: str("Selected value", 120),
        defaultValue: str("Value used by Decide for me", 120),
      },
      ["questionKey", "label", "options", "status", "defaultValue"]
    ),
    fallback: (p) => `${s(p.label)} Choices: ${arr(p.options).map((o) => s(o.label)).join(", ")}.`,
  },
  DurationSlider: {
    description: "Interview question for the total course length and the lesson length, in minutes.",
    modelSelectable: "interview",
    children: false,
    props: obj(
      {
        ...QUESTION_BASE,
        totalOptions: list(int("Minutes", { minimum: 5, maximum: 2400 }), "Total duration presets", 6),
        lessonOptions: list(int("Minutes", { minimum: 2, maximum: 120 }), "Lesson length presets", 6),
        value: obj({ totalMinutes: int("Total minutes"), lessonMinutes: int("Lesson minutes") }, ["totalMinutes", "lessonMinutes"]),
        defaultValue: obj({ totalMinutes: int("Total minutes"), lessonMinutes: int("Lesson minutes") }, ["totalMinutes", "lessonMinutes"]),
      },
      ["questionKey", "label", "totalOptions", "lessonOptions", "status", "defaultValue"]
    ),
    fallback: (p) => `${s(p.label)} (minutes)`,
  },
  LanguagePicker: {
    description: "Interview question for the course language (ISO 639-1 codes).",
    modelSelectable: "interview",
    children: false,
    props: obj(
      {
        ...QUESTION_BASE,
        options: list(OPTION, "Languages, value is the ISO code", 12),
        value: str("Selected code", 8),
        defaultValue: str("Default code", 8),
      },
      ["questionKey", "label", "options", "status", "defaultValue"]
    ),
    fallback: (p) => `${s(p.label)} Languages: ${arr(p.options).map((o) => s(o.label)).join(", ")}.`,
  },
  DecideForMe: {
    description: "Button that fills every open interview question with its default and explains the choices.",
    modelSelectable: false,
    children: false,
    props: obj({ label: str("Button text", 60), open: int("Questions still open") }, ["label"]),
    fallback: (p) => s(p.label),
  },
  SourceCard: {
    description: "An uploaded source: file, processing status and its section tree with fragment ids.",
    modelSelectable: false,
    children: false,
    props: obj(
      {
        sourceId: str("Source id", 32),
        name: str("File name", 255),
        status: oneOf(["uploaded", "processing", "ready", "failed"], "Processing state"),
        sizeBytes: int("File size"),
        pages: int("PDF pages"),
        tokens: int("Estimated tokens"),
        fragmentCount: int("Fragments"),
        sections: list(obj({ fragmentId: str("First fragment of the section", 16), label: str("Section label", 160), level: int("Depth") }, ["fragmentId", "label", "level"]), "Sections", 400),
        error: str("Why processing failed", 500),
      },
      ["sourceId", "name", "status"]
    ),
    fallback: (p) => `Source ${s(p.name)}: ${s(p.status)}.`,
  },
  CitationChip: {
    description: "Amber chip linking an element to the source fragment it comes from.",
    modelSelectable: false,
    children: false,
    props: CITATION,
    fallback: (p) => `[${s(p.label)}]`,
  },
  OutlineDiff: {
    description:
      "Proposed course outline as a reviewable diff: modules, lessons with learning objectives and citations, added/changed/removed marks, inline objective edits, approve or request changes.",
    modelSelectable: false,
    children: false,
    props: obj(
      {
        versionId: str("Blueprint version", 32),
        number: int("Version number"),
        status: oneOf(["proposed", "approved", "rejected", "superseded"], "Decision state"),
        editable: bool("Objectives can be edited inline"),
        targetMinutes: int("Minutes from the brief"),
        summary: obj({ modules: int("Modules"), lessons: int("Lessons"), minutes: int("Minutes"), objectives: int("Objectives") }, ["modules", "lessons", "minutes", "objectives"]),
        course: obj({ title: str("Course title", 200), subtitle: str("Subtitle", 300), objectives: list(OBJECTIVE, "Course outcomes", 12) }, ["title", "objectives"]),
        modules: list(
          obj(
            {
              id: str("Module id", 32),
              title: str("Module title", 200),
              summary: str("One-line summary", 600),
              change: CHANGE,
              lessons: list(
                obj(
                  {
                    id: str("Lesson id", 32),
                    title: str("Lesson title", 200),
                    before: str("Previous title when changed", 200),
                    summary: str("One-line summary", 600),
                    minutes: int("Minutes"),
                    change: CHANGE,
                    objectives: list(OBJECTIVE, "Lesson objectives", 8),
                    citations: list(CITATION, "Fragments the lesson draws on", 20),
                  },
                  ["id", "title", "minutes", "objectives", "citations"]
                ),
                "Lessons",
                30
              ),
            },
            ["id", "title", "lessons"]
          ),
          "Modules",
          20
        ),
        removed: list(obj({ title: str("Title", 200), kind: oneOf(["module", "lesson"], "Kind") }, ["title", "kind"]), "Removed since the previous version", 40),
        comment: str("Author comment the version answers", 1000),
      },
      ["versionId", "status", "summary", "course", "modules"]
    ),
    fallback: (p) =>
      ["Proposed outline:", ...arr(p.modules).map((m) => `- ${s(m.title)}: ${arr(m.lessons).map((l) => s(l.title)).join("; ")}`)].join("\n"),
  },
  GenerationProgress: {
    description: "Stage timeline, per-lesson status with retry for a failed step, and the running cost.",
    modelSelectable: false,
    children: false,
    props: obj(
      {
        runId: str("Run id", 32),
        status: oneOf(["running", "needs_attention", "finished", "failed", "cancelled"], "Run state"),
        stages: list(obj({ key: str("Stage key", 32), label: str("Stage label", 60), status: STAGE_STATUS }, ["key", "label", "status"]), "Stages", 10),
        lessons: list(
          obj(
            {
              id: str("Lesson id", 32),
              title: str("Lesson title", 200),
              module: str("Module title", 200),
              status: oneOf(["pending", "running", "done", "failed", "flagged"], "Lesson state"),
              citations: int("Citations in the lesson"),
              questions: int("Quiz questions"),
              costMicroUsd: int("Cost so far"),
              stepId: str("Failed step to retry", 32),
              error: str("Failure reason", 500),
            },
            ["id", "title", "status"]
          ),
          "Lessons",
          200
        ),
        cost: obj(
          {
            usedMicroUsd: int("Spent"),
            budgetMicroUsd: int("Session budget"),
            inputTokens: int("Input tokens"),
            outputTokens: int("Output tokens"),
            cacheReadTokens: int("Cached input tokens"),
          },
          ["usedMicroUsd", "budgetMicroUsd"]
        ),
      },
      ["runId", "status", "stages", "lessons", "cost"]
    ),
    fallback: (p) => `Generating: ${arr(p.lessons).filter((l) => l.status === "done").length} of ${arr(p.lessons).length} lessons ready.`,
  },
  LessonPreviewCard: {
    description: "A generated lesson rendered for review, with citation chips and actions (edit in chat, view sources).",
    modelSelectable: "chat-reply",
    children: false,
    props: obj(
      {
        lessonId: str("Lesson id", 32),
        title: str("Lesson title", 200),
        minutes: int("Minutes"),
        blocks: list(BLOCK, "Content blocks", 60),
        flags: list(str("Unsupported claim or warning", 500), "Grounding flags", 20),
      },
      ["lessonId", "title", "blocks"]
    ),
    fallback: (p) => `${s(p.title)}\n${arr(p.blocks).map((b) => s(b.markdown)).join("\n\n")}`,
  },
  QuizQuestionCard: {
    description: "One quiz question with its answers, the correct ones marked, the explanation and its cited fragment.",
    modelSelectable: "chat-reply",
    children: false,
    props: obj(
      {
        questionId: str("Question id", 32),
        type: oneOf(["single", "multiple", "truefalse", "short"], "Question type"),
        stem: str("Question", 1000),
        options: list(obj({ id: str("Option id", 32), text: str("Answer", 500), correct: bool("Correct") }, ["id", "text", "correct"]), "Answers", 8),
        explanation: str("Why the answer is right", 2000),
        citations: list(CITATION, "Cited fragments", 10),
      },
      ["questionId", "type", "stem", "options", "explanation", "citations"]
    ),
    fallback: (p) => `${s(p.stem)}\n${arr(p.options).map((o) => `${o.correct ? "(correct) " : ""}${s(o.text)}`).join("\n")}`,
  },
  DiffView: {
    description: "Before/after of a proposed change to one element, block by block with word-level marks; approve or reject.",
    modelSelectable: "chat-reply",
    children: false,
    props: obj(
      {
        versionId: str("Proposed version", 32),
        elementId: str("Changed element", 32),
        elementLabel: str("Element shown to the author, e.g. Lesson 2.1 › Q2", 200),
        reason: str("The author's request", 1000),
        status: oneOf(["proposed", "approved", "rejected", "superseded"], "Decision state"),
        changes: list(
          obj(
            {
              path: str("Field path", 200),
              label: str("Readable field name", 200),
              kind: oneOf(["added", "removed", "changed"], "Change"),
              before: str("Old text", 20000),
              after: str("New text", 20000),
            },
            ["path", "label", "kind"]
          ),
          "Changes",
          100
        ),
        citations: list(CITATION, "Fragments the new version cites", 20),
      },
      ["versionId", "elementId", "elementLabel", "status", "changes"]
    ),
    fallback: (p) => `Proposed change to ${s(p.elementLabel)}: ${arr(p.changes).length} edits.`,
  },
  ApplySummary: {
    description: "What applying the blueprint will create, update and delete in the LMS, with warnings; approve to apply.",
    modelSelectable: false,
    children: false,
    props: obj(
      {
        versionId: str("Version to apply", 32),
        status: oneOf(["proposed", "applying", "applied", "failed"], "Apply state"),
        firstApply: bool("Creates the course"),
        create: COUNTS,
        update: COUNTS,
        delete: COUNTS,
        warnings: list(str("Warning", 500), "Warnings", 40),
        courseId: int("Course id after apply"),
      },
      ["versionId", "status", "create", "update", "delete", "warnings"]
    ),
    fallback: (p) => `Apply version: ${s((p.create as Record<string, unknown> | undefined)?.topics)} topics to create.`,
  },
  VersionList: {
    description: "Blueprint version history with origin and status; restore any version.",
    modelSelectable: false,
    children: false,
    props: obj(
      {
        currentVersionId: str("Current version", 32),
        versions: list(
          obj(
            {
              id: str("Version id", 32),
              number: int("Number"),
              kind: str("outline, content, patch, author, restore", 16),
              origin: oneOf(["ai", "author", "restore"], "Who made it"),
              status: oneOf(["proposed", "approved", "rejected", "superseded"], "State"),
              reason: str("Why", 1000),
              createdAt: str("ISO time", 40),
            },
            ["id", "number", "origin", "status"]
          ),
          "Versions",
          500
        ),
      },
      ["versions"]
    ),
    fallback: (p) => `${arr(p.versions).length} versions.`,
  },
  CostMeter: {
    description: "Running AI cost of the session against its budget.",
    modelSelectable: false,
    children: false,
    props: obj(
      { usedMicroUsd: int("Spent"), budgetMicroUsd: int("Budget"), tokens: int("Tokens used"), label: str("Profile label", 60) },
      ["usedMicroUsd", "budgetMicroUsd"]
    ),
    fallback: (p) => `${usd(p.usedMicroUsd)} of ${usd(p.budgetMicroUsd)}`,
  },
} satisfies Record<string, BuilderComponentSpec>;

export type BuilderComponentName = keyof typeof builderCatalogue;
export const builderComponentNames = Object.keys(builderCatalogue) as BuilderComponentName[];
export const isBuilderComponent = (name: unknown): name is BuilderComponentName =>
  typeof name === "string" && Object.prototype.hasOwnProperty.call(builderCatalogue, name);

/** The JSON manifest copied into the API (no functions). */
export function builderManifest(): {
  catalogId: string;
  a2uiVersion: string;
  components: Record<string, { description: string; modelSelectable: string | false; children: boolean; props: JsonSchema }>;
} {
  return {
    catalogId: BUILDER_CATALOGUE_ID,
    a2uiVersion: A2UI_VERSION,
    components: Object.fromEntries(
      Object.entries(builderCatalogue).map(([name, spec]) => [
        name,
        { description: spec.description, modelSelectable: spec.modelSelectable, children: spec.children, props: spec.props },
      ])
    ),
  };
}
