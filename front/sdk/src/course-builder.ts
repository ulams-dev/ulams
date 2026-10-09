/**
 * AI Course Builder client (`/api/admin/course-builder`, ADR 0010/0011). REST calls return the
 * unwrapped `data`; `events()` streams AG-UI events over SSE with resume. In the browser the
 * reference app points `baseUrl` at its BFF (`/studio/api`), which adds the author's token.
 */
import { ApiError, type ClientOptions } from "./client.ts";
import type { StalenessSummary } from "./living-course.ts";
import { connectEventStream, type AgUiEvent, type StreamStatus } from "./ag-ui.ts";

export type SessionStatus =
  | "draft"
  | "ingesting"
  | "interviewing"
  | "outlining"
  | "outline_review"
  | "generating"
  | "apply_review"
  | "applying"
  | "applied"
  | "failed";

export interface CourseBrief {
  audience: string;
  level: "beginner" | "intermediate" | "advanced";
  totalMinutes: number;
  lessonMinutes: number;
  tone: "friendly" | "professional" | "playful" | "academic";
  assessments: { perLessonQuiz: boolean; finalTest: boolean; passScore: number };
  language: string;
  notes?: string;
  /** Brief v2: a v1 brief has none of these (price reads as free, the site is left as it is). */
  theme?: { preset: "coffee" | "oncall" | "nightsky"; accent?: string };
  pricing?: { mode: "free" | "paid"; amountMinor?: number; currency?: string };
  site?: { mode: "current" | "new"; slug?: string };
  decidedBy: Record<string, "author" | "default"> | [];
}

export interface PublishCheck {
  blocking: Array<{ code: string; message: string }>;
  warnings: Array<{ code: string; message: string; elementId?: string }>;
  facts: {
    courseId: number | null;
    title: string | null;
    url: string | null;
    published: boolean;
    price: { mode: "free" | "paid"; amountMinor: number | null; currency: string | null; label: string; suggestion: { amountMinor: number; currency: string; rationale: string } | null };
    theme: { preset: string; accent?: string; adjustedAccent: string | null } | null;
    counts: { modules?: number; lessons?: number; minutes?: number; questions?: number };
    applyNotes: string[];
    landingValid: boolean;
  };
}

export interface BuilderCost {
  usedMicroUsd: number;
  budgetMicroUsd: number;
  inputTokens: number;
  outputTokens: number;
  cacheReadTokens: number;
  cacheWriteTokens: number;
  calls: number;
}

export interface BuilderSource {
  id: string;
  name: string;
  status: "uploaded" | "processing" | "ready" | "failed";
  kind: "markdown" | "pdf" | "docx";
  size: number;
  tokens: number;
  fragments: number;
  pages: number | null;
  title: string | null;
  error: string | null;
}

export interface SessionSummary {
  id: string;
  title: string | null;
  status: SessionStatus;
  courseId: number | null;
  currentVersionId: string | null;
  appliedVersionId: string | null;
  createdAt?: string;
  updatedAt?: string;
  costMicroUsd?: number;
  /** How far the course is behind its sources (added by the Living Course package; absent without it). */
  freshness?: StalenessSummary;
}

/**
 * True only when the session's applied version is its current version and the apply run has
 * finished. The `applied` event, an approved patch and a `status` of `applied` alone are not
 * enough: after an approved edit the status can still read `applied` while the re-apply of the
 * new version is queued, so the course in the academy is behind what the author approved.
 */
export function isApplied(session: Pick<SessionSummary, "status" | "currentVersionId" | "appliedVersionId"> | null | undefined): boolean {
  return Boolean(session && session.status === "applied" && session.appliedVersionId && session.appliedVersionId === session.currentVersionId);
}

export interface WaitForAppliedOptions {
  /** Wait until exactly this version is the applied one (from the `applied` event). */
  versionId?: string;
  timeoutMs?: number;
  intervalMs?: number;
  signal?: AbortSignal;
}

/** Raised by `sessions.waitForApplied` when the apply did not finish in time or failed. */
export class ApplyNotSettledError extends Error {
  constructor(message: string, readonly state?: BuilderState) {
    super(message);
    this.name = "ApplyNotSettledError";
  }
}

