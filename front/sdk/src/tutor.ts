/**
 * Course tutor (`/api/tutor`): the learner's questions, answers with citations and earlier
 * conversations. REST calls return the unwrapped `data`. In the browser `baseUrl` is the learner BFF
 * (`/bff`), which adds the session token; on another site pass the tenant API and the learner token.
 */
import { connectEventStream, type AgUiEvent } from "./ag-ui.ts";
import { ApiError, type ClientOptions } from "./client.ts";

export interface TutorStatus {
  /** The tutor is switched on for the tenant. */
  enabled: boolean;
  /** AI is switched on for the tenant. */
  aiEnabled: boolean;
  /** Questions left today. */
  remaining: number;
  dailyLimit: number;
  /** A quiz attempt is open: the tutor explains concepts only. */
  attemptActive: boolean;
  /** The operator allows streamed answers (`askStream`); absent on older APIs. */
  streaming?: boolean;
}

export type TutorRefusal = "none" | "out_of_scope" | "attempt_active" | "unsafe";

export interface TutorCitation {
  chunkId: string;
  label: string;
  topicId: number | null;
  fragmentIds: Array<string | number>;
}

export interface TutorAnswer {
  conversationId: string;
  messageId: string;
  questionId?: string;
  /** Markdown. */
  answer: string;
  citations: TutorCitation[];
  refusal: TutorRefusal;
  inScope: boolean;
  remaining: number;
  /** "AI tutor · answers only from this course". */
  label: string;
}

export interface TutorConversationSummary {
  id: string;
  lastMessageAt: string;
  /** The conversation is deleted automatically after this moment. */
  expiresAt: string;
}

export interface TutorMessage {
  id: string;
  role: "learner" | "tutor";
  text: string;
  citations: TutorCitation[];
  refusal: TutorRefusal | null;
  at: string;
}

export interface TutorConversation {
  id: string;
  courseId: number;
  expiresAt: string;
  messages: TutorMessage[];
}

export interface TutorQuestion {
  text: string;
  conversationId?: string | null;
  /** The topic the learner has open: its content is added to the search. */
  topicId?: number | null;
}

/** A queued streamed answer (`POST messages` with `stream: true` answers 202). */
export interface TutorRun {
  conversationId: string;
  runId: string;
  questionId: string;
}

/** What the caller of `askStream` can react to while the answer is written. */
export interface TutorStreamHandlers {
  /**
   * The text written so far. It is a draft: the validated answer that `askStream` resolves with always
   * replaces it, and it may differ (a failed check swaps the draft for a standard message).
   */
  onDraft?: (text: string) => void;
  /** The run exists on the server (store it to resume after a reload with `followRun`). */
  onRun?: (run: TutorRun) => void;
  signal?: AbortSignal;
}

/** The run ended with an error (`RUN_ERROR`) or never finished. The question is stored; the learner can ask again. */
export class TutorRunError extends Error {
  constructor(
    readonly code: string,
    message: string
  ) {
    super(message);
    this.name = "TutorRunError";
  }
}

/** Longest question the API accepts. */
export const TUTOR_QUESTION_CHARS = 1000;

/** Why a tutor call failed, from the API's `reason` or the status code. */
export type TutorFailure = "limit" | "disabled" | "forbidden" | "not_found" | "busy" | "network" | "unknown";

export function tutorFailure(error: unknown): TutorFailure {
  if (!(error instanceof ApiError)) return "unknown";
  if (error.status === 0) return "network";
  if (error.status === 429) return "limit";
  if (error.status === 503) return "disabled";
  if (error.status === 403) return "forbidden";
  if (error.status === 404) return "not_found";
  if (error.status === 409) return "busy";
  return "unknown";
}

const PREFIX = "/api/tutor";

export type TutorClient = ReturnType<typeof createTutorClient>;

