import { z } from "zod";
import { CliError } from "../errors.ts";
import { idleMs, streamEvents, readStoredEvents, interviewFromEvents, type InterviewQuestion } from "../http/events.ts";
import { waitOperation } from "../http/lro.ts";
import type { AnyCommand, Ctx, Kind, Plan, Result } from "../registry/types.ts";
import {
  builderCall,
  flattenBlueprint,
  getRun,
  getSession,
  listVersions,
  outlineView,
  proposedVersion,
  runHandle,
  sessionView,
  waitForSession,
  type RunStatus,
  type SessionState,
} from "./builder-lib.ts";
import { defineCommand } from "./define.ts";

/* The course builder from the command line (plan 7.4). Every command is also an MCP tool (toolset `builder`).
 * The routes are not in the OpenAPI spec yet (`undocumented`); the coverage check lists them. */

const common = { audience: ["author" as const], mcp: { toolset: "builder" } };
const READ = ["builder:read"];
const WRITE = ["builder:write"];
const STABLE = ["interviewing", "outline_review", "apply_review", "applied"];

const session = z.string().describe("Builder session id (from `ulams builder sessions list`).");

function planOf(method: string, path: string, body?: unknown): Plan {
  return { request: { method, path, ...(body !== undefined ? { body } : {}) } };
}

interface RunAccepted {
  runId: string | null;
  accepted: boolean;
  message: string | null;
}

const summariseRun = (run: RunStatus) => ({
  id: run.id,
  kind: run.kind,
  status: run.status,
  stage: run.stage,
  needsAttention: run.needsAttention,
  steps: {
    total: run.steps.length,
    succeeded: run.steps.filter((s) => s.status === "succeeded").length,
    failed: run.steps.filter((s) => s.status === "failed").map((s) => ({ id: s.id, name: s.name, error: s.error })),
  },
  startedAt: run.startedAt,
  finishedAt: run.finishedAt,
  error: run.error,
});

/** After a request that started a run: return its handle, or wait and report the run and the session. */
export async function finishRun(ctx: Ctx, started: Record<string, unknown>, runId: string | null | undefined, sessionId?: string): Promise<Result> {
  const { state: _state, ...rest } = started as { state?: unknown } & Record<string, unknown>;
  void _state;
  if (!runId) {
    const id = sessionId ?? ((started.state as SessionState | undefined)?.session.id as string | undefined);
    const view = id ? sessionView(await getSession(ctx, id)) : undefined;
    return { data: { ...rest, runId: null, ...(view ? { session: view } : {}) } };
  }
  const handle = runHandle(runId);
  if (!ctx.flags.wait) return { data: { ...rest, runId }, meta: { operation: handle } };
  const done = await waitOperation(ctx, handle);
  const run = done.data as RunStatus;
  const view = sessionView(await getSession(ctx, sessionId ?? run.sessionId));
  return { data: { ...rest, runId, run: summariseRun(run), session: view } };
}

async function action(ctx: Ctx, sessionId: string, name: string, surfaceId: string, context: Record<string, unknown>): Promise<RunAccepted> {
  return builderCall<RunAccepted>(ctx, "POST", "/sessions/{session}/runs", {
    params: { session: sessionId },
    body: { threadId: sessionId, runId: `cli-${Date.now()}`, messages: [], tools: [], context: [], forwardedProps: { action: { name, surfaceId, context } } },
  });
}

function refused(result: RunAccepted, what: string): never {
  throw new CliError("CONFLICT", result.message ?? `The builder did not accept ${what}.`, { hint: "Run `ulams builder interview show <session>` to see what it is waiting for." });
}

/* ------------------------------------------------------------------ sources */

const EXTENSIONS: Record<string, string> = { "text/markdown": ".md", "text/x-markdown": ".md", "text/plain": ".md", "application/pdf": ".pdf", "application/vnd.openxmlformats-officedocument.wordprocessingml.document": ".docx" };

interface SourceFile {
  name: string;
  bytes: Uint8Array;
}

async function fetchSource(ctx: Ctx, url: string): Promise<SourceFile> {
  let parsed: URL;
  try {
    parsed = new URL(url);
  } catch {
    throw new CliError("INPUT_INVALID", `"${url}" is not a URL.`);
  }
  if (!/^https?:$/.test(parsed.protocol)) throw new CliError("INPUT_INVALID", "Only http and https URLs can be a source.");
  const doFetch = ctx.client.fetchImpl ?? ((input: RequestInfo | URL, init?: RequestInit) => fetch(input, init));
  let response: Response;
  try {
    response = await doFetch(url, { signal: ctx.signal, redirect: "follow", headers: { Accept: "text/markdown, application/pdf, text/plain, */*" } });
  } catch (error) {
    throw new CliError("NETWORK", `Cannot fetch ${url}: ${(error as Error).message}`);
  }
  if (!response.ok) throw new CliError("INPUT_INVALID", `${url} answered ${response.status}.`, { hint: "The URL must return the document itself (Markdown, PDF or DOCX), for example a raw GitHub link." });
  const type = (response.headers.get("content-type") ?? "").split(";")[0]?.trim().toLowerCase() ?? "";
  if (type === "text/html") {
    throw new CliError("INPUT_INVALID", `${url} is a web page (HTML), not a document.`, { hint: "Use the raw file URL of a Markdown, PDF or DOCX document, or download it and pass --from." });
  }
  const bytes = new Uint8Array(await response.arrayBuffer());
  if (bytes.byteLength > 25 * 1024 * 1024) throw new CliError("INPUT_INVALID", `${url} is larger than 25 MB.`);
  let name = decodeURIComponent(parsed.pathname.split("/").filter(Boolean).pop() ?? "source");
  if (!/\.(md|markdown|pdf|docx)$/i.test(name)) name += EXTENSIONS[type] ?? ".md";
  return { name, bytes };
}

async function readSource(ctx: Ctx, path: string): Promise<SourceFile> {
  try {
    return { name: path.split(/[\\/]/).pop() ?? "source", bytes: await ctx.fs.readFile(path) };
  } catch {
    throw new CliError("INPUT_INVALID", `Cannot read ${path}.`, { hint: "Pass the path of a Markdown, PDF or DOCX file." });
  }
}

export async function uploadSource(ctx: Ctx, sessionId: string, file: SourceFile): Promise<{ source: { id: string; name: string; status: string } | null; runId: string | null }> {
  const form = new FormData();
  form.append("file", new Blob([file.bytes as BlobPart]), file.name);
  return builderCall(ctx, "POST", "/sessions/{session}/sources", { params: { session: sessionId }, form });
}

export async function addSources(ctx: Ctx, sessionId: string, files: string[], urls: string[]) {
  const uploaded: Array<{ name: string; sourceId: string | null; runId: string | null }> = [];
  for (const path of files) {
    const res = await uploadSource(ctx, sessionId, await readSource(ctx, path));
    uploaded.push({ name: path.split(/[\\/]/).pop() ?? path, sourceId: res.source?.id ?? null, runId: res.runId });
  }
  for (const url of urls) {
    const file = await fetchSource(ctx, url);
    const res = await uploadSource(ctx, sessionId, file);
    uploaded.push({ name: file.name, sourceId: res.source?.id ?? null, runId: res.runId });
  }
  return uploaded;
}