/** The AG-UI shared state (STATE_SNAPSHOT / STATE_DELTA). */
export interface BuilderState {
  session: SessionSummary;
  brief: CourseBrief | null;
  briefRows: Array<{ key: string; label: string; value: string }>;
  /** A model-suggested price for a paid course without an amount; the author confirms it in the brief. */
  priceSuggestion?: { amountMinor: number; currency: string; rationale: string; suggested: true } | null;
  /** Notes from the last apply (a skipped theme, a product that was not created). */
  applyNotes?: string[];
  cost: BuilderCost;
  sources: BuilderSource[];
  aiEnabled: boolean;
  profiles: { default: string; light: string };
  budgetReached: boolean;
  activeRunId: string | null;
  canUndo: boolean;
  canRedo: boolean;
  links: { adminPath?: string; learnerPath?: string; landingSlug?: string; admin?: string | null; learner?: string | null };
}

export interface BlueprintObjective {
  id: string;
  text: string;
  citations: string[];
}
export interface BlueprintBlock {
  id: string;
  kind: "paragraph" | "callout" | "steps" | "code" | "table" | "example";
  markdown: string;
  citations: string[];
  objectiveIds: string[];
}
export interface BlueprintQuestion {
  id: string;
  type: "single" | "multiple" | "truefalse" | "short";
  stem: string;
  options: Array<{ id: string; text: string; correct: boolean }>;
  explanation: string;
  citations: string[];
  objectiveIds: string[];
}
export interface BlueprintLesson {
  id: string;
  title: string;
  summary?: string;
  minutes: number;
  objectives: BlueprintObjective[];
  citations: string[];
  contentType: "richtext";
  status: "planned" | "generated" | "failed";
  blocks: BlueprintBlock[];
  quiz: { id: string; questions: BlueprintQuestion[] } | null;
  flags: string[];
}
export interface Blueprint {
  schemaVersion: 1;
  sources: Array<{ id: string; title: string; fragmentCount: number }>;
  course: {
    id: string;
    title: string;
    subtitle?: string;
    description?: string;
    language: string;
    objectives: BlueprintObjective[];
    faq?: Array<{ question: string; answer: string; citations: string[] }>;
  };
  modules: Array<{ id: string; title: string; summary?: string; lessons: BlueprintLesson[] }>;
  finalTest: { id: string; passScore?: number; questions: BlueprintQuestion[] } | null;
  pages: { landing: unknown; header: unknown };
}

export interface BlueprintVersion {
  id: string;
  number: number;
  kind: "outline" | "content" | "patch" | "author" | "restore" | "update";
  origin: "ai" | "author" | "restore";
  status: "proposed" | "approved" | "rejected" | "superseded";
  reason: string | null;
  parentId: string | null;
  elementId: string | null;
  document: Blueprint;
  /** Source revisions an `update` version applied: source id → revision id. */
  sourceRevisions?: Record<string, string> | null;
  /** fragment id → "§2.3 Title" */
  fragments: Record<string, string>;
  createdAt: string;
}

export interface Fragment {
  id: string;
  label: string;
  section: string | null;
  headingPath: string[];
  text: string;
  pageStart: number | null;
  pageEnd: number | null;
  source: { id: string; name: string };
}

/** A2UI action sent back from a surface. */
export interface A2uiAction {
  name: string;
  surfaceId: string;
  sourceComponentId?: string;
  context?: Record<string, unknown>;
}

export interface RunAccepted {
  runId: string | null;
  accepted: boolean;
  message: string | null;
}

export interface UsageRow {
  task: string;
  profile: string;
  profileLabel: string;
  model: string;
  calls: number;
  inputTokens: number;
  outputTokens: number;
  cacheReadTokens: number;
  cacheCreationTokens: number;
  costMicroUsd: number;
  latencyMs: number;
}

const PREFIX = "/api/admin/course-builder";

export type CourseBuilderClient = ReturnType<typeof createCourseBuilderClient>;

/**
 * `prefix` is where the builder API lives under `baseUrl`: the API path by default, or `""` when
 * `baseUrl` already points at a proxy of it (the studio BFF).
 */
