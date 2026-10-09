import { connectEventStream, type AgUiEvent } from "@ulams/sdk";
import { CliError } from "../errors.ts";
import type { Ctx } from "../registry/types.ts";

/** One AG-UI event with its SSE id (null for the STATE_SNAPSHOT the server sends first on every connection). */
export interface StoredEvent {
  id: string | null;
  event: AgUiEvent;
}

export interface StreamOptions {
  sessionId: string;
  /** Resume after this event id. */
  after?: string | null;
  /** Return `"stop"` to end the stream. */
  onEvent(item: StoredEvent): "stop" | void;
  /** End when no event arrived for this long after the first message (reads what is stored, then stops). */
  idleMs?: number;
  /** End after this long no matter what. */
  maxMs?: number;
}

export type StreamEnd = "idle" | "stopped" | "timeout" | "aborted";

const PREFIX = "/api/admin/course-builder";

function failure(detail: string, sessionId: string): CliError {
  const status = Number(/HTTP (\d+)/.exec(detail)?.[1] ?? 0);
  if (status === 401) return new CliError("AUTH_EXPIRED", "The server rejected the token for the event stream.", { status });
  if (status === 403) return new CliError("FORBIDDEN", "This builder session belongs to another author.", { status });
  if (status === 404) return new CliError("NOT_FOUND", `Builder session ${sessionId} does not exist.`, { status, hint: "List sessions with `ulams builder sessions list`." });
  return new CliError("SERVER_ERROR", `The event stream failed: ${detail}`, { retryable: true });
}

/**
 * Reads the session's AG-UI event stream (SSE) with resume (`Last-Event-ID`) and de-duplication by
 * event id, so a reconnect after the server's 25 s cap never repeats or loses an event.
 */
export async function streamEvents(ctx: Ctx, o: StreamOptions): Promise<{ lastId: string | null; end: StreamEnd }> {
  const stop = new AbortController();
  const signal = AbortSignal.any([ctx.signal, stop.signal]);
  let end: StreamEnd = "aborted";
  let lastId = o.after ?? null;
  let lastNumber = Number(o.after ?? 0) || 0;
  let snapshotSeen = false;
  let error: string | null = null;
  let timer: ReturnType<typeof setTimeout> | undefined;
  const arm = (ms: number, why: StreamEnd) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
      end = why;
      stop.abort();
    }, ms);
  };
  const overall = o.maxMs ? setTimeout(() => ((end = "timeout"), stop.abort()), o.maxMs) : undefined;
  // nothing at all after 20 s: the server is not answering
  if (o.idleMs) arm(Math.max(o.idleMs, 20_000), "idle");

  try {
    await connectEventStream({
      url: `${ctx.client.baseUrl}${PREFIX}/sessions/${encodeURIComponent(o.sessionId)}/events`,
      ...(ctx.client.fetchImpl ? { fetch: ctx.client.fetchImpl } : {}),
      headers: ctx.client.streamHeaders(),
      lastEventId: o.after ?? null,
      signal,
      onStatus: (status, detail) => {
        if (status === "error") error = detail ?? "error";
      },
      onEvent: (event, id) => {
        if (id !== null) {
          const n = Number(id);
          if (Number.isFinite(n)) {
            if (n <= lastNumber) return;
            lastNumber = n;
          }
          lastId = id;
        } else {
          // the snapshot repeats on every reconnect: show it once
          if (event.type === "STATE_SNAPSHOT") {
            if (snapshotSeen) return;
            snapshotSeen = true;
          }
        }
        if (o.idleMs) arm(o.idleMs, "idle");
        if (o.onEvent({ id, event }) === "stop") {
          end = "stopped";
          stop.abort();
        }
      },
    });
  } finally {
    clearTimeout(timer);
    clearTimeout(overall);
  }
  if (error) throw failure(error, o.sessionId);
  return { lastId, end };
}

/** Quiet time that ends a read of the stored events (ULAMS_EVENTS_IDLE_MS shortens it for tests). */
export const idleMs = (ctx: Ctx): number => Number(ctx.env.ULAMS_EVENTS_IDLE_MS) || 900;

/** Everything stored so far (up to `limit` events). */
export async function readStoredEvents(ctx: Ctx, sessionId: string, after: string | null = null, limit = 5000): Promise<{ events: StoredEvent[]; lastId: string | null }> {
  const events: StoredEvent[] = [];
  const { lastId } = await streamEvents(ctx, {
    sessionId,
    after,
    idleMs: idleMs(ctx),
    onEvent: (item) => {
      events.push(item);
      if (events.length >= limit) return "stop";
    },
  });
  return { events, lastId };
}

/* ------------------------------------------------------------------ interview and outline from the stream */

export interface InterviewQuestion {
  key: string;
  label: string;
  why: string | null;
  status: "open" | "upcoming" | "answered" | string;
  component: string;
  options: Array<{ value: string; label?: string }>;
  default: unknown;
  value: unknown;
  step: number | null;
  total: number | null;
}

interface A2uiComponentLike {
  id: string;
  component: string;
  [prop: string]: unknown;
}

/** Latest content of every A2UI surface, by id; deleted surfaces are dropped. */
export function surfacesFromEvents(events: StoredEvent[]): Map<string, { kind: string | null; components: A2uiComponentLike[] }> {
  const surfaces = new Map<string, { kind: string | null; components: A2uiComponentLike[] }>();
  for (const { event } of events) {
    if (event.type !== "ACTIVITY_SNAPSHOT" || event.activityType !== "a2ui-surface") continue;
    const content = (event.content ?? {}) as { surfaceId?: string; kind?: string; messages?: Array<Record<string, unknown>> };
    const id = String(content.surfaceId ?? event.messageId ?? "");
    let components: A2uiComponentLike[] | null = null;
    let deleted = false;
    for (const message of content.messages ?? []) {
      const update = message.updateComponents as { components?: A2uiComponentLike[] } | undefined;
      if (update?.components) components = update.components;
      if (message.deleteSurface) deleted = true;
    }
    if (deleted) surfaces.delete(id);
    else if (components) surfaces.set(id, { kind: content.kind ?? null, components });
  }
  return surfaces;
}

/** The interview questions of the latest `interview` surface, in order. */
export function interviewFromEvents(events: StoredEvent[]): InterviewQuestion[] {
  const surface = surfacesFromEvents(events).get("interview");
  if (!surface) return [];
  return surface.components
    .filter((c) => c.id.startsWith("q-") && typeof c.questionKey === "string")
    .map((c) => ({
      key: String(c.questionKey),
      label: String(c.label ?? c.questionKey),
      why: typeof c.why === "string" ? c.why : null,
      status: String(c.status ?? "open"),
      component: c.component,
      options: Array.isArray(c.options) ? (c.options as InterviewQuestion["options"]) : [],
      default: c.defaultValue ?? null,
      value: c.value ?? null,
      step: typeof c.step === "number" ? c.step : null,
      total: typeof c.total === "number" ? c.total : null,
    }));
}

/** The last RUN_ERROR message, for a session that stopped without an active run. */
export function lastRunError(events: StoredEvent[]): string | null {
  for (let i = events.length - 1; i >= 0; i--) {
    const e = events[i]?.event;
    if (e?.type === "RUN_ERROR") return String(e.message ?? "The run failed.");
  }
  return null;
}