/* ------------------------------------------------------------------ interview */

async function interviewQuestions(ctx: Ctx, sessionId: string): Promise<InterviewQuestion[]> {
  return interviewFromEvents((await readStoredEvents(ctx, sessionId)).events);
}

const pendingQuestion = (q: InterviewQuestion) => ({ key: q.key, label: q.label, why: q.why, options: q.options, default: q.default, component: q.component });

async function answerInterview(ctx: Ctx, sessionId: string, answers: Record<string, unknown>, defaults: boolean) {
  const questions = await interviewQuestions(ctx, sessionId);
  if (questions.length === 0) {
    throw new CliError("CONFLICT", "The interview is not open for this session.", { hint: "It opens after the first source is read; check `ulams builder sessions get <session>`." });
  }
  const unknown = Object.keys(answers).filter((k) => !questions.some((q) => q.key === k));
  if (unknown.length) {
    throw new CliError("INPUT_INVALID", `Unknown interview question: ${unknown.join(", ")}.`, { details: { choices: questions.map((q) => q.key) }, hint: `Questions: ${questions.map((q) => q.key).join(", ")}.` });
  }
  const answered: string[] = [];
  let last: RunAccepted | null = null;
  for (const q of questions) {
    if (!(q.key in answers) || q.status === "answered") continue;
    last = await action(ctx, sessionId, "answer", "interview", { key: q.key, value: answers[q.key] });
    if (!last.accepted) refused(last, `the answer to ${q.key}`);
    answered.push(q.key);
  }
  let decided = false;
  if (defaults && (last?.runId ?? null) === null) {
    last = await action(ctx, sessionId, "decide_for_me", "interview", {});
    if (!last.accepted) refused(last, "the defaults");
    decided = true;
  }
  return { answered, decided, runId: last?.runId ?? null };
}

/* ------------------------------------------------------------------ the composite */

async function startFlow(
  ctx: Ctx,
  i: { session?: string; title?: string; from?: string[]; fromUrl?: string[]; answers?: Record<string, unknown>; defaults?: boolean; approveOutline?: boolean; apply?: boolean; publish?: boolean; overwrite?: boolean }
): Promise<Result> {
  const steps: Array<Record<string, unknown>> = [];
  const files = i.from ?? [];
  const urls = i.fromUrl ?? [];
  if (!i.session && files.length + urls.length === 0) {
    throw new CliError("INPUT_INVALID", "Pass a source with --from <file> or --from-url <url>, or continue a session with --session.", { hint: "Example: ulams builder start --from ./guide.md --defaults --approve-outline --apply" });
  }
  let sessionId = i.session;
  if (!sessionId) {
    const created = await builderCall<SessionState>(ctx, "POST", "/sessions", { body: i.title ? { title: i.title } : {} });
    sessionId = created.session.id;
    steps.push({ step: "create-session", sessionId });
  }
  const uploaded = await addSources(ctx, sessionId, files, urls);
  for (const u of uploaded) steps.push({ step: "add-source", ...u });

  if (!ctx.flags.wait) {
    const runId = uploaded.find((u) => u.runId)?.runId ?? null;
    return { data: { sessionId, steps, runId }, ...(runId ? { meta: { operation: runHandle(runId) } } : {}) };
  }

  let published = false;
  let pending: Record<string, unknown> | null = null;
  let state = await getSession(ctx, sessionId);
  for (let guard = 0; guard < 10; guard++) {
    state = await waitForSession(ctx, sessionId, STABLE);
    const status = state.session.status;
    if (status === "interviewing") {
      const answers = i.answers ?? {};
      if (Object.keys(answers).length === 0 && !i.defaults) {
        pending = { type: "interview", questions: (await interviewQuestions(ctx, sessionId)).filter((q) => q.status !== "answered").map(pendingQuestion) };
        break;
      }
      const result = await answerInterview(ctx, sessionId, answers, Boolean(i.defaults));
      steps.push({ step: "interview", ...result });
      if (!result.runId) {
        pending = { type: "interview", questions: (await interviewQuestions(ctx, sessionId)).filter((q) => q.status !== "answered").map(pendingQuestion) };
        break;
      }
      continue;
    }
    if (status === "outline_review") {
      if (!i.approveOutline) {
        const version = state.session.currentVersionId ? await builderCall<{ document: never }>(ctx, "GET", "/versions/{version}", { params: { version: state.session.currentVersionId } }) : null;
        pending = { type: "outline", versionId: state.session.currentVersionId, ...(version ? { outline: outlineView(version.document) } : {}) };
        break;
      }
      const v = await proposedVersion(ctx, sessionId, "outline");
      const res = await builderCall<{ runId: string | null }>(ctx, "POST", "/versions/{version}/approve", { params: { version: v.id }, body: { edits: [] } });
      steps.push({ step: "approve-outline", versionId: v.id, runId: res.runId });
      continue;
    }
    if (status === "apply_review") {
      if (!i.apply) {
        pending = { type: "apply", versionId: state.session.currentVersionId };
        break;
      }
      const res = await builderCall<{ runId: string }>(ctx, "POST", "/sessions/{session}/apply", { params: { session: sessionId }, body: i.overwrite ? { overwrite: true } : {} });
      steps.push({ step: "apply", runId: res.runId });
      continue;
    }
    if (status === "applied") {
      if (i.publish && !published) {
        const res = await builderCall<{ courseId: number; published: boolean }>(ctx, "POST", "/sessions/{session}/publish", { params: { session: sessionId } });
        steps.push({ step: "publish", ...res });
        published = true;
        state = await getSession(ctx, sessionId);
      }
      break;
    }
  }
  const view = sessionView(state);
  return {
    data: {
      sessionId,
      status: view.status,
      courseId: view.courseId,
      published,
      ...(pending ? { pending } : {}),
      ...(view.links ? { links: view.links } : {}),
      steps,
      session: view,
    },
  };
}

/* ------------------------------------------------------------------ commands */

const wait = { longRunning: { kind: "builder-run" } };

