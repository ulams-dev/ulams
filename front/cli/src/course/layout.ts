import type { KeySpec } from "./yaml-io.ts";

export const ULAMS_FILE = "ulams.yaml";
export const COURSE_FILE = "course.yaml";
export const FINAL_TEST_FILE = "final-test.quiz.yaml";
export const MODULES_DIR = "modules";
export const MODULE_FILE = "module.yaml";
export const ASSETS_DIR = "assets";
export const FORMAT_VERSION = 1;
export const BLUEPRINT_VERSION = 3;

const OBJECTIVE: KeySpec = { keys: ["id", "text", "citations"], omit: { citations: [] } };
const OPTION_KEYS = ["id", "text", "correct"];
export const QUESTION: KeySpec = {
  keys: ["id", "type", "stem", "options", "explanation", "origin", "citations", "objectiveIds"],
  child: { options: { keys: OPTION_KEYS, omit: { correct: false } } },
  flow: ["options"],
  omit: { citations: [], objectiveIds: [] },
};

export const ULAMS_SPEC: KeySpec = {
  keys: ["format", "blueprint", "profile", "course", "language", "lint"],
  child: { course: { keys: ["id", "session"] }, lint: { keys: ["uncitedAuthorBlocks"] } },
};

export const COURSE_SPEC: KeySpec = {
  keys: ["id", "title", "subtitle", "language", "audience", "description", "seo", "objectives", "faq", "pages", "sources"],
  child: {
    seo: { keys: ["title", "description"] },
    objectives: OBJECTIVE,
    faq: { keys: ["question", "answer", "origin", "citations"], omit: { citations: [] } },
    sources: { keys: ["id", "title", "fragmentCount"] },
  },
};

export const MODULE_SPEC: KeySpec = { keys: ["id", "key", "title", "summary"] };

export const LESSON_SPEC: KeySpec = {
  keys: ["id", "key", "title", "summary", "minutes", "objectives", "contentType", "citations", "status", "origin", "flags", "asset", "assetRef", "video", "embed", "exercises", "selfChecks", "interaction"],
  child: {
    objectives: OBJECTIVE,
    asset: { keys: ["path", "sha256", "bytes", "mediaType"] },
    assetRef: { keys: ["entityType", "entityId"] },
    video: { keys: ["provider", "url"] },
    embed: { keys: ["url", "title"] },
    selfChecks: QUESTION,
    interaction: { keys: ["id", "kind", "library", "packageId", "title", "startStep", "endStep", "caption", "data", "origin", "citations", "objectiveIds"], omit: { citations: [], objectiveIds: [] } },
  },
  omit: { contentType: "richtext", status: "generated", citations: [], flags: [] },
};

export const QUIZ_SPEC: KeySpec = { keys: ["id", "passScore", "questions"], child: { questions: QUESTION } };