export function createTutorClient(options: ClientOptions) {
  const base = options.baseUrl.replace(/\/+$/, "") + PREFIX;
  const doFetch = options.fetch ?? ((input: RequestInfo | URL, init?: RequestInit) => fetch(input, init));

  function headers(extra: Record<string, string> = {}): Record<string, string> {
    return { "Current-timezone": options.timezone ?? "UTC", ...options.headers, ...(options.token ? { Authorization: `Bearer ${options.token}` } : {}), ...extra };
  }

  async function raw<T>(method: "GET" | "POST" | "DELETE", path: string, body?: unknown): Promise<{ status: number; data: T | null }> {
    const headers: Record<string, string> = {
      Accept: "application/json",
      "Current-timezone": options.timezone ?? "UTC",
      ...options.headers,
    };
    if (body !== undefined) headers["Content-Type"] = "application/json";
    if (options.token) headers.Authorization = `Bearer ${options.token}`;
    let response: Response;
    try {
      response = await doFetch(`${base}${path}`, {
        method,
        headers,
        body: body === undefined ? undefined : JSON.stringify(body),
        signal: AbortSignal.timeout(options.timeoutMs ?? 15_000),
      });
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
    return { status: response.status, data: json?.data ?? null };
  }

  async function call<T>(method: "GET" | "POST" | "DELETE", path: string, body?: unknown): Promise<T | null> {
    return (await raw<T>(method, path, body)).data;
  }

  function questionBody(question: TutorQuestion, stream: boolean): Record<string, unknown> {
    const body: Record<string, unknown> = { text: question.text };
    if (question.conversationId) body.conversationId = question.conversationId;
    if (question.topicId) body.topicId = question.topicId;
    if (stream) body.stream = true;
    return body;
  }

  /** Waits for the end of a run and returns its validated answer; a `text` draft is passed to `onDraft` meanwhile. */
  async function follow(run: TutorRun, handlers: TutorStreamHandlers = {}): Promise<TutorAnswer> {
    const controller = new AbortController();
    handlers.signal?.addEventListener("abort", () => controller.abort());
    let draft = "";
    let opened = false;
    let result: { answer?: TutorAnswer; error?: TutorRunError; unavailable?: boolean } = {};
    const finished = new Promise<void>((resolve) => {
      const deadline = setTimeout(() => {
        result = { error: new TutorRunError("timeout", "The tutor took too long to answer.") };
        controller.abort();
      }, 90_000);
      controller.signal.addEventListener("abort", () => {
        clearTimeout(deadline);
        resolve();
      });
      void connectEventStream({
        url: `${base}/runs/${encodeURIComponent(run.runId)}/events`,
        fetch: doFetch,
        headers: headers(),
        signal: controller.signal,
        onStatus: (status) => {
          if (status === "error" && !opened) {
            result = { unavailable: true };
            controller.abort();
          }
        },
        onEvent: (event: AgUiEvent) => {
          opened = true;
          if (event.type === "TEXT_MESSAGE_CONTENT" && typeof event.delta === "string") {
            draft += event.delta;
            handlers.onDraft?.(draft);
          } else if (event.type === "CUSTOM" && event.name === "tutor.final") {
            result = { answer: event.value as TutorAnswer };
            controller.abort();
          } else if (event.type === "RUN_ERROR") {
            result = { error: new TutorRunError(String(event.code ?? "error"), String(event.message ?? "The tutor could not answer.")) };
            controller.abort();
          }
        },
      });
    });
    await finished;
    if (result.answer) return result.answer;
    if (result.error) throw result.error;
    if (handlers.signal?.aborted) throw new TutorRunError("aborted", "Stopped.");
    return recover(run);
  }

  /**
   * The event stream could not be opened (or the run's events are gone): the answer is still written to the
   * conversation, so read it from there instead of asking the question a second time.
   */
  async function recover(run: TutorRun): Promise<TutorAnswer> {
    for (let attempt = 0; attempt < 30; attempt++) {
      const conversation = await call<TutorConversation>("GET", `/conversations/${encodeURIComponent(run.conversationId)}`);
      const messages = conversation?.messages ?? [];
      const at = messages.findIndex((m) => m.id === run.questionId);
      const reply = at >= 0 ? messages[at + 1] : undefined;
      if (reply?.role === "tutor") {
        const left = await call<TutorStatus>("GET", `/courses/${conversation!.courseId}/status`).catch(() => null);
        return {
          conversationId: run.conversationId,
          messageId: reply.id,
          questionId: run.questionId,
          answer: reply.text,
          citations: reply.citations,
          refusal: reply.refusal ?? "none",
          inScope: (reply.refusal ?? "none") === "none",
          remaining: left?.remaining ?? 0,
          label: "AI tutor · answers only from this course",
        };
      }
      await new Promise((resolve) => setTimeout(resolve, 2000));
    }
    throw new TutorRunError("timeout", "The tutor took too long to answer.");
  }

  return {
    base,
    /** Whether the tutor is on, the questions left today and whether a quiz attempt is open. */
    status: async (courseId: number): Promise<TutorStatus> => {
      const data = await call<TutorStatus>("GET", `/courses/${courseId}/status`);
      if (!data) throw new ApiError(502, `/courses/${courseId}/status`, null, "Empty tutor status");
      return data;
    },
    /** Asks a question. The answer comes only from the course and cites it. */
    ask: async (courseId: number, question: TutorQuestion): Promise<TutorAnswer> => {
      const data = await call<TutorAnswer>("POST", `/courses/${courseId}/messages`, questionBody(question, false));
      if (!data) throw new ApiError(502, `/courses/${courseId}/messages`, null, "Empty tutor answer");
      return data;
    },
    /**
     * Asks a question and streams the answer (ADR 0105): the API queues it and `handlers.onDraft` receives the
     * text as it is written; the returned promise resolves with the validated answer and its citations, which
     * replace the draft. When the operator switched streaming off the answer comes back at once. Throws
     * `TutorRunError` when the run fails, `ApiError` for the same refusals as `ask` (429, 503, 409, ...).
     */
    askStream: async (courseId: number, question: TutorQuestion, handlers: TutorStreamHandlers = {}): Promise<TutorAnswer> => {
      const { status, data } = await raw<TutorRun & Partial<TutorAnswer>>("POST", `/courses/${courseId}/messages`, questionBody(question, true));
      if (!data) throw new ApiError(502, `/courses/${courseId}/messages`, null, "Empty tutor answer");
      if (status !== 202 || !data.runId) return data as TutorAnswer;
      const run: TutorRun = { conversationId: data.conversationId, runId: data.runId, questionId: data.questionId };
      handlers.onRun?.(run);
      return follow(run, handlers);
    },
    /** Rejoins a run after a reload: replays its events from the start and resolves with the final answer. */
    followRun: follow,
    /** The learner's conversations in a course, newest first. */
    conversations: async (courseId: number): Promise<TutorConversationSummary[]> => (await call<TutorConversationSummary[]>("GET", `/courses/${courseId}/conversations`)) ?? [],
    conversation: async (id: string): Promise<TutorConversation> => {
      const data = await call<TutorConversation>("GET", `/conversations/${encodeURIComponent(id)}`);
      if (!data) throw new ApiError(502, `/conversations/${id}`, null, "Empty conversation");
      return data;
    },
    deleteConversation: async (id: string): Promise<void> => {
      await call("DELETE", `/conversations/${encodeURIComponent(id)}`);
    },
  };
}