export const builderCommands: AnyCommand[] = [
  defineCommand({
    ...common,
    ...wait,
    id: "builder.start",
    summary: "Start a course from a source file or URL and drive it as far as you ask",
    description:
      "Creates a builder session, uploads the source(s), waits for the interview, then continues only as far as the flags say: --answers <file> or --defaults answers the interview, --approve-outline approves the outline, --apply creates the course (unpublished) and --publish publishes it. Without them it stops at the first question or review and returns it in data.pending (exit 0), so you or an agent can decide. AI proposes, the author approves: every stage past the interview needs its flag. Needs AI to be enabled on the instance (FEATURE_DISABLED otherwise). Use --no-wait to return right after the upload.",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: ["POST /api/admin/course-builder/sessions", "POST /api/admin/course-builder/sessions/{session}/sources", "GET /api/admin/course-builder/sessions/{session}", "POST /api/admin/course-builder/sessions/{session}/runs", "POST /api/admin/course-builder/versions/{version}/approve", "POST /api/admin/course-builder/sessions/{session}/apply", "POST /api/admin/course-builder/sessions/{session}/publish"],
    input: z.object({
      from: z.array(z.string()).optional().describe("Source file (Markdown, PDF or DOCX); repeat for several."),
      fromUrl: z.array(z.string()).optional().describe("URL of a Markdown, PDF or DOCX document (the file itself, e.g. a raw GitHub link)."),
      session: z.string().optional().describe("Continue this session instead of creating one."),
      title: z.string().optional().describe("Session title (default: the source's title)."),
      answers: z.record(z.string(), z.unknown()).optional().describe("Interview answers by question key (audience, level, duration, tone, assessments, language) as JSON, or @file.json / @file.yaml."),
      defaults: z.boolean().optional().describe("Let the builder decide every question that --answers leaves open."),
      approveOutline: z.boolean().optional().describe("Approve the proposed outline and generate the lessons."),
      apply: z.boolean().optional().describe("Apply the generated course to the academy (as an unpublished draft)."),
      publish: z.boolean().optional().describe("Publish the applied course."),
      overwrite: z.boolean().optional().describe("With --apply on an already applied course: overwrite admin edits."),
    }),
    output: z.unknown(),
    examples: [
      { title: "Until the first question", argv: "builder start --from ./guide.md --json" },
      { title: "A whole course from a Markdown file", argv: "builder start --from ./guide.md --defaults --approve-outline --apply --json" },
      { title: "Answer the interview from a file, stop at the outline", argv: "builder start --from ./guide.md --answers @answers.yaml --json" },
    ],
    plan: async (_ctx, i) => ({ note: "Creates a builder session and uploads the sources; later stages follow the flags.", request: { method: "POST", path: "/api/admin/course-builder/sessions", body: { title: i.title ?? null, sources: [...(i.from ?? []), ...(i.fromUrl ?? [])] } } }),
    run: (ctx, i) => startFlow(ctx, i),
  }),

  /* ---- sessions */
  defineCommand({
    ...common,
    id: "builder.sessions.list",
    summary: "List my builder sessions",
    kind: "read",
    scopes: READ,
    endpoints: ["GET /api/admin/course-builder/sessions"],
    input: z.object({}),
    output: z.unknown(),
    examples: [{ title: "My sessions", argv: "builder sessions list --fields id,title,status --json" }],
    async run(ctx) {
      return { data: await builderCall(ctx, "GET", "/sessions") };
    },
  }),
  defineCommand({
    ...common,
    id: "builder.sessions.get",
    summary: "Show a builder session: status, brief, sources, cost, links",
    description: "The status tells the next step: interviewing (answer), outline_review (approve), apply_review (apply), applied (publish). activeRunId is set while the builder works.",
    kind: "read",
    scopes: READ,
    endpoints: ["GET /api/admin/course-builder/sessions/{session}"],
    positionals: ["session"],
    input: z.object({ session }),
    output: z.unknown(),
    examples: [{ title: "A session", argv: "builder sessions get 01j9z3k8m2x4q7r5t6v8w0y1ab --json" }],
    async run(ctx, i) {
      const s = await getSession(ctx, i.session);
      return { data: { ...sessionView(s), brief: s.brief, briefRows: s.briefRows ?? [], cost: s.cost ?? null, aiEnabled: s.aiEnabled, budgetReached: Boolean(s.budgetReached), canUndo: Boolean(s.canUndo), canRedo: Boolean(s.canRedo) } };
    },
  }),
  defineCommand({
    ...common,
    id: "builder.sessions.create",
    summary: "Create an empty builder session",
    description: "Authors normally use `builder start`, which also uploads the source. At most 10 sessions per author per day (HTTP 429 after).",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: ["POST /api/admin/course-builder/sessions"],
    input: z.object({ title: z.string().optional().describe("Session title.") }),
    output: z.unknown(),
    examples: [{ title: "New session", argv: 'builder sessions create --title "Onboarding" --json' }],
    plan: async (_ctx, i) => planOf("POST", "/api/admin/course-builder/sessions", i.title ? { title: i.title } : {}),
    async run(ctx, i) {
      return { data: sessionView(await builderCall<SessionState>(ctx, "POST", "/sessions", { body: i.title ? { title: i.title } : {} })) };
    },
  }),
  defineCommand({
    ...common,
    id: "builder.sessions.delete",
    summary: "Delete a builder session (an applied course is kept)",
    kind: "destructive",
    idempotent: true,
    scopes: WRITE,
    endpoints: ["DELETE /api/admin/course-builder/sessions/{session}"],
    positionals: ["session"],
    input: z.object({ session }),
    output: z.unknown(),
    examples: [{ title: "Delete", argv: "builder sessions delete 01j9z3k8m2x4q7r5t6v8w0y1ab --yes --json" }],
    plan: async (_ctx, i) => planOf("DELETE", `/api/admin/course-builder/sessions/${i.session}`),
    async run(ctx, i) {
      return { data: await builderCall(ctx, "DELETE", "/sessions/{session}", { params: { session: i.session } }) };
    },
  }),
  defineCommand({
    ...common,
    id: "builder.sessions.wait",
    summary: "Wait until a session reaches a status with no run active",
    description: "Statuses: interviewing, outline_review, apply_review, applied (default: any of them). Fails fast when a run failed, with the run's error. Exit 10 on timeout (details.operation resumes it).",
    kind: "read",
    scopes: READ,
    endpoints: ["GET /api/admin/course-builder/sessions/{session}", "GET /api/admin/course-builder/runs/{run}"],
    positionals: ["session"],
    input: z.object({ session, for: z.string().optional().describe("Comma-separated target statuses (default: interviewing,outline_review,apply_review,applied).") }),
    output: z.unknown(),
    examples: [{ title: "Wait for the outline", argv: "builder sessions wait 01j9z3k8m2x4q7r5t6v8w0y1ab --for outline_review --json" }],
    async run(ctx, i) {
      const targets = i.for ? i.for.split(",").map((s) => s.trim()).filter(Boolean) : STABLE;
      return { data: sessionView(await waitForSession(ctx, i.session, targets)) };
    },
  }),

  /* ---- sources and fragments */
  defineCommand({
    ...common,
    ...wait,
    id: "builder.sources.add",
    summary: "Add a source (Markdown, PDF or DOCX) to a session, from a file or a URL",
    description: "Uploading starts ingestion; the interview starts by itself after the first source. Waits for ingestion (use --no-wait to return the run handle). The sources of one session may add up to about 400,000 tokens.",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: ["POST /api/admin/course-builder/sessions/{session}/sources"],
    positionals: ["session"],
    input: z.object({
      session,
      file: z.array(z.string()).optional().describe("Local file; repeat for several."),
      url: z.array(z.string()).optional().describe("URL of the document itself; repeat for several."),
    }),
    output: z.unknown(),
    examples: [{ title: "Upload a file", argv: "builder sources add 01j9z3k8m2x4q7r5t6v8w0y1ab --file ./handbook.pdf --json" }],
    plan: async (_ctx, i) => ({ note: "Uploads the files.", request: { method: "POST", path: `/api/admin/course-builder/sessions/${i.session}/sources`, body: { files: i.file ?? [], urls: i.url ?? [] } } }),
    async run(ctx, i) {
      if (!(i.file?.length || i.url?.length)) throw new CliError("INPUT_INVALID", "Pass --file <path> or --url <url>.");
      const uploaded = await addSources(ctx, i.session, i.file ?? [], i.url ?? []);
      if (ctx.flags.wait) for (const u of uploaded) if (u.runId) await waitOperation(ctx, runHandle(u.runId));
      const state = await getSession(ctx, i.session);
      const last = uploaded.filter((u) => u.runId).at(-1);
      return { data: { uploaded, session: sessionView(state) }, ...(!ctx.flags.wait && last?.runId ? { meta: { operation: runHandle(last.runId) } } : {}) };
    },
  }),
  defineCommand({
    ...common,
    id: "builder.sources.get",
    summary: "Show a source with its section tree",
    kind: "read",
    scopes: READ,
    endpoints: ["GET /api/admin/course-builder/sessions/{session}/sources/{source}"],
    positionals: ["session", "source"],
    input: z.object({ session, source: z.string().describe("Source id (from `builder sessions get`).") }),
    output: z.unknown(),
    examples: [{ title: "A source", argv: "builder sources get <session> <source> --json" }],
    async run(ctx, i) {
      return { data: await builderCall(ctx, "GET", "/sessions/{session}/sources/{source}", { params: { session: i.session, source: i.source } }) };
    },
  }),
  defineCommand({
    ...common,
    id: "builder.fragments.get",
    summary: "Show the source passage a citation points to (frg_...)",
    kind: "read",
    scopes: READ,
    endpoints: ["GET /api/admin/course-builder/fragments/{fragment}"],
    positionals: ["fragment"],
    input: z.object({ fragment: z.string().describe("Fragment id, e.g. frg_abcdefabcdef.") }),
    output: z.unknown(),
    examples: [{ title: "A citation", argv: "builder fragments get frg_abcdefabcdef --json" }],
    async run(ctx, i) {
      return { data: await builderCall(ctx, "GET", "/fragments/{fragment}", { params: { fragment: i.fragment } }) };
    },
  }),

  /* ---- brief */
  defineCommand({
    ...common,
    id: "builder.brief.get",
    summary: "Show the Course Brief (audience, level, length, tone, assessments, language)",
    kind: "read",
    scopes: READ,
    endpoints: ["GET /api/admin/course-builder/sessions/{session}/brief"],
    positionals: ["session"],
    input: z.object({ session }),
    output: z.unknown(),
    examples: [{ title: "The brief", argv: "builder brief get <session> --json" }],
    async run(ctx, i) {
      return { data: await builderCall(ctx, "GET", "/sessions/{session}/brief", { params: { session: i.session } }) };
    },
  }),
  defineCommand({
    ...common,
    id: "builder.brief.set",
    summary: "Change fields of the Course Brief",
    description: "Editing the brief after the outline marks the outline and lessons stale: nothing is regenerated silently. Pass fields as flags or a whole `brief` object (JSON or @file).",
    kind: "write",
    idempotent: true,
    scopes: WRITE,
    endpoints: ["PUT /api/admin/course-builder/sessions/{session}/brief"],
    positionals: ["session"],
    input: z.object({
      session,
      brief: z.record(z.string(), z.unknown()).optional().describe("Brief fields as an object."),
      audience: z.string().optional(),
      level: z.enum(["beginner", "intermediate", "advanced"]).optional(),
      tone: z.enum(["friendly", "professional", "playful", "academic"]).optional(),
      language: z.string().optional().describe("Two-letter language code, e.g. en, pl."),
      totalMinutes: z.number().int().optional().describe("Target course length in minutes."),
      lessonMinutes: z.number().int().optional().describe("Target lesson length in minutes."),
      notes: z.string().optional(),
      perLessonQuiz: z.boolean().optional().describe("A quiz after every lesson."),
      finalTest: z.boolean().optional().describe("A final test."),
    }),
    output: z.unknown(),
    examples: [{ title: "Shorter lessons", argv: "builder brief set <session> --lesson-minutes 5 --level beginner --json" }],
    plan: async (_ctx, i) => planOf("PUT", `/api/admin/course-builder/sessions/${i.session}/brief`, { brief: briefBody(i) }),
    async run(ctx, i) {
      const brief = briefBody(i);
      if (Object.keys(brief).length === 0) throw new CliError("INPUT_INVALID", "Pass at least one brief field, e.g. --level beginner.");
      return { data: await builderCall(ctx, "PUT", "/sessions/{session}/brief", { params: { session: i.session }, body: { brief } }) };
    },
  }),

  /* ---- interview */
  defineCommand({
    ...common,
    id: "builder.interview.show",
    summary: "Show the interview questions and which are still open",
    description: "Read from the session's event stream. Each question has a key, options and a default; answer with `builder interview answer`.",
    kind: "read",
    scopes: READ,
    endpoints: ["GET /api/admin/course-builder/sessions/{session}/events"],
    positionals: ["session"],
    input: z.object({ session }),
    output: z.unknown(),
    examples: [{ title: "Open questions", argv: "builder interview show <session> --json" }],
    async run(ctx, i) {
      const questions = await interviewQuestions(ctx, i.session);
      const open = questions.filter((q) => q.status !== "answered");
      return { data: { questions, open: open.map((q) => q.key), next: open[0] ? pendingQuestion(open[0]) : null } };
    },
  }),
  defineCommand({
    ...common,
    ...wait,
    id: "builder.interview.answer",
    summary: "Answer interview questions (one --key/--value, or --answers as JSON or a file)",
    description:
      "Values: audience is free text; level is beginner, intermediate or advanced; tone is friendly, professional, playful or academic; language is a two-letter code; duration is \"60|10\" (total|lesson minutes) or {totalMinutes, lessonMinutes}; assessments is [\"quiz\", \"final\"]. With --defaults the builder decides the questions you leave open. When the last question is answered the outline starts; this waits for it (--no-wait returns the run handle).",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: ["POST /api/admin/course-builder/sessions/{session}/runs"],
    positionals: ["session"],
    input: z.object({
      session,
      key: z.string().optional().describe("Question key (see `builder interview show`)."),
      value: z.unknown().optional().describe("The answer to --key."),
      answers: z.record(z.string(), z.unknown()).optional().describe("Several answers by question key, as JSON or @file.json / @file.yaml."),
      defaults: z.boolean().optional().describe("Let the builder decide every open question."),
    }),
    output: z.unknown(),
    examples: [
      { title: "One answer", argv: "builder interview answer <session> --key level --value beginner --json" },
      { title: "From a file, defaults for the rest", argv: "builder interview answer <session> --answers @answers.json --defaults --json" },
    ],
    plan: async (_ctx, i) => planOf("POST", `/api/admin/course-builder/sessions/${i.session}/runs`, { action: "answer", answers: collectAnswers(i), defaults: Boolean(i.defaults) }),
    async run(ctx, i) {
      const answers = collectAnswers(i);
      if (Object.keys(answers).length === 0 && !i.defaults) throw new CliError("INPUT_INVALID", "Pass --key and --value, --answers, or --defaults.");
      const result = await answerInterview(ctx, i.session, answers, Boolean(i.defaults));
      const out = await finishRun(ctx, { answered: result.answered, decided: result.decided }, result.runId, i.session);
      if (!result.runId) {
        const open = (await interviewQuestions(ctx, i.session)).filter((q) => q.status !== "answered");
        return { ...out, data: { ...(out.data as object), next: open[0] ? pendingQuestion(open[0]) : null } };
      }
      return out;
    },
  }),
  defineCommand({
    ...common,
    ...wait,
    id: "builder.interview.decide",
    summary: "Let the builder decide the open interview questions (or one with --key)",
    description: "Uses the default it proposed for each question (justified by the source). The outline starts when nothing is left open.",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: ["POST /api/admin/course-builder/sessions/{session}/runs"],
    positionals: ["session"],
    input: z.object({ session, key: z.string().optional().describe("Only this question.") }),
    output: z.unknown(),
    examples: [{ title: "Decide everything", argv: "builder interview decide <session> --json" }],
    plan: async (_ctx, i) => planOf("POST", `/api/admin/course-builder/sessions/${i.session}/runs`, { action: "decide_for_me", key: i.key ?? null }),
    async run(ctx, i) {
      const res = await action(ctx, i.session, "decide_for_me", "interview", i.key ? { key: i.key } : {});
      if (!res.accepted) refused(res, "the defaults");
      return finishRun(ctx, {}, res.runId, i.session);
    },
  }),
  defineCommand({
    ...common,
    ...wait,
    id: "builder.retry",
    summary: "Retry the interview or outline step that failed",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: ["POST /api/admin/course-builder/sessions/{session}/runs"],
    positionals: ["session"],
    input: z.object({ session }),
    output: z.unknown(),
    examples: [{ title: "Retry", argv: "builder retry <session> --json" }],
    plan: async (_ctx, i) => planOf("POST", `/api/admin/course-builder/sessions/${i.session}/runs`, { action: "retry" }),
    async run(ctx, i) {
      const res = await action(ctx, i.session, "retry", "", {});
      if (!res.accepted) refused(res, "the retry");
      return finishRun(ctx, {}, res.runId, i.session);
    },
  }),

  /* ---- outline */
  defineCommand({
    ...common,
    id: "builder.outline.show",
    summary: "Show the outline waiting for approval (objectives, modules, lessons)",
    kind: "read",
    scopes: READ,
    endpoints: ["GET /api/admin/course-builder/sessions/{session}/versions", "GET /api/admin/course-builder/versions/{version}"],
    positionals: ["session"],
    input: z.object({ session, version: z.string().optional().describe("A specific version id (default: the one waiting for a decision, else the current one).") }),
    output: z.unknown(),
    examples: [{ title: "The outline", argv: "builder outline show <session> --json" }],
    async run(ctx, i) {
      const list = await listVersions(ctx, i.session);
      const id =
        i.version ??
        [...list.versions].filter((v) => v.kind === "outline" && v.status === "proposed").sort((a, b) => b.number - a.number)[0]?.id ??
        list.currentVersionId;
      if (!id) throw new CliError("NOT_FOUND", "This session has no outline yet.", { hint: "Answer the interview first: `ulams builder interview show <session>`." });
      const v = await builderCall<{ id: string; number: number; kind: string; status: string; document: never }>(ctx, "GET", "/versions/{version}", { params: { version: id } });
      return { data: { versionId: v.id, number: v.number, kind: v.kind, status: v.status, outline: outlineView(v.document) } };
    },
  }),
  defineCommand({
    ...common,
    ...wait,
    id: "builder.outline.approve",
    summary: "Approve the outline and generate the lessons",
    description: "Optionally edit learning objectives first with --edit <objectiveId>=<new text> (repeatable). Generation takes a few minutes and costs model tokens (see `builder usage`); this waits for it (--no-wait returns the run handle). The result is a generated course in apply_review: nothing is in the academy yet.",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: ["POST /api/admin/course-builder/versions/{version}/approve", "GET /api/admin/course-builder/sessions/{session}/versions"],
    positionals: ["session"],
    input: z.object({
      session,
      version: z.string().optional().describe("Outline version id (default: the one waiting for approval)."),
      edit: z.array(z.string()).optional().describe("Edit an objective: <objectiveId>=<text>; repeat."),
    }),
    output: z.unknown(),
    examples: [{ title: "Approve", argv: "builder outline approve <session> --json" }],
    plan: async (_ctx, i) => planOf("POST", `/api/admin/course-builder/versions/${i.version ?? "<proposed outline>"}/approve`, { edits: parseEdits(i.edit) }),
    async run(ctx, i) {
      const id = i.version ?? (await proposedVersion(ctx, i.session, "outline")).id;
      const res = await builderCall<{ runId: string | null }>(ctx, "POST", "/versions/{version}/approve", { params: { version: id }, body: { edits: parseEdits(i.edit) } });
      return finishRun(ctx, { versionId: id }, res.runId, i.session);
    },
  }),
  defineCommand({
    ...common,
    ...wait,
    id: "builder.outline.request-changes",
    summary: "Reject the outline with a comment; the builder proposes a new one",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: ["POST /api/admin/course-builder/versions/{version}/reject", "GET /api/admin/course-builder/sessions/{session}/versions"],
    positionals: ["session"],
    input: z.object({ session, comment: z.string().describe("What to change, in plain words."), version: z.string().optional().describe("Outline version id (default: the one waiting for approval).") }),
    output: z.unknown(),
    examples: [{ title: "Ask for changes", argv: 'builder outline request-changes <session> --comment "Fewer modules, more practice" --json' }],
    plan: async (_ctx, i) => planOf("POST", `/api/admin/course-builder/versions/${i.version ?? "<proposed outline>"}/reject`, { comment: i.comment }),
    async run(ctx, i) {
      const id = i.version ?? (await proposedVersion(ctx, i.session, "outline")).id;
      const res = await builderCall<{ runId: string | null }>(ctx, "POST", "/versions/{version}/reject", { params: { version: id }, body: { comment: i.comment } });
      return finishRun(ctx, { versionId: id }, res.runId, i.session);
    },
  }),

  /* ---- apply and publish */
  defineCommand({
    ...common,
    ...wait,
    id: "builder.apply",
    summary: "Apply the generated course to the academy (an unpublished draft)",
    description: "Creates or updates the course through the course services. Waits until the applied version is the current one. Pass --overwrite to replace edits an admin made in the course since the last apply.",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: ["POST /api/admin/course-builder/sessions/{session}/apply"],
    positionals: ["session"],
    input: z.object({ session, overwrite: z.boolean().optional().describe("Overwrite admin edits.") }),
    output: z.unknown(),
    examples: [{ title: "Apply", argv: "builder apply <session> --json" }],
    plan: async (_ctx, i) => planOf("POST", `/api/admin/course-builder/sessions/${i.session}/apply`, i.overwrite ? { overwrite: true } : {}),
    async run(ctx, i) {
      const res = await builderCall<{ runId: string }>(ctx, "POST", "/sessions/{session}/apply", { params: { session: i.session }, body: i.overwrite ? { overwrite: true } : {} });
      return finishRun(ctx, {}, res.runId, i.session);
    },
  }),
  defineCommand({
    ...common,
    id: "builder.publish",
    summary: "Publish the applied course so learners can enrol",
    kind: "write",
    idempotent: true,
    scopes: WRITE,
    endpoints: ["POST /api/admin/course-builder/sessions/{session}/publish"],
    positionals: ["session"],
    input: z.object({ session }),
    output: z.unknown(),
    examples: [{ title: "Publish", argv: "builder publish <session> --json" }],
    plan: async (_ctx, i) => planOf("POST", `/api/admin/course-builder/sessions/${i.session}/publish`),
    async run(ctx, i) {
      return { data: await builderCall(ctx, "POST", "/sessions/{session}/publish", { params: { session: i.session } }) };
    },
  }),
  defineCommand({
    ...common,
    id: "builder.publish-check",
    summary: "List what blocks publishing the applied course, plus warnings",
    description: "Runs the same checks `builder publish` runs and returns data.blocking, data.warnings and data.facts without publishing anything.",
    kind: "read",
    scopes: READ,
    endpoints: ["GET /api/admin/course-builder/sessions/{session}/publish-check"],
    positionals: ["session"],
    input: z.object({ session }),
    output: z.unknown(),
    examples: [{ title: "Before publishing", argv: "builder publish-check <session> --json" }],
    async run(ctx, i) {
      return { data: await builderCall(ctx, "GET", "/sessions/{session}/publish-check", { params: { session: i.session } }) };
    },
  }),
  defineCommand({
    ...common,
    id: "builder.new-site",
    summary: "Create a new site (tenant) for the course in this session",
    description: "Starts creating a new site and returns its status (the request is queued). Only available to users allowed to create sites (a 403 otherwise). The slug defaults to the one in the Course Brief.",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: ["POST /api/admin/course-builder/sessions/{session}/new-site"],
    positionals: ["session"],
    input: z.object({ session, slug: z.string().optional().describe("Site slug (default: the brief's site slug)."), name: z.string().max(120).optional().describe("Site name.") }),
    output: z.unknown(),
    examples: [{ title: "A new site", argv: "builder new-site <session> --slug coffee-atlas --json" }],
    plan: async (_ctx, i) => planOf("POST", `/api/admin/course-builder/sessions/${i.session}/new-site`, { ...(i.slug ? { slug: i.slug } : {}), ...(i.name ? { name: i.name } : {}) }),
    async run(ctx, i) {
      return { data: await builderCall(ctx, "POST", "/sessions/{session}/new-site", { params: { session: i.session }, body: { ...(i.slug ? { slug: i.slug } : {}), ...(i.name ? { name: i.name } : {}) } }) };
    },
  }),

  /* ---- element chat and patches */
  defineCommand({
    ...common,
    ...wait,
    id: "builder.chat",
    summary: "Ask the builder to change one element; it proposes a patch you approve or reject",
    description:
      "Scope the instruction to an element id from `builder elements list` (a lesson, block, objective or question). The builder proposes a change with citations; nothing changes until `builder patches approve <version>`. Waits for the proposal and returns the version id and the element-aware diff.",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: ["POST /api/admin/course-builder/sessions/{session}/runs", "GET /api/admin/course-builder/sessions/{session}/versions", "GET /api/admin/course-builder/versions/{version}/diff"],
    positionals: ["session", "message"],
    input: z.object({ session, message: z.string().describe("What to change, in plain words."), element: z.string().describe("Element id (from `builder elements list`).") }),
    output: z.unknown(),
    examples: [{ title: "Shorten a block", argv: 'builder chat <session> "Make this shorter" --element blk_x1 --json' }],
    plan: async (_ctx, i) => planOf("POST", `/api/admin/course-builder/sessions/${i.session}/runs`, { message: i.message, selection: { elementId: i.element } }),
    async run(ctx, i) {
      const res = await builderCall<RunAccepted>(ctx, "POST", "/sessions/{session}/runs", {
        params: { session: i.session },
        body: { threadId: i.session, runId: `cli-${Date.now()}`, messages: [{ id: `m-${Date.now()}`, role: "user", content: i.message }], tools: [], context: [], forwardedProps: { selection: { elementId: i.element } } },
      });
      const out = await finishRun(ctx, { accepted: res.accepted }, res.runId, i.session);
      if (!res.runId || !ctx.flags.wait) return out;
      const version = await proposedVersion(ctx, i.session, "patch").catch(() => null);
      if (!version) return out;
      const diff = await builderCall<{ changes: unknown[] }>(ctx, "GET", "/versions/{version}/diff", { params: { version: version.id } });
      return { ...out, data: { ...(out.data as object), patch: { versionId: version.id, number: version.number, reason: version.reason, changes: diff.changes } } };
    },
  }),
  defineCommand({
    ...common,
    ...wait,
    id: "builder.patches.approve",
    summary: "Approve a proposed patch; an applied course is updated",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: ["POST /api/admin/course-builder/versions/{version}/approve"],
    positionals: ["version"],
    input: z.object({ version: z.string().describe("Patch version id (from `builder chat`).") }),
    output: z.unknown(),
    examples: [{ title: "Approve", argv: "builder patches approve <version> --json" }],
    plan: async (_ctx, i) => planOf("POST", `/api/admin/course-builder/versions/${i.version}/approve`, { edits: [] }),
    async run(ctx, i) {
      const res = await builderCall<{ runId: string | null; state: SessionState }>(ctx, "POST", "/versions/{version}/approve", { params: { version: i.version }, body: { edits: [] } });
      return finishRun(ctx, { versionId: i.version, state: res.state }, res.runId, res.state?.session?.id);
    },
  }),
  defineCommand({
    ...common,
    id: "builder.patches.reject",
    summary: "Reject a proposed patch",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: ["POST /api/admin/course-builder/versions/{version}/reject"],
    positionals: ["version"],
    input: z.object({ version: z.string().describe("Patch version id."), comment: z.string().optional() }),
    output: z.unknown(),
    examples: [{ title: "Reject", argv: "builder patches reject <version> --json" }],
    plan: async (_ctx, i) => planOf("POST", `/api/admin/course-builder/versions/${i.version}/reject`, { comment: i.comment ?? "" }),
    async run(ctx, i) {
      const res = await builderCall<{ runId: string | null; state: SessionState }>(ctx, "POST", "/versions/{version}/reject", { params: { version: i.version }, body: { comment: i.comment ?? "" } });
      return { data: { versionId: i.version, rejected: true, session: res.state ? sessionView(res.state) : null } };
    },
  }),
  defineCommand({
    ...common,
    id: "builder.elements.list",
    summary: "List the elements of the course (ids to scope `builder chat` to)",
    kind: "read",
    scopes: READ,
    endpoints: ["GET /api/admin/course-builder/sessions/{session}/versions", "GET /api/admin/course-builder/versions/{version}"],
    positionals: ["session"],
    input: z.object({ session, version: z.string().optional().describe("Version id (default: the current version)."), type: z.string().optional().describe("Only this type: lesson, module, objective, question, paragraph, callout, steps, code, table, example, quiz.") }),
    output: z.unknown(),
    examples: [{ title: "Lessons", argv: "builder elements list <session> --type lesson --json" }],
    async run(ctx, i) {
      const id = i.version ?? (await listVersions(ctx, i.session)).currentVersionId;
      if (!id) throw new CliError("NOT_FOUND", "This session has no blueprint yet.");
      const v = await builderCall<{ document: never }>(ctx, "GET", "/versions/{version}", { params: { version: id } });
      const all = flattenBlueprint(v.document);
      return { data: i.type ? all.filter((e) => e.type === i.type) : all };
    },
  }),

  /* ---- versions */
  defineCommand({
    ...common,
    id: "builder.versions.list",
    summary: "List the blueprint versions of a session",
    kind: "read",
    scopes: READ,
    endpoints: ["GET /api/admin/course-builder/sessions/{session}/versions"],
    positionals: ["session"],
    input: z.object({ session }),
    output: z.unknown(),
    examples: [{ title: "History", argv: "builder versions list <session> --json" }],
    async run(ctx, i) {
      return { data: await listVersions(ctx, i.session) };
    },
  }),
  defineCommand({
    ...common,
    id: "builder.versions.get",
    summary: "Show one version (without the document unless --document)",
    kind: "read",
    scopes: READ,
    endpoints: ["GET /api/admin/course-builder/versions/{version}"],
    positionals: ["version"],
    input: z.object({ version: z.string().describe("Version id."), document: z.boolean().optional().describe("Include the full blueprint document (large).") }),
    output: z.unknown(),
    examples: [{ title: "A version", argv: "builder versions get <version> --json" }],
    async run(ctx, i) {
      const v = await builderCall<Record<string, unknown>>(ctx, "GET", "/versions/{version}", { params: { version: i.version } });
      if (i.document) return { data: v };
      const { document, fragments, ...rest } = v;
      return { data: { ...rest, outline: outlineView(document as never), fragmentCount: Object.keys((fragments as object) ?? {}).length } };
    },
  }),
  defineCommand({
    ...common,
    id: "builder.versions.diff",
    summary: "Element-aware diff of a version against another (default: its parent)",
    kind: "read",
    scopes: READ,
    endpoints: ["GET /api/admin/course-builder/versions/{version}/diff"],
    positionals: ["version"],
    input: z.object({ version: z.string().describe("Version id."), against: z.string().optional().describe("Version id to compare with.") }),
    output: z.unknown(),
    examples: [{ title: "What changed", argv: "builder versions diff <version> --json" }],
    async run(ctx, i) {
      return { data: await builderCall(ctx, "GET", "/versions/{version}/diff", { params: { version: i.version }, query: { against: i.against } }) };
    },
  }),
  defineCommand({
    ...common,
    ...wait,
    id: "builder.versions.restore",
    summary: "Restore an old version as a new current version",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: ["POST /api/admin/course-builder/versions/{version}/restore"],
    positionals: ["version"],
    input: z.object({ version: z.string().describe("Version id.") }),
    output: z.unknown(),
    examples: [{ title: "Restore", argv: "builder versions restore <version> --json" }],
    plan: async (_ctx, i) => planOf("POST", `/api/admin/course-builder/versions/${i.version}/restore`),
    async run(ctx, i) {
      const res = await builderCall<{ currentVersionId: string; runId: string | null; state: SessionState }>(ctx, "POST", "/versions/{version}/restore", { params: { version: i.version } });
      return finishRun(ctx, { currentVersionId: res.currentVersionId, state: res.state }, res.runId, res.state?.session?.id);
    },
  }),
  ...(["undo", "redo"] as const).map((verb) =>
    defineCommand({
      ...common,
      ...wait,
      id: `builder.${verb}`,
      summary: verb === "undo" ? "Undo the last content change" : "Redo the change you undid",
      description: "An applied course is re-applied to match; this waits for it.",
      kind: "write",
      idempotent: false,
      scopes: WRITE,
      endpoints: [`POST /api/admin/course-builder/sessions/{session}/${verb}`],
      positionals: ["session"],
      input: z.object({ session }),
      output: z.unknown(),
      examples: [{ title: verb, argv: `builder ${verb} <session> --json` }],
      plan: async (_ctx, i: { session: string }) => planOf("POST", `/api/admin/course-builder/sessions/${i.session}/${verb}`),
      async run(ctx, i: { session: string }) {
        const res = await builderCall<{ currentVersionId: string; runId: string | null; state: SessionState }>(ctx, "POST", `/sessions/{session}/${verb}`, { params: { session: i.session } });
        return finishRun(ctx, { currentVersionId: res.currentVersionId, state: res.state }, res.runId, i.session);
      },
    })
  ),

  /* ---- runs */
  defineCommand({
    ...common,
    id: "builder.runs.get",
    summary: "Show the status of one run (queued, running, succeeded, failed, cancelled) with its steps",
    kind: "read",
    scopes: READ,
    endpoints: ["GET /api/admin/course-builder/runs/{run}"],
    positionals: ["run"],
    input: z.object({ run: z.string().describe("Run id.") }),
    output: z.unknown(),
    examples: [{ title: "A run", argv: "builder runs get <run> --json" }],
    async run(ctx, i) {
      return { data: await getRun(ctx, i.run) };
    },
  }),
  defineCommand({
    ...common,
    id: "builder.runs.cancel",
    summary: "Cancel a run that is still queued or running",
    kind: "write",
    idempotent: true,
    scopes: WRITE,
    endpoints: ["POST /api/admin/course-builder/runs/{run}/cancel"],
    positionals: ["run"],
    input: z.object({ run: z.string().describe("Run id.") }),
    output: z.unknown(),
    examples: [{ title: "Cancel", argv: "builder runs cancel <run> --json" }],
    plan: async (_ctx, i) => planOf("POST", `/api/admin/course-builder/runs/${i.run}/cancel`),
    async run(ctx, i) {
      return { data: await builderCall(ctx, "POST", "/runs/{run}/cancel", { params: { run: i.run } }) };
    },
  }),
  defineCommand({
    ...common,
    ...wait,
    id: "builder.runs.retry-step",
    summary: "Retry one failed generation step of a run",
    kind: "write",
    idempotent: false,
    scopes: WRITE,
    endpoints: ["POST /api/admin/course-builder/runs/{run}/steps/{step}/retry"],
    positionals: ["run", "step"],
    input: z.object({ run: z.string().describe("Run id."), step: z.string().describe("Step id (from `builder runs get`).") }),
    output: z.unknown(),
    examples: [{ title: "Retry a step", argv: "builder runs retry-step <run> <step> --json" }],
    plan: async (_ctx, i) => planOf("POST", `/api/admin/course-builder/runs/${i.run}/steps/${i.step}/retry`),
    async run(ctx, i) {
      const res = await builderCall<{ stepId: string; sessionId: string }>(ctx, "POST", "/runs/{run}/steps/{step}/retry", { params: { run: i.run, step: i.step } });
      return finishRun(ctx, res, i.run, res.sessionId);
    },
  }),

  /* ---- events and usage */
  defineCommand({
    ...common,
    id: "builder.events.list",
    summary: "The stored AG-UI events of a session (bounded read, for agents)",
    description: "Returns up to --limit events after --after (an event id). Use `builder events --follow` in a terminal to stream them as they happen.",
    kind: "read",
    scopes: READ,
    endpoints: ["GET /api/admin/course-builder/sessions/{session}/events"],
    positionals: ["session"],
    input: z.object({
      session,
      after: z.string().optional().describe("Only events after this event id."),
      types: z.string().optional().describe("Comma-separated AG-UI event types, e.g. RUN_FINISHED,RUN_ERROR."),
      limit: z.number().int().min(1).max(2000).optional().describe("Maximum number of events (default 200; the newest are kept)."),
    }),
    output: z.unknown(),
    examples: [{ title: "Failures", argv: "builder events list <session> --types RUN_ERROR --json" }],
    async run(ctx, i) {
      const { events, lastId } = await readStoredEvents(ctx, i.session, i.after ?? null);
      const types = i.types ? new Set(i.types.split(",").map((t) => t.trim())) : null;
      // the STATE_SNAPSHOT every connection starts with repeats the session: only on request
      const filtered = events.filter((e) => (types ? types.has(String(e.event.type)) : e.event.type !== "STATE_SNAPSHOT"));
      const limit = i.limit ?? 200;
      return { data: { events: filtered.slice(-limit).map((e) => ({ id: e.id, ...e.event })), lastEventId: lastId, total: filtered.length } };
    },
  }),
  defineCommand({
    ...common,
    id: "builder.events",
    summary: "Stream the session's AG-UI events as NDJSON (one {\"type\":\"event\",\"id\",\"data\"} line each)",
    description:
      "Without --follow it prints the stored events up to now and exits. With --follow it keeps streaming (resuming across the server's 25 s connection cap) until Ctrl-C or --timeout. --until-run <runId> stops after that run's RUN_FINISHED (exit 0) or RUN_ERROR (exit 9, the event is in error.details). The last line is the normal envelope with the event count and the last event id (resume with --after).",
    kind: "stream",
    idempotent: true,
    scopes: READ,
    endpoints: ["GET /api/admin/course-builder/sessions/{session}/events"],
    positionals: ["session"],
    input: z.object({
      session,
      follow: z.boolean().optional().describe("Keep streaming new events."),
      after: z.string().optional().describe("Resume after this event id."),
      types: z.string().optional().describe("Only these AG-UI event types (comma-separated), e.g. RUN_STARTED,RUN_FINISHED,RUN_ERROR,TEXT_MESSAGE_CONTENT."),
      untilRun: z.string().optional().describe("Stop after this run finishes."),
    }),
    output: z.unknown(),
    examples: [
      { title: "Follow a run", argv: "builder events <session> --follow --until-run <run> --output ndjson" },
      { title: "What happened so far", argv: "builder events <session> --types RUN_FINISHED,RUN_ERROR" },
    ],
    async run(ctx, i) {
      const types = i.types ? new Set(i.types.split(",").map((t) => t.trim())) : null;
      let count = 0;
      let failed: Record<string, unknown> | null = null;
      let finished = false;
      const live = Boolean(i.follow || i.untilRun);
      const { lastId, end } = await streamEvents(ctx, {
        sessionId: i.session,
        after: i.after ?? null,
        ...(live ? { maxMs: ctx.flags.timeout * 1000 } : { idleMs: idleMs(ctx) }),
        onEvent: ({ id, event }) => {
          if (!types || types.has(String(event.type))) {
            ctx.emit(event, { id });
            count++;
          }
          if (i.untilRun && event.runId === i.untilRun) {
            if (event.type === "RUN_FINISHED") {
              finished = true;
              return "stop";
            }
            if (event.type === "RUN_ERROR") {
              failed = event as Record<string, unknown>;
              return "stop";
            }
          }
        },
      });
      if (failed) {
        throw new CliError("SERVER_ERROR", `Run ${i.untilRun} failed: ${String((failed as { message?: unknown }).message ?? "error")}`, { retryable: false, details: { event: failed, lastEventId: lastId } });
      }
      if (i.untilRun && !finished && end === "timeout") {
        throw new CliError("TIMEOUT", `Run ${i.untilRun} did not finish within ${ctx.flags.timeout}s.`, { details: { operation: runHandle(i.untilRun), lastEventId: lastId } });
      }
      return { data: { events: count, lastEventId: lastId, end } };
    },
  }),
  defineCommand({
    ...common,
    id: "builder.usage",
    summary: "AI calls of the session by task and model: tokens and cost",
    description: "Cost is in micro-USD (1,000,000 = 1 USD). Every call is logged; nothing here is estimated.",
    kind: "read",
    scopes: READ,
    endpoints: ["GET /api/admin/course-builder/sessions/{session}/usage"],
    positionals: ["session"],
    input: z.object({ session }),
    output: z.unknown(),
    examples: [{ title: "Cost so far", argv: "builder usage <session> --json" }],
    async run(ctx, i) {
      return { data: await builderCall(ctx, "GET", "/sessions/{session}/usage", { params: { session: i.session } }) };
    },
  }),
];

