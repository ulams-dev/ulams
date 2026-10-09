import { z } from "zod";
import { CliError } from "../errors.ts";
import { appendForm } from "../registry/http-run.ts";
import { waitOperation } from "../http/lro.ts";
import { markdownToHtml } from "../util-markdown.ts";
import { defineCommand } from "./define.ts";
import type { AnyCommand, Ctx } from "../registry/types.ts";

const NS = "Ulams\\TopicTypes\\Models\\TopicContent\\";
export const TOPIC_CLASSES = {
  richtext: `${NS}RichText`,
  video: `${NS}Video`,
  audio: `${NS}Audio`,
  image: `${NS}Image`,
  pdf: `${NS}PDF`,
  oembed: `${NS}OEmbed`,
  h5p: `${NS}H5P`,
  scorm: `${NS}ScormSco`,
  cmi5: `${NS}Cmi5Au`,
  liascript: "Ulams\\LiaScript\\Models\\LiaScriptTopic",
  interactive: "Ulams\\Interactive\\Models\\InteractiveTopic",
  layout: "Ulams\\TopicTypeLayout\\Models\\LayoutTopic",
  quiz: "Ulams\\TopicTypeGift\\Models\\GiftQuiz",
  project: "Ulams\\TopicTypeProject\\Models\\Project",
  lti: "Ulams\\Lti\\Models\\LtiLink",
} as const;

const base = {
  lesson: z.number().int().describe("Lesson id (from `ulams lessons list --course-id <id>`)."),
  title: z.string().describe("Topic title."),
  order: z.number().int().optional().describe("Position in the lesson; default: after the last topic."),
  summary: z.string().optional().describe("Short summary."),
  preview: z.boolean().optional().describe("Visible without access to the course."),
  active: z.boolean().optional().describe("Visible to learners (default true)."),
  canSkip: z.boolean().optional().describe("Learners may skip the topic."),
  duration: z.string().optional().describe("Estimated duration, e.g. '10 min'."),
};
type Base = { lesson: number; title: string; order?: number; summary?: string; preview?: boolean; active?: boolean; canSkip?: boolean; duration?: string };

/** Next free position in a lesson: the lesson tells its course, the course lists the lesson's topics. */
async function nextOrder(ctx: Ctx, lessonId: number): Promise<number> {
  try {
    const lesson = await ctx.client.call<{ course_id?: number }>("GET", "/api/admin/lessons/{id}", { params: { id: lessonId }, idempotent: true });
    const courseId = lesson.data.course_id;
    if (!courseId) return 1;
    const course = await ctx.client.call<{ lessons?: Array<{ id: number; topics?: Array<{ order?: number }> }> }>("GET", "/api/admin/courses/{course}", {
      params: { course: courseId },
      idempotent: true,
    });
    const topics = course.data.lessons?.find((l) => l.id === lessonId)?.topics ?? [];
    return topics.reduce((max, t) => Math.max(max, t.order ?? 0), 0) + 1;
  } catch {
    return 1;
  }
}

function baseFields(b: Base, order: number): Record<string, unknown> {
  return {
    lesson_id: b.lesson,
    title: b.title,
    order,
    ...(b.summary !== undefined ? { summary: b.summary } : {}),
    ...(b.preview !== undefined ? { preview: b.preview } : {}),
    ...(b.active !== undefined ? { active: b.active } : {}),
    ...(b.canSkip !== undefined ? { can_skip: b.canSkip } : {}),
    ...(b.duration !== undefined ? { duration: b.duration } : {}),
  };
}

export interface CreateSpec {
  type: keyof typeof TOPIC_CLASSES;
  /** extra fields besides the value */
  extra?: Record<string, unknown>;
  value: unknown;
  /** local file to upload as `value` */
  file?: string;
}

/** POST /api/admin/topics: JSON, or multipart when a file is the value. */
export async function createTopic(ctx: Ctx, b: Base, spec: CreateSpec): Promise<unknown> {
  const order = b.order ?? (await nextOrder(ctx, b.lesson));
  const fields = { ...baseFields(b, order), topicable_type: TOPIC_CLASSES[spec.type], ...spec.extra };
  if (spec.file) {
    const form = new FormData();
    for (const [k, v] of Object.entries(fields)) appendForm(form, k, v);
    form.append("value", await blobOf(ctx, spec.file), spec.file.split(/[\\/]/).pop() ?? "file");
    return (await ctx.client.call("POST", "/api/admin/topics", { form, signal: ctx.signal })).data;
  }
  return (await ctx.client.call("POST", "/api/admin/topics", { body: { ...fields, value: spec.value }, signal: ctx.signal })).data;
}

