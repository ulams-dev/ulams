import { CliError } from "../errors.ts";
import { readStoredEvents, lastRunError } from "../http/events.ts";
import { registerOperationKind } from "../http/lro.ts";
import type { Ctx } from "../registry/types.ts";

export const BUILDER = "/api/admin/course-builder";

export type CallMethod = "GET" | "POST" | "PUT" | "PATCH" | "DELETE";

/** One call to the course builder API (the Laravel envelope is unwrapped to `data`). */
export async function builderCall<T>(
  ctx: Ctx,
  method: CallMethod,
  path: string,
  o: { params?: Record<string, string | number>; query?: Record<string, string | number | boolean | undefined>; body?: unknown; form?: FormData } = {}
): Promise<T> {
  const res = await ctx.client.call<T>(method, `${BUILDER}${path}`, {
    ...(o.params ? { params: o.params } : {}),
    ...(o.query ? { query: o.query } : {}),
    ...(o.body !== undefined ? { body: o.body } : {}),
    ...(o.form ? { form: o.form } : {}),
    idempotent: method === "GET",
    signal: ctx.signal,
  });
  return res.data;
}

export interface SessionSummary {
  id: string;
  title: string | null;
  status: string;
  courseId: number | null;
  currentVersionId: string | null;
  appliedVersionId: string | null;
  updatedAt?: string;
}

export interface SessionState {
  session: SessionSummary;
  brief: Record<string, unknown> | null;
  briefRows?: Array<{ key: string; label: string; value: string }>;
  cost?: Record<string, unknown>;
  sources: Array<{ id: string; name: string; status: string; kind: string; tokens: number; fragments: number; error: string | null }>;
  aiEnabled: boolean;
  budgetReached?: boolean;
  activeRunId: string | null;
  canUndo?: boolean;
  canRedo?: boolean;
  links?: Record<string, unknown>;
}

export interface RunStatus {
  id: string;
  sessionId: string;
  kind: string;
  status: "queued" | "running" | "succeeded" | "failed" | "cancelled";
  stage: string | null;
  needsAttention: boolean;
  steps: Array<{ id: string; name: string; status: string; error: string | null }>;
  startedAt: string | null;
  finishedAt: string | null;
  error: string | null;
}

export const getSession = (ctx: Ctx, id: string) => builderCall<SessionState>(ctx, "GET", "/sessions/{session}", { params: { session: id } });
export const getRun = (ctx: Ctx, id: string) => builderCall<RunStatus>(ctx, "GET", "/runs/{run}", { params: { run: id } });

/** Compact session view for results: what an agent needs to decide the next step. */
export function sessionView(state: SessionState) {
  return {
    id: state.session.id,
    title: state.session.title,
    status: state.session.status,
    courseId: state.session.courseId,
    currentVersionId: state.session.currentVersionId,
    appliedVersionId: state.session.appliedVersionId,
    activeRunId: state.activeRunId,
    sources: state.sources.map((s) => ({ id: s.id, name: s.name, status: s.status, error: s.error })),
    ...(state.links && Object.keys(state.links).length ? { links: state.links } : {}),
  };
}

registerOperationKind({
  kind: "builder-run",
  describe: "A course builder run (ingest, interview, outline, generate, patch, apply, a Living Course analysis or apply): builder-run:<run id>",
  async get(ctx, id) {
    const run = await getRun(ctx, id);
    if (run.status === "failed") return { status: "failed", data: run, error: run.error ?? run.steps.find((s) => s.status === "failed")?.error ?? "the run failed" };
    if (run.status === "cancelled") return { status: "failed", data: run, error: "the run was cancelled" };
    if (run.status === "succeeded") return { status: "succeeded", data: run };
    // waiting for the author (an answer or an approval): stop polling and let the caller ask
    if (run.needsAttention) return { status: "succeeded", data: run };
    return { status: "running", data: run };
  },
});

export const runHandle = (id: string) => `builder-run:${id}`;