/* ------------------------------------------------------------------ small helpers */

function briefBody(i: Record<string, unknown>): Record<string, unknown> {
  const out: Record<string, unknown> = { ...((i.brief as Record<string, unknown> | undefined) ?? {}) };
  for (const key of ["audience", "level", "tone", "language", "totalMinutes", "lessonMinutes", "notes"]) if (i[key] !== undefined) out[key] = i[key];
  const assessments: Record<string, unknown> = {};
  if (i.perLessonQuiz !== undefined) assessments.perLessonQuiz = i.perLessonQuiz;
  if (i.finalTest !== undefined) assessments.finalTest = i.finalTest;
  if (Object.keys(assessments).length) out.assessments = { ...((out.assessments as object | undefined) ?? {}), ...assessments };
  return out;
}

function collectAnswers(i: { key?: string; value?: unknown; answers?: Record<string, unknown> }): Record<string, unknown> {
  const answers = { ...(i.answers ?? {}) };
  if (i.key !== undefined) {
    if (i.value === undefined) throw new CliError("INPUT_INVALID", `--key ${i.key} needs --value.`);
    answers[i.key] = i.value;
  }
  return answers;
}

function parseEdits(edits: string[] | undefined): Array<{ objectiveId: string; text: string }> {
  return (edits ?? []).map((entry) => {
    const eq = entry.indexOf("=");
    if (eq < 1) throw new CliError("INPUT_INVALID", `--edit expects <objectiveId>=<text>, got "${entry}".`, { hint: "Objective ids come from `ulams builder outline show`." });
    return { objectiveId: entry.slice(0, eq), text: entry.slice(eq + 1) };
  });
}

export type { Kind };