export function createCourseBuilderClient(options: ClientOptions & { prefix?: string }) {
  const base = options.baseUrl.replace(/\/+$/, "") + (options.prefix ?? PREFIX);
  const doFetch = options.fetch ?? ((input: RequestInfo | URL, init?: RequestInit) => fetch(input, init));
  const auth = (): Record<string, string> => ({
    Accept: "application/json",
    ...options.headers,
    ...(options.token ? { Authorization: `Bearer ${options.token}` } : {}),
  });

  async function call<T>(method: string, path: string, body?: unknown, form?: FormData): Promise<T> {
    const headers = auth();
    let payload: BodyInit | undefined;
    if (form) payload = form;
    else if (body !== undefined) {
      headers["Content-Type"] = "application/json";
      payload = JSON.stringify(body);
    }
    let response: Response;
    try {
      response = await doFetch(`${base}${path}`, { method, headers, body: payload, signal: AbortSignal.timeout(options.timeoutMs ?? 60_000) });
    } catch (error) {
      throw new ApiError(0, path, null, `API unreachable on ${path}: ${(error as Error).message}`);
    }
    const text = await response.text();
    let json: { data?: T; message?: string } | null;
    try {
      json = text ? JSON.parse(text) : null;
    } catch {
      json = { message: text.slice(0, 200) };
    }
    if (!response.ok) throw new ApiError(response.status, path, json, json?.message ?? `API ${response.status} on ${path}`);
    return (json?.data ?? null) as T;
  }

  const id = (value: string) => encodeURIComponent(value);

  return {
    base,
    sessions: {
      list: () => call<SessionSummary[]>("GET", "/sessions"),
      create: (title?: string) => call<BuilderState>("POST", "/sessions", title ? { title } : {}),
      get: (sessionId: string) => call<BuilderState>("GET", `/sessions/${id(sessionId)}`),
      delete: (sessionId: string) => call<{ id: string }>("DELETE", `/sessions/${id(sessionId)}`),
      /**
       * Polls the session endpoint (the authoritative state) until the applied version has caught
       * up with the current one, so a UI never reports "applied" from an event that arrived first.
       */
      waitForApplied: async (sessionId: string, opts: WaitForAppliedOptions = {}): Promise<BuilderState> => {
        const deadline = Date.now() + (opts.timeoutMs ?? 20_000);
        const interval = opts.intervalMs ?? 500;
        for (;;) {
          const state = await call<BuilderState>("GET", `/sessions/${id(sessionId)}`);
          const settled = opts.versionId ? state.session.status === "applied" && state.session.appliedVersionId === opts.versionId : isApplied(state.session);
          if (settled) return state;
          if (state.session.status === "failed") throw new ApplyNotSettledError("The apply failed.", state);
          if (opts.signal?.aborted || Date.now() + interval > deadline) throw new ApplyNotSettledError("The apply did not finish in time.", state);
          await new Promise((resolve) => setTimeout(resolve, interval));
        }
      },
    },
    sources: {
      upload: (sessionId: string, file: Blob, filename: string) => {
        const form = new FormData();
        form.append("file", file, filename);
        return call<{ source: BuilderSource; runId: string | null }>("POST", `/sessions/${id(sessionId)}/sources`, undefined, form);
      },
      get: (sessionId: string, sourceId: string) => call<BuilderSource & { fragments: Array<Record<string, unknown>> }>("GET", `/sessions/${id(sessionId)}/sources/${id(sourceId)}`),
      fragment: (fragmentId: string) => call<Fragment>("GET", `/fragments/${id(fragmentId)}`),
    },
    brief: {
      get: (sessionId: string) => call<{ brief: CourseBrief; rows: BuilderState["briefRows"] }>("GET", `/sessions/${id(sessionId)}/brief`),
      update: (sessionId: string, brief: Partial<CourseBrief>) => call<{ brief: CourseBrief; stale: boolean }>("PUT", `/sessions/${id(sessionId)}/brief`, { brief }),
    },
    runs: {
      /** Sends an A2UI action (AG-UI RunAgentInput with `forwardedProps.action`). */
      action: (sessionId: string, action: A2uiAction) =>
        call<RunAccepted>("POST", `/sessions/${id(sessionId)}/runs`, {
          threadId: sessionId,
          runId: `client-${Date.now()}`,
          messages: [],
          tools: [],
          context: [],
          forwardedProps: { action },
        }),
      /** Sends typed text, optionally scoped to a selected blueprint element. */
      message: (sessionId: string, text: string, elementId?: string | null) =>
        call<RunAccepted>("POST", `/sessions/${id(sessionId)}/runs`, {
          threadId: sessionId,
          runId: `client-${Date.now()}`,
          messages: [{ id: `m-${Date.now()}`, role: "user", content: text }],
          tools: [],
          context: [],
          forwardedProps: elementId ? { selection: { elementId } } : {},
        }),
      cancel: (runId: string) => call<{ id: string; status: string }>("POST", `/runs/${id(runId)}/cancel`),
      retryStep: (runId: string, stepId: string) => call<{ stepId: string }>("POST", `/runs/${id(runId)}/steps/${id(stepId)}/retry`),
    },
    versions: {
      list: (sessionId: string) =>
        call<{ currentVersionId: string | null; appliedVersionId: string | null; versions: Array<Omit<BlueprintVersion, "document" | "fragments" | "parentId" | "elementId">> }>(
          "GET",
          `/sessions/${id(sessionId)}/versions`
        ),
      get: (versionId: string) => call<BlueprintVersion>("GET", `/versions/${id(versionId)}`),
      diff: (versionId: string, against?: string) => call<{ against: string | null; changes: Array<Record<string, unknown>> }>("GET", `/versions/${id(versionId)}/diff${against ? `?against=${id(against)}` : ""}`),
      approve: (versionId: string, edits: Array<{ objectiveId: string; text: string }> = []) => call<{ runId: string | null; state: BuilderState }>("POST", `/versions/${id(versionId)}/approve`, { edits }),
      reject: (versionId: string, comment = "") => call<{ runId: string | null; state: BuilderState }>("POST", `/versions/${id(versionId)}/reject`, { comment }),
      restore: (versionId: string) => call<{ currentVersionId: string; runId: string | null; state: BuilderState }>("POST", `/versions/${id(versionId)}/restore`),
      undo: (sessionId: string) => call<{ currentVersionId: string; runId: string | null; state: BuilderState }>("POST", `/sessions/${id(sessionId)}/undo`),
      redo: (sessionId: string) => call<{ currentVersionId: string; runId: string | null; state: BuilderState }>("POST", `/sessions/${id(sessionId)}/redo`),
    },
    apply: (sessionId: string) => call<{ runId: string }>("POST", `/sessions/${id(sessionId)}/apply`),
    publish: (sessionId: string, acknowledgedWarnings = false) =>
      call<{ courseId: number; published: boolean }>("POST", `/sessions/${id(sessionId)}/publish`, acknowledgedWarnings ? { acknowledgedWarnings: true } : {}),
    /** Blocking items and warnings before publishing (the publish summary). */
    publishCheck: (sessionId: string) => call<PublishCheck>("GET", `/sessions/${id(sessionId)}/publish-check`),
    usage: (sessionId: string) => call<{ total: BuilderCost; byTask: UsageRow[] }>("GET", `/sessions/${id(sessionId)}/usage`),
    /** AG-UI event stream with resume; resolves when `signal` aborts or access is refused. */
    events: (
      sessionId: string,
      handlers: { onEvent: (event: AgUiEvent, id: string | null) => void; onStatus?: (status: StreamStatus, detail?: string) => void },
      opts: { signal?: AbortSignal; lastEventId?: string | null } = {}
    ) =>
      connectEventStream({
        url: `${base}/sessions/${id(sessionId)}/events`,
        fetch: doFetch,
        headers: options.token ? { Authorization: `Bearer ${options.token}` } : options.headers,
        lastEventId: opts.lastEventId,
        signal: opts.signal,
        onEvent: handlers.onEvent,
        onStatus: handlers.onStatus,
      }),
  };
}