/**
 * Polls the session until it reaches one of `targets` with no run active. Fails fast when a run
 * failed, when AI is off, or when the session stopped in another status (with the last RUN_ERROR).
 */
export async function waitForSession(
  ctx: Ctx,
  sessionId: string,
  targets: string[],
  o: { timeoutSeconds?: number; sleep?: (ms: number) => Promise<void> } = {}
): Promise<SessionState> {
  const sleep = o.sleep ?? ((ms: number) => new Promise<void>((r) => setTimeout(r, ms)));
  const timeout = o.timeoutSeconds ?? ctx.flags.timeout;
  const deadline = Date.now() + timeout * 1000;
  let delay = Number(ctx.env.ULAMS_POLL_MS) || 500;
  let idle = 0;
  const seenRuns = new Set<string>();
  for (;;) {
    const state = await getSession(ctx, sessionId);
    const s = state.session;
    const applied = s.status === "applied" && s.appliedVersionId !== null && s.appliedVersionId === s.currentVersionId;
    const reached = targets.includes(s.status) && (s.status !== "applied" || applied);
    if (reached && !state.activeRunId) return state;
    if (s.status === "failed") throw new CliError("SERVER_ERROR", "The builder session failed.", { details: { session: sessionView(state) } });
    if (state.activeRunId) {
      idle = 0;
      seenRuns.add(state.activeRunId);
      const run = await getRun(ctx, state.activeRunId);
      if (run.status === "failed" || run.status === "cancelled") {
        throw new CliError("SERVER_ERROR", `The ${run.kind} run ${run.status}${run.error ? `: ${run.error}` : "."}`, { details: { run, session: sessionView(state) } });
      }
    } else {
      if (!state.aiEnabled && !reached) {
        throw new CliError("FEATURE_DISABLED", "AI is disabled on this instance, so the builder cannot interview, outline or generate.", { details: { session: sessionView(state) } });
      }
      idle++;
      if (idle >= 4) {
        // nothing is running and the target was not reached: say why
        const { events } = await readStoredEvents(ctx, sessionId).catch(() => ({ events: [] }));
        const reason = lastRunError(events);
        throw new CliError("SERVER_ERROR", reason ? `The builder stopped in status ${s.status}: ${reason}` : `The builder stopped in status ${s.status} without a run (waiting for ${targets.join(" or ")}).`, {
          details: { session: sessionView(state), runs: [...seenRuns] },
          hint: s.status === "interviewing" ? "Answer the interview: `ulams builder interview show <session>`." : "Check `ulams builder events <session>` for what happened.",
        });
      }
    }
    if (Date.now() + delay > deadline) {
      throw new CliError("TIMEOUT", `The session is still in status ${s.status} after ${timeout}s.`, {
        details: { session: sessionView(state), operation: state.activeRunId ? runHandle(state.activeRunId) : null },
        hint: state.activeRunId ? `Resume with \`ulams operations wait ${runHandle(state.activeRunId)}\`.` : "Run the command again.",
      });
    }
    ctx.io.stderr(`waiting for the session (${s.status}${state.activeRunId ? `, run ${state.activeRunId}` : ""}) ...`);
    await sleep(delay);
    delay = Math.min(delay * 2, 3000);
  }
}

/* ------------------------------------------------------------------ blueprint helpers */

interface BlueprintLike {
  course?: { id?: string; title?: string; objectives?: Array<{ id: string; text: string }>; faq?: Array<{ id?: string; question: string }> };
  modules?: Array<{
    id: string;
    title: string;
    lessons?: Array<{
      id: string;
      title: string;
      minutes?: number;
      objectives?: Array<{ id: string; text: string }>;
      blocks?: Array<{ id: string; kind: string; markdown: string }>;
      quiz?: { id: string; questions?: Array<{ id: string; stem: string }> } | null;
    }>;
  }>;
  finalTest?: { id: string; questions?: Array<{ id: string; stem: string }> } | null;
}