async function blobOf(ctx: Ctx, path: string): Promise<Blob> {
  try {
    return new Blob([(await ctx.fs.readFile(path)) as BlobPart]);
  } catch {
    throw new CliError("INPUT_INVALID", `Cannot read ${path}.`, { hint: "Pass the path of an existing local file." });
  }
}

async function uploadFile(ctx: Ctx, path: string, field: string, file: string, extra: Record<string, unknown> = {}): Promise<{ data: unknown }> {
  const form = new FormData();
  for (const [k, v] of Object.entries(extra)) appendForm(form, k, v);
  form.append(field, await blobOf(ctx, file), file.split(/[\\/]/).pop() ?? "file");
  return ctx.client.call("POST", path, { form, signal: ctx.signal });
}

function choose(kind: string, items: Array<{ id: number; label: string }>, wanted: number | undefined): number {
  if (wanted !== undefined) {
    if (!items.some((i) => i.id === wanted)) {
      throw new CliError("INPUT_INVALID", `The package has no ${kind} with id ${wanted}.`, { details: { choices: items } });
    }
    return wanted;
  }
  if (items.length === 1) return (items[0] as { id: number }).id;
  throw new CliError("INPUT_INVALID", `The package contains ${items.length} ${kind}s; choose one.`, {
    hint: `Re-run with --${kind} <id>.`,
    details: { choices: items },
  });
}

const common = { kind: "write" as const, idempotent: false, scopes: ["courses:write"], audience: ["admin" as const], mcp: { toolset: "topics" } };

const plan = (steps: string[]) => async () => ({ note: steps.join(" -> ") });

