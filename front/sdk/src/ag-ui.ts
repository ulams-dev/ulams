/**
 * AG-UI over SSE (ADR 0011): a small fetch-based reader with Last-Event-ID resume, instead of
 * `@ag-ui/client` (which brings RxJS). Event types come from `@ag-ui/core`.
 *
 * The Course Builder API closes each connection after ~25 s; `connectEventStream` reconnects with
 * the last event id, so a reload or a dropped connection never loses or repeats an event. A2UI v0.9
 * surfaces arrive as `ACTIVITY_SNAPSHOT` events with activity type `a2ui-surface` (helpers below).
 */
import { EventType } from "@ag-ui/core";

export { EventType };

/** One AG-UI event as sent by the API: `type` plus the event's fields. */
export interface AgUiEvent {
  type: EventType | `${EventType}`;
  [key: string]: unknown;
}

export interface SseMessage {
  id: string | null;
  event: string;
  data: string;
  retry: number | null;
}

/**
 * Incremental SSE parser (WHATWG event-stream rules): feed it text chunks in any split, it returns
 * the complete messages. Handles CRLF/CR/LF, multi-line `data`, comments and `retry`.
 */
export class SseParser {
  private buffer = "";
  private data: string[] = [];
  private eventName = "";
  private id: string | null = null;
  private retry: number | null = null;
  /** Last id seen (persists across messages without an id, as the spec requires). */
  lastEventId: string | null = null;

  push(chunk: string): SseMessage[] {
    this.buffer += chunk;
    const out: SseMessage[] = [];
    for (;;) {
      const match = /\r\n|\r|\n/.exec(this.buffer);
      if (!match) break;
      // a lone CR at the end may be the first half of CRLF: wait for more input
      if (match[0] === "\r" && match.index === this.buffer.length - 1) break;
      const line = this.buffer.slice(0, match.index);
      this.buffer = this.buffer.slice(match.index + match[0].length);
      const message = this.line(line);
      if (message) out.push(message);
    }
    return out;
  }

  private line(line: string): SseMessage | null {
    if (line === "") {
      if (this.data.length === 0) {
        this.eventName = "";
        return null;
      }
      const message: SseMessage = { id: this.id, event: this.eventName || "message", data: this.data.join("\n"), retry: this.retry };
      this.data = [];
      this.eventName = "";
      this.id = null;
      this.retry = null;
      return message;
    }
    if (line.startsWith(":")) return null;
    const colon = line.indexOf(":");
    const field = colon === -1 ? line : line.slice(0, colon);
    let value = colon === -1 ? "" : line.slice(colon + 1);
    if (value.startsWith(" ")) value = value.slice(1);
    switch (field) {
      case "data":
        this.data.push(value);
        break;
      case "event":
        this.eventName = value;
        break;
      case "id":
        if (!value.includes("\0")) {
          this.id = value;
          this.lastEventId = value;
        }
        break;
      case "retry":
        if (/^\d+$/.test(value)) this.retry = Number(value);
        break;
    }
    return null;
  }
}

/** Reads one SSE response body to the end, calling `onMessage` for each message. */
export async function readEventStream(
  body: ReadableStream<Uint8Array>,
  onMessage: (message: SseMessage) => void,
  parser: SseParser = new SseParser()
): Promise<SseParser> {
  const reader = body.getReader();
  const decoder = new TextDecoder();
  for (;;) {
    const { value, done } = await reader.read();
    if (done) break;
    for (const message of parser.push(decoder.decode(value, { stream: true }))) onMessage(message);
  }
  for (const message of parser.push(decoder.decode() + "\n\n")) onMessage(message);
  return parser;
}

export type StreamStatus = "connecting" | "open" | "reconnecting" | "closed" | "error";

export interface EventStreamOptions {
  /** Stream URL, e.g. `/studio/api/sessions/<id>/events`. */
  url: string;
  fetch?: typeof fetch;
  headers?: Record<string, string>;
  /** Resume after this event id. */
  lastEventId?: string | null;
  onEvent: (event: AgUiEvent, id: string | null) => void;
  onStatus?: (status: StreamStatus, detail?: string) => void;
  signal?: AbortSignal;
  /** Initial reconnect delay in ms (the server's `retry` wins); doubles on errors up to 15 s. */
  retryMs?: number;
}

/**
 * Connects and keeps reconnecting until `signal` aborts. Normal server-side closes (the 25 s cap)
 * reconnect immediately with `Last-Event-ID`; failures back off. 401/403/404 stop the stream.
 */