export interface ElementRef {
  id: string;
  type: string;
  path: string;
  label: string;
}

const clip = (text: string, n = 90) => (text.length > n ? `${text.slice(0, n - 1)}…` : text);

/** Every element a chat message can be scoped to, with a readable path. */
export function flattenBlueprint(doc: BlueprintLike): ElementRef[] {
  const out: ElementRef[] = [];
  if (doc.course?.id) out.push({ id: doc.course.id, type: "course", path: "course", label: doc.course.title ?? "" });
  for (const o of doc.course?.objectives ?? []) out.push({ id: o.id, type: "objective", path: "course/objectives", label: clip(o.text) });
  for (const [mi, m] of (doc.modules ?? []).entries()) {
    out.push({ id: m.id, type: "module", path: `modules/${mi + 1}`, label: m.title });
    for (const [li, l] of (m.lessons ?? []).entries()) {
      const base = `modules/${mi + 1}/lessons/${li + 1}`;
      out.push({ id: l.id, type: "lesson", path: base, label: l.title });
      for (const o of l.objectives ?? []) out.push({ id: o.id, type: "objective", path: `${base}/objectives`, label: clip(o.text) });
      for (const [bi, b] of (l.blocks ?? []).entries()) out.push({ id: b.id, type: b.kind, path: `${base}/blocks/${bi + 1}`, label: clip(b.markdown.replace(/\s+/g, " ")) });
      if (l.quiz) {
        out.push({ id: l.quiz.id, type: "quiz", path: `${base}/quiz`, label: `Quiz of ${l.title}` });
        for (const [qi, q] of (l.quiz.questions ?? []).entries()) out.push({ id: q.id, type: "question", path: `${base}/quiz/${qi + 1}`, label: clip(q.stem) });
      }
    }
  }
  if (doc.finalTest) {
    out.push({ id: doc.finalTest.id, type: "final-test", path: "finalTest", label: "Final test" });
    for (const [qi, q] of (doc.finalTest.questions ?? []).entries()) out.push({ id: q.id, type: "question", path: `finalTest/${qi + 1}`, label: clip(q.stem) });
  }
  return out;
}

/** The outline an author reviews: objectives, modules and lessons. */
export function outlineView(doc: BlueprintLike) {
  return {
    title: doc.course?.title ?? null,
    objectives: (doc.course?.objectives ?? []).map((o) => ({ id: o.id, text: o.text })),
    modules: (doc.modules ?? []).map((m) => ({
      id: m.id,
      title: m.title,
      lessons: (m.lessons ?? []).map((l) => ({ id: l.id, title: l.title, minutes: l.minutes ?? null, objectives: (l.objectives ?? []).length })),
    })),
    finalTest: doc.finalTest ? { id: doc.finalTest.id, questions: (doc.finalTest.questions ?? []).length } : null,
  };
}

export interface VersionRow {
  id: string;
  number: number;
  kind: string;
  origin: string;
  status: string;
  reason: string | null;
  createdAt: string;
}

export async function listVersions(ctx: Ctx, sessionId: string) {
  return builderCall<{ currentVersionId: string | null; appliedVersionId: string | null; versions: VersionRow[] }>(ctx, "GET", "/sessions/{session}/versions", { params: { session: sessionId } });
}

/** The newest proposed version of a kind (outline, patch) of a session. */
export async function proposedVersion(ctx: Ctx, sessionId: string, kind: "outline" | "patch"): Promise<VersionRow> {
  const { versions } = await listVersions(ctx, sessionId);
  const found = [...versions].filter((v) => v.kind === kind && v.status === "proposed").sort((a, b) => b.number - a.number)[0];
  if (!found) {
    throw new CliError("CONFLICT", `There is no ${kind} waiting for a decision in session ${sessionId}.`, {
      hint: kind === "outline" ? "Check the session with `ulams builder sessions get`; the interview may still be open." : "Propose a change first with `ulams builder chat`.",
    });
  }
  return found;
}