export const topicCommands: AnyCommand[] = [
  defineCommand({
    ...common,
    id: "topics.create-richtext",
    summary: "Create a rich text topic from Markdown or HTML",
    description: "Use for text lessons. Pass --markdown (inline text or @file.md) or --html. Markdown is converted to HTML on the client.",
    endpoints: ["POST /api/admin/topics"],
    input: z.object({
      ...base,
      markdown: z.string().optional().meta({ fileInput: true }).describe("Markdown text, or @path to a Markdown file."),
      html: z.string().optional().meta({ fileInput: true }).describe("HTML text, or @path to an HTML file."),
    }),
    output: z.unknown(),
    examples: [{ title: "A text topic from a file", argv: "topics create-richtext --lesson 12 --title Intro --markdown @intro.md --json" }],
    plan: plan(["POST /api/admin/topics (RichText)"]),
    async run(ctx, i) {
      if ((i.markdown === undefined) === (i.html === undefined)) {
        throw new CliError("INPUT_INVALID", "Pass exactly one of --markdown or --html.");
      }
      const value = i.html ?? markdownToHtml(i.markdown as string);
      return { data: await createTopic(ctx, i, { type: "richtext", value }) };
    },
  }),
  defineCommand({
    ...common,
    id: "topics.create-oembed",
    summary: "Create a topic that embeds a URL (YouTube, Vimeo and other oEmbed providers)",
    endpoints: ["POST /api/admin/topics"],
    input: z.object({ ...base, url: z.string().describe("Page URL of the video or content.") }),
    output: z.unknown(),
    examples: [{ title: "Embed a YouTube video", argv: "topics create-oembed --lesson 12 --title Demo --url https://www.youtube.com/watch?v=abc --json" }],
    plan: plan(["POST /api/admin/topics (OEmbed)"]),
    async run(ctx, i) {
      return { data: await createTopic(ctx, i, { type: "oembed", value: i.url }) };
    },
  }),
  defineCommand({
    ...common,
    id: "topics.create-video",
    summary: "Create a video topic from a local file, or from a YouTube URL",
    description: "--file uploads the video (mp4, ogg, webm, mov; processed in the background: use `ulams operations wait video:<topic id>`). --youtube-url creates an embedded topic.",
    endpoints: ["POST /api/admin/topics"],
    longRunning: { kind: "video" },
    input: z.object({
      ...base,
      file: z.string().optional().describe("Local video file."),
      youtubeUrl: z.string().optional().describe("YouTube URL (embedded, no upload)."),
    }),
    output: z.unknown(),
    examples: [{ title: "Upload a video", argv: "topics create-video --lesson 12 --title Intro --file ./intro.mp4 --json" }],
    plan: plan(["POST /api/admin/topics (Video, multipart)"]),
    async run(ctx, i) {
      if ((i.file === undefined) === (i.youtubeUrl === undefined)) throw new CliError("INPUT_INVALID", "Pass exactly one of --file or --youtube-url.");
      if (i.youtubeUrl) return { data: await createTopic(ctx, i, { type: "oembed", value: i.youtubeUrl }) };
      const topic = (await createTopic(ctx, i, { type: "video", value: null, file: i.file as string })) as { id?: number };
      if (!topic.id) return { data: topic };
      const handle = `video:${topic.id}`;
      if (!ctx.flags.wait) return { data: topic, meta: { operation: handle } };
      return { data: { topic, processing: await waitOperation(ctx, handle) } };
    },
  }),
  defineCommand({
    ...common,
    id: "topics.create-file",
    summary: "Create a PDF, image or audio topic from a local file",
    description: "The topic type follows the extension: .pdf, .png .jpg .jpeg .gif .webp .svg, .mp3 .ogg.",
    endpoints: ["POST /api/admin/topics"],
    input: z.object({ ...base, file: z.string().describe("Local file.") }),
    output: z.unknown(),
    examples: [{ title: "A PDF topic", argv: "topics create-file --lesson 12 --title Handout --file ./handout.pdf --json" }],
    plan: plan(["POST /api/admin/topics (PDF|Image|Audio, multipart)"]),
    async run(ctx, i) {
      const ext = i.file.toLowerCase().split(".").pop() ?? "";
      const type = ext === "pdf" ? "pdf" : ["png", "jpg", "jpeg", "gif", "webp", "svg"].includes(ext) ? "image" : ["mp3", "ogg"].includes(ext) ? "audio" : null;
      if (!type) throw new CliError("INPUT_INVALID", `Unsupported file type .${ext}.`, { hint: "Use .pdf, an image, .mp3 or .ogg; videos use create-video." });
      return { data: await createTopic(ctx, i, { type, value: null, file: i.file }) };
    },
  }),
  defineCommand({
    ...common,
    id: "topics.create-scorm",
    summary: "Create a SCORM topic from a package .zip",
    description: "Uploads the package, picks its SCO (the only one, or --sco), then creates the topic. Several SCOs without --sco exit 2 with details.choices.",
    endpoints: ["POST /api/admin/scorm/upload", "POST /api/admin/topics"],
    input: z.object({ ...base, package: z.string().describe("Local SCORM .zip."), sco: z.number().int().optional().describe("SCO id when the package has several.") }),
    output: z.unknown(),
    examples: [{ title: "A SCORM package", argv: "topics create-scorm --lesson 12 --title Safety --package ./pkg.zip --json" }],
    plan: plan(["POST /api/admin/scorm/upload", "choose SCO", "POST /api/admin/topics (ScormSco)"]),
    async run(ctx, i) {
      const up = (await uploadFile(ctx, "/api/admin/scorm/upload", "zip", i.package)).data as { scormData?: { scos?: Array<{ id: number; title?: string }> }; scos?: Array<{ id: number; title?: string }> };
      const scos = (up.scormData?.scos ?? up.scos ?? []).map((s) => ({ id: s.id, label: s.title ?? String(s.id) }));
      const sco = choose("sco", scos, i.sco);
      return { data: { topic: await createTopic(ctx, i, { type: "scorm", value: sco }), scorm: up } };
    },
  }),
  defineCommand({
    ...common,
    id: "topics.create-cmi5",
    summary: "Create a cmi5 topic from a package .zip",
    description: "Uploads the cmi5 package and creates a topic for its assignable unit (the only one, or --au).",
    endpoints: ["POST /api/admin/cmi5", "POST /api/admin/topics"],
    input: z.object({ ...base, package: z.string().describe("Local cmi5 .zip."), au: z.number().int().optional().describe("Assignable unit id when there are several.") }),
    output: z.unknown(),
    examples: [{ title: "A cmi5 package", argv: "topics create-cmi5 --lesson 12 --title Module --package ./au.zip --json" }],
    plan: plan(["POST /api/admin/cmi5", "choose AU", "POST /api/admin/topics (Cmi5Au)"]),
    async run(ctx, i) {
      const up = (await uploadFile(ctx, "/api/admin/cmi5", "file", i.package)).data as { au?: Array<{ id: number; title?: string }>; aus?: Array<{ id: number; title?: string }> };
      const list = up.au ?? up.aus ?? [];
      const au = choose("au", list.map((a) => ({ id: a.id, label: a.title ?? String(a.id) })), i.au);
      return { data: { topic: await createTopic(ctx, i, { type: "cmi5", value: au }), package: up } };
    },
  }),
  defineCommand({
    ...common,
    id: "topics.create-h5p",
    summary: "Create an H5P topic from a .h5p package",
    description: "Uploads the package to the H5P service (POST /h5p/contents/upload, field h5p_file) and creates a topic for the new content.",
    endpoints: ["POST /api/admin/topics"],
    input: z.object({ ...base, package: z.string().describe("Local .h5p file.") }),
    output: z.unknown(),
    examples: [{ title: "An H5P activity", argv: "topics create-h5p --lesson 12 --title Quiz --package ./quiz.h5p --json" }],
    plan: plan(["POST /h5p/contents/upload", "POST /api/admin/topics (H5P)"]),
    async run(ctx, i) {
      const up = (await uploadFile(ctx, "/h5p/contents/upload", "h5p_file", i.package)).data as { contentId?: string | number };
      const body = up as { contentId?: string | number; data?: { contentId?: string | number } };
      const id = Number(body.contentId ?? body.data?.contentId);
      if (!Number.isFinite(id)) throw new CliError("SERVER_ERROR", "The H5P service did not return a content id.");
      return { data: { topic: await createTopic(ctx, i, { type: "h5p", value: id }), contentId: id } };
    },
  }),
  defineCommand({
    ...common,
    id: "topics.create-liascript",
    summary: "Create a LiaScript topic from Markdown or a .zip",
    endpoints: ["POST /api/admin/liascript", "POST /api/admin/topics"],
    input: z.object({
      ...base,
      markdown: z.string().optional().meta({ fileInput: true }).describe("LiaScript Markdown, or @path."),
      file: z.string().optional().describe("Local .zip with the course and its assets."),
    }),
    output: z.unknown(),
    examples: [{ title: "A LiaScript course", argv: "topics create-liascript --lesson 12 --title Lia --markdown @course.md --json" }],
    plan: plan(["POST /api/admin/liascript", "POST /api/admin/topics (LiaScriptTopic)"]),
    async run(ctx, i) {
      if ((i.markdown === undefined) === (i.file === undefined)) throw new CliError("INPUT_INVALID", "Pass exactly one of --markdown or --file.");
      const doc = (
        i.file
          ? await uploadFile(ctx, "/api/admin/liascript", "file", i.file, { title: i.title })
          : await ctx.client.call("POST", "/api/admin/liascript", { body: { markdown: i.markdown, title: i.title }, signal: ctx.signal })
      ).data as { id?: number };
      if (!doc.id) throw new CliError("SERVER_ERROR", "The API did not return a LiaScript document id.");
      return { data: { topic: await createTopic(ctx, i, { type: "liascript", value: doc.id }), document: doc } };
    },
  }),
  defineCommand({
    ...common,
    id: "topics.create-interactive",
    summary: "Create an Interactive topic from a package .zip (or an uploaded package)",
    description:
      "An Interactive topic plays an uploaded web app (3D scene, map, simulation) in a sandbox, inline or as the background of the page. Pass --file to upload a package (a .zip with ulams-interactive.json) or --package to use an uploaded one. The topic pins the package's current version unless --version is given; --follow-latest plays every new upload instead. --start-step and --end-step choose the steps (ids from the manifest); the topic completes by --completion (default on_range_end). A manifest that lists network origins needs --accept-network.",
    endpoints: ["POST /api/admin/interactive", "POST /api/admin/topics"],
    input: z.object({
      ...base,
      file: z.string().optional().describe("Local .zip of a new package."),
      package: z.number().int().optional().describe("Id of an uploaded package (from `ulams interactive list`)."),
      version: z.number().int().min(1).optional().describe("Pin this version of the package (default: the current one)."),
      followLatest: z.boolean().optional().describe("Play the package's current version instead of pinning one."),
      startStep: z.string().optional().describe("First step id of the range."),
      endStep: z.string().optional().describe("Last step id of the range."),
      completion: z.enum(["on_open", "on_range_end", "on_complete", "on_score"]).default("on_range_end").describe("When the topic completes."),
      passScore: z.number().int().min(0).max(100).optional().describe("Pass percentage for --completion on_score."),
      display: z.enum(["inline", "background"]).default("inline").describe("Inline in the lesson, or the background of the page."),
      height: z.number().int().min(240).max(2000).optional().describe("Frame height in px (inline)."),
      text: z.string().optional().meta({ fileInput: true }).describe("Lesson text in Markdown, or @path."),
      acceptNetwork: z.boolean().optional().describe("Accept the network origins the manifest lists."),
    }),
    output: z.unknown(),
    examples: [
      { title: "A step of a 3D scene as the page background", argv: "topics create-interactive --lesson 12 --title \"Too slow\" --file gravity.zip --start-step too-slow --end-step too-slow --display background --json" },
      { title: "Another topic on an uploaded package", argv: "topics create-interactive --lesson 12 --title \"Too fast\" --package 3 --start-step too-fast --end-step too-fast --json" },
    ],
    plan: plan(["POST /api/admin/interactive (when --file)", "POST /api/admin/topics (InteractiveTopic)"]),
    async run(ctx, i) {
      if ((i.file === undefined) === (i.package === undefined)) throw new CliError("INPUT_INVALID", "Pass exactly one of --file or --package.");
      if (i.completion === "on_score" && i.passScore === undefined) throw new CliError("INPUT_INVALID", "--completion on_score needs --pass-score.");
      let packageId = i.package;
      let uploaded: unknown;
      if (i.file) {
        uploaded = (await uploadFile(ctx, "/api/admin/interactive", "file", i.file, { ...(i.acceptNetwork ? { accept_network: 1 } : {}) })).data;
        packageId = (uploaded as { id?: number }).id;
      }
      if (!packageId) throw new CliError("SERVER_ERROR", "The API did not return an interactive package id.");
      const extra = {
        ...(i.followLatest ? { follow_latest: 1 } : {}),
        ...(i.version !== undefined && !i.followLatest ? { version: i.version } : {}),
        ...(i.startStep !== undefined ? { start_step: i.startStep } : {}),
        ...(i.endStep !== undefined ? { end_step: i.endStep } : {}),
        completion_rule: i.completion,
        ...(i.passScore !== undefined ? { pass_score: i.passScore } : {}),
        display: i.display,
        ...(i.height !== undefined ? { height: i.height } : {}),
        ...(i.text !== undefined ? { text: i.text } : {}),
      };
      return { data: { topic: await createTopic(ctx, i, { type: "interactive", value: packageId, extra }), package: uploaded ?? { id: packageId } } };
    },
  }),
  defineCommand({
    ...common,
    id: "topics.create-layout",
    summary: "Create a Layout topic from a document of learning components (flip cards, timelines, practice activities)",
    description:
      "A Layout topic is a lesson body built from approved learning components instead of prose: Callout, Steps, ComparisonTable, H5PFrame, LiaScriptLesson, Timeline, FlipCards, CodeBlock and PracticeActivity (practice must use PracticeActivity). --document is a JSON (or YAML) list of nodes, [{ \"component\": \"Timeline\", \"props\": {...}, \"id\": \"optional\" }, ...], inline or @path; the props of every component are listed in the learner layout manifest (front/ui/catalogue/learner-layout-manifest.json). --fallback is the Markdown shown by clients that do not render layouts and when the document cannot be rendered (inline or @path); leave hints and worked solutions out of it. The API validates the document and answers 422 with the node and prop path of every problem. The topic completes when the learner has viewed it, or after the first checked attempt when it holds a PracticeActivity.",
    endpoints: ["POST /api/admin/topics"],
    input: z.object({
      ...base,
      document: z.array(z.record(z.string(), z.unknown())).min(1).describe("Layout nodes: a JSON list of { component, props, id? }, inline or @path."),
      fallback: z.string().min(1).meta({ fileInput: true }).describe("Markdown fallback, or @path to a Markdown file."),
    }),
    output: z.unknown(),
    examples: [
      { title: "Flip cards and a timeline for a chapter", argv: "topics create-layout --lesson 12 --title \"Coffee through time\" --document @layout.json --fallback @layout.md --json" },
    ],
    plan: plan(["POST /api/admin/topics (LayoutTopic)"]),
    async run(ctx, i) {
      return { data: await createTopic(ctx, i, { type: "layout", value: undefined, extra: { document: i.document, markdown_fallback: i.fallback } }) };
    },
  }),
  defineCommand({
    ...common,
    id: "topics.create-quiz",
    summary: "Create a quiz topic with questions from a YAML or JSON file",
    description:
      "Input: { value?, maxAttempts?, minPassScore?, questions: [{ prompt, options: [{ text, correct }], score? } | { gift, score? }] }. Multiple choice questions are converted to GIFT; raw `gift` strings pass through.",
    endpoints: ["POST /api/admin/topics", "POST /api/admin/gift-questions"],
    input: z.object({
      ...base,
      maxAttempts: z.number().int().optional(),
      minPassScore: z.number().optional(),
      questions: z.array(z.record(z.string(), z.unknown())).describe("Questions (see the command description)."),
    }),
    output: z.unknown(),
    examples: [{ title: "A quiz", argv: "topics create-quiz --lesson 12 --title Check --input @quiz.yaml --json" }],
    plan: plan(["POST /api/admin/topics (GiftQuiz)", "POST /api/admin/gift-questions x N"]),
    async run(ctx, i) {
      const questions = i.questions.map((q, n) => toGift(q, n + 1));
      const topic = (await createTopic(ctx, i, {
        type: "quiz",
        value: i.title,
        extra: { ...(i.maxAttempts !== undefined ? { max_attempts: i.maxAttempts } : {}), ...(i.minPassScore !== undefined ? { min_pass_score: i.minPassScore } : {}) },
      })) as { topicable_id?: number; topicable?: { id?: number } };
      const quizId = topic.topicable?.id ?? topic.topicable_id;
      if (!quizId) throw new CliError("SERVER_ERROR", "The API did not return the quiz id.");
      const created: unknown[] = [];
      for (const [n, q] of questions.entries()) {
        created.push((await ctx.client.call("POST", "/api/admin/gift-questions", { body: { topic_gift_quiz_id: quizId, value: q.gift, score: q.score, order: n + 1 }, signal: ctx.signal })).data);
      }
      return { data: { topic, questions: created } };
    },
  }),
  defineCommand({
    ...common,
    id: "topics.create",
    summary: "Create a topic of any registered type from raw fields (project, LTI, custom types)",
    description: "Escape hatch for types without a dedicated command: pass topicable (the class name from `ulams topics types`), value and any extra fields in --input.",
    endpoints: ["POST /api/admin/topics"],
    input: z.looseObject({ ...base, topicable: z.string().describe("Topic content class, e.g. Ulams\\TopicTypeProject\\Models\\Project."), value: z.unknown().optional() }),
    output: z.unknown(),
    examples: [{ title: "A project topic", argv: "topics create --lesson 12 --title Capstone --topicable 'Ulams\\TopicTypeProject\\Models\\Project' --value 'Build X' --json" }],
    plan: plan(["POST /api/admin/topics"]),
    async run(ctx, i) {
      const { lesson, title, order, summary, preview, active, canSkip, duration, topicable, value, ...rest } = i as Record<string, unknown> & Base & { topicable: string };
      const b: Base = { lesson, title, ...(order !== undefined ? { order } : {}), ...(summary !== undefined ? { summary } : {}), ...(preview !== undefined ? { preview } : {}), ...(active !== undefined ? { active } : {}), ...(canSkip !== undefined ? { canSkip } : {}), ...(duration !== undefined ? { duration } : {}) };
      const ord = b.order ?? (await nextOrder(ctx, b.lesson));
      return { data: (await ctx.client.call("POST", "/api/admin/topics", { body: { ...baseFields(b, ord), topicable_type: topicable, value, ...rest }, signal: ctx.signal })).data };
    },
  }),
];

/** Friendly question to GIFT. */
export function toGift(q: Record<string, unknown>, n: number): { gift: string; score: number } {
  const score = typeof q.score === "number" ? q.score : 1;
  if (typeof q.gift === "string") return { gift: q.gift, score };
  const prompt = String(q.prompt ?? "");
  const options = q.options as Array<{ text: string; correct?: boolean }> | undefined;
  if (!prompt || !options?.length) throw new CliError("INPUT_INVALID", `Question ${n} needs a prompt and options, or a gift string.`);
  const esc = (s: string) => s.replace(/([~=#{}:])/g, "\\$1");
  const body = options.map((o) => `${o.correct ? "=" : "~"}${esc(o.text)}`).join(" ");
  return { gift: `::Q${n}:: ${esc(prompt)} {${body}}`, score };
}