export async function connectEventStream(options: EventStreamOptions): Promise<void> {
  const doFetch = options.fetch ?? ((input: RequestInfo | URL, init?: RequestInit) => fetch(input, init));
  let lastId = options.lastEventId ?? null;
  let delay = options.retryMs ?? 1000;
  let failures = 0;
  const sleep = (ms: number) =>
    new Promise<void>((resolve) => {
      const timer = setTimeout(resolve, ms);
      options.signal?.addEventListener("abort", () => {
        clearTimeout(timer);
        resolve();
      });
    });

  while (!options.signal?.aborted) {
    options.onStatus?.(failures > 0 || lastId ? "reconnecting" : "connecting");
    try {
      const headers: Record<string, string> = { Accept: "text/event-stream", ...options.headers };
      if (lastId) headers["Last-Event-ID"] = lastId;
      const response = await doFetch(options.url, { headers, signal: options.signal, cache: "no-store" });
      if ([401, 403, 404].includes(response.status)) {
        options.onStatus?.("error", `HTTP ${response.status}`);
        return;
      }
      if (!response.ok || !response.body) throw new Error(`HTTP ${response.status}`);
      options.onStatus?.("open");
      failures = 0;
      const parser = new SseParser();
      await readEventStream(response.body, (message) => {
        if (message.retry !== null) delay = message.retry;
        if (message.id) lastId = message.id;
        let event: AgUiEvent;
        try {
          event = JSON.parse(message.data) as AgUiEvent;
        } catch {
          return;
        }
        if (event && typeof event.type === "string") options.onEvent(event, message.id);
      }, parser);
      // the server closed the connection on purpose (connection cap): resume right away
      await sleep(Math.min(delay, 1000));
    } catch (error) {
      if (options.signal?.aborted) break;
      failures += 1;
      options.onStatus?.("reconnecting", (error as Error).message);
      await sleep(Math.min(15_000, delay * 2 ** Math.min(failures, 4)));
    }
  }
  options.onStatus?.("closed");
}

/* ------------------------------------------------------------------ A2UI v0.9 in AG-UI */

export const A2UI_ACTIVITY = "a2ui-surface";

/** A flat A2UI component: `id`, `component`, props inline, `children` as ids. */
export interface A2uiComponent {
  id: string;
  component: string;
  children?: string[];
  [prop: string]: unknown;
}

export interface A2uiSurface {
  surfaceId: string;
  catalogId: string;
  /** Builder surface kind (`interview`, `outline`, `progress`, `apply`, `patch`, `source`). */
  kind?: string;
  components: A2uiComponent[];
  data: Record<string, unknown>;
  deleted: boolean;
}

/** Applies an `ACTIVITY_SNAPSHOT` event carrying A2UI messages; returns the surface or null. */
export function surfaceFromEvent(event: AgUiEvent): A2uiSurface | null {
  if (event.type !== EventType.ACTIVITY_SNAPSHOT || event.activityType !== A2UI_ACTIVITY) return null;
  type Message = {
    createSurface?: { catalogId?: string };
    updateComponents?: { components?: A2uiComponent[] };
    updateDataModel?: { path?: string; value?: Record<string, unknown> };
    deleteSurface?: unknown;
  };
  const content = (event.content ?? {}) as { surfaceId?: string; kind?: string; messages?: Message[] };
  const surface: A2uiSurface = {
    surfaceId: String(content.surfaceId ?? event.messageId ?? ""),
    catalogId: "",
    kind: content.kind,
    components: [],
    data: {},
    deleted: false,
  };
  for (const message of content.messages ?? []) {
    if (message.createSurface) surface.catalogId = String(message.createSurface.catalogId ?? "");
    if (message.updateComponents) surface.components = message.updateComponents.components ?? [];
    if (message.updateDataModel) {
      const path = String(message.updateDataModel.path ?? "/");
      if (path === "/") surface.data = message.updateDataModel.value ?? {};
    }
    if (message.deleteSurface) surface.deleted = true;
  }
  return surface;
}

/** RFC 6902 subset used by STATE_DELTA (add, replace, remove on object paths). */
export function applyJsonPatch<T extends Record<string, unknown>>(state: T, ops: Array<{ op: string; path: string; value?: unknown }>): T {
  const next = structuredClone(state) as Record<string, unknown>;
  for (const op of ops) {
    const keys = op.path.split("/").slice(1).map((k) => k.replace(/~1/g, "/").replace(/~0/g, "~"));
    if (keys.length === 0) continue;
    let target: Record<string, unknown> = next;
    for (const key of keys.slice(0, -1)) {
      if (typeof target[key] !== "object" || target[key] === null) target[key] = {};
      target = target[key] as Record<string, unknown>;
    }
    const last = keys[keys.length - 1]!;
    if (op.op === "remove") delete target[last];
    else if (op.op === "add" || op.op === "replace") target[last] = structuredClone(op.value);
  }
  return next as T;
}
