import { z } from "zod";
import { FRAGMENT_RE, ID_RE, KEY_RE } from "./ids.ts";
import { BLUEPRINT_VERSION, FORMAT_VERSION } from "./layout.ts";

/* Schemas of the files of a course folder. They give editors completion and validation (committed as JSON Schema
 * under front/cli/schemas/course-folder, see scripts/gen-course-schemas.mjs) and `course validate` its
 * file-level errors. Ids may be missing in a file (`course fmt` mints them), so `id` is optional here; the
 * validator reports ID_MISSING itself. Rules that need several files (objectives, content type payloads,
 * citation rule) live in checks.ts and in the bundled Blueprint v3 schema. */

const id = z.string().regex(ID_RE, "An element id is 26 lowercase letters and digits.").describe("Element id: a lowercase ULID. `ulams course fmt` mints missing ids.");
const fragment = z.string().regex(FRAGMENT_RE, "A fragment id looks like frg_ followed by 12 characters a-z or 2-7.");
const citations = z.array(fragment).max(20).default([]).describe("Source fragments this element cites. Only elements with origin: author may leave it empty.");
const origin = z.enum(["ai", "author"]).describe("Who wrote it. Absent means ai; only author elements may have no citations.");
const key = z.string().regex(KEY_RE, "A key is a lowercase slug: a-z, 0-9 and dashes, at most 64 characters.").describe("Optional stable slug, unique among siblings.");
const language = z.string().regex(/^[a-z]{2}$/, "Two lowercase letters, for example en.");

export const objectiveSchema = z.strictObject({
  id: id.optional(),
  text: z.string().min(3).max(400),
  citations,
});

export const optionSchema = z.strictObject({
  id: id.optional(),
  text: z.string().min(1).max(500),
  correct: z.boolean().default(false),
});

export const questionSchema = z.strictObject({
  id: id.optional(),
  type: z.enum(["single", "multiple", "truefalse", "short"]),
  stem: z.string().min(3).max(1000),
  options: z.array(optionSchema).min(1).max(8),
  explanation: z.string().min(3).max(2000),
  origin: origin.optional(),
  citations,
  objectiveIds: z.array(id).default([]).describe("Ids of objectives of the lesson this question checks."),
});

export const quizSchema = z.strictObject({
  id: id.optional(),
  passScore: z.number().int().min(0).max(100).optional(),
  questions: z.array(questionSchema).max(50),
});

export const ulamsSchema = z.strictObject({
  format: z.literal(FORMAT_VERSION).describe("Course folder format version."),
  blueprint: z.literal(BLUEPRINT_VERSION).describe("Blueprint schema version the folder maps to."),
  profile: z.string().optional().describe("Default CLI profile; CI uses ULAMS_URL and ULAMS_TOKEN. Never put secrets here."),
  course: z
    .strictObject({
      id: z.number().int().positive().optional().describe("Course id on the server; set by the first push."),
      session: z.string().optional().describe("Builder session that owns the course."),
    })
    .optional(),
  language: language.optional().describe("Default language when course.yaml has none."),
  lint: z.strictObject({ uncitedAuthorBlocks: z.enum(["warn", "error", "off"]).default("warn") }).optional(),
});

const landingPage = z.record(z.string(), z.unknown()).nullable().optional();

export const courseSchema = z.strictObject({
  id: id.optional(),
  title: z.string().min(3).max(200),
  subtitle: z.string().max(300).optional(),
  language: language.optional(),
  audience: z.string().max(300).optional(),
  description: z.string().max(4000).optional(),
  seo: z.strictObject({ title: z.string().max(70).optional(), description: z.string().max(160).optional() }).optional(),
  objectives: z.array(objectiveSchema).max(12).default([]),
  faq: z
    .array(z.strictObject({ question: z.string().max(300), answer: z.string().max(2000), origin: origin.optional(), citations }))
    .max(12)
    .optional(),
  pages: z.strictObject({ landing: landingPage, header: landingPage }).optional(),
  sources: z.array(z.strictObject({ id: z.string(), title: z.string(), fragmentCount: z.number().int() })).optional().describe("Source documents of the builder session; informational, written by pull."),
});

export const moduleSchema = z.strictObject({
  id: id.optional(),
  key: key.optional(),
  title: z.string().min(2).max(200),
  summary: z.string().max(600).optional(),
});

const assetSchema = z.union([
  z.string().min(1).max(300).describe("Path relative to assets/; `ulams course fmt` expands it with sha256, bytes and mediaType."),
  z.strictObject({
    path: z.string().min(1).max(300),
    sha256: z.string().regex(/^[0-9a-f]{64}$/).optional(),
    bytes: z.number().int().min(0).optional(),
    mediaType: z.string().max(120).optional(),
  }),
]);

const interactionSchema = z.discriminatedUnion("kind", [
  z.strictObject({
    id: id.optional(),
    kind: z.literal("h5p"),
    library: z.enum(["H5P.Blanks", "H5P.DragText", "H5P.Dialogcards"]),
    title: z.string().min(2).max(200),
    data: z.record(z.string(), z.unknown()),
    origin: origin.optional(),
    citations,
    objectiveIds: z.array(id).default([]),
  }),
  z.strictObject({
    id: id.optional(),
    kind: z.literal("interactive"),
    packageId: z.number().int().min(1),
    title: z.string().min(2).max(200),
    startStep: z.string().max(64).optional(),
    endStep: z.string().max(64).optional(),
    caption: z.string().min(1).max(4000),
    origin: origin.optional(),
    citations,
    objectiveIds: z.array(id).default([]),
  }),
]);

export const lessonFrontMatterSchema = z.strictObject({
  id: id.optional(),
  key: key.optional(),
  title: z.string().min(2).max(200),
  summary: z.string().max(600).optional(),
  minutes: z.number().int().min(1).max(600),
  objectives: z.array(objectiveSchema).min(1).max(8).describe("What the lesson teaches. Blocks and questions refer to these ids."),
  contentType: z.enum(["richtext", "liascript", "h5p", "interactive", "scorm", "cmi5", "video", "file", "embed"]).default("richtext"),
  citations,
  status: z.enum(["planned", "generated", "failed"]).default("generated"),
  origin: origin.optional(),
  flags: z.array(z.string().max(500)).max(20).default([]),
  asset: assetSchema.optional(),
  assetRef: z.strictObject({ entityType: z.string().max(64), entityId: z.union([z.number().int(), z.string()]) }).optional().describe("Set by the server after an upload or an adoption."),
  video: z.strictObject({ provider: z.enum(["upload", "youtube", "vimeo"]), url: z.string().max(500).optional() }).optional(),
  embed: z.strictObject({ url: z.string().max(500), title: z.string().min(1).max(200) }).optional(),
  exercises: z.array(z.never()).max(0).optional().describe("Reserved for code exercises."),
  selfChecks: z.array(questionSchema).max(6).optional().describe("LiaScript lessons: questions shown after the text."),
  interaction: interactionSchema.optional().describe("h5p and interactive lessons: the interaction that follows the text."),
});

/** The files and the schema each one is validated against; the generator writes one JSON Schema per entry. */
export const FILE_SCHEMAS = {
  "ulams.schema.json": { title: "ulams.yaml", description: "Course folder settings: format version, default profile, course binding, lint levels.", schema: ulamsSchema },
  "course.schema.json": { title: "course.yaml", description: "Course fields, learning objectives, FAQ and pages.", schema: courseSchema },
  "module.schema.json": { title: "module.yaml", description: "A module of the course.", schema: moduleSchema },
  "lesson-front-matter.schema.json": { title: "Lesson front matter", description: "The YAML block at the top of a lesson Markdown file.", schema: lessonFrontMatterSchema },
  "quiz.schema.json": { title: "Quiz", description: "A lesson quiz (*.quiz.yaml) or the final test (final-test.quiz.yaml).", schema: quizSchema },
} as const;

export const SCHEMA_BASE_URL = "https://ulams.dev/schemas/course-folder/1";

export function jsonSchemaFor(name: keyof typeof FILE_SCHEMAS): Record<string, unknown> {
  const entry = FILE_SCHEMAS[name];
  const generated = z.toJSONSchema(entry.schema, { target: "draft-2020-12", io: "input", unrepresentable: "any" }) as Record<string, unknown>;
  const { $schema, ...rest } = generated;
  return { $schema, $id: `${SCHEMA_BASE_URL}/${name}`, title: entry.title, description: entry.description, ...rest };
}
