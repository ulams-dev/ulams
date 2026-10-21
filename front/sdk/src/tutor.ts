/**
 * Course tutor (`/api/tutor`): the learner's questions, answers with citations and earlier
 * conversations. REST calls return the unwrapped `data`. In the browser `baseUrl` is the learner BFF
 * (`/bff`), which adds the session token; on another site pass the tenant API and the learner token.
 */
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

/** Longest question the API accepts. */
export const TUTOR_QUESTION_CHARS = 1000;

/** Why a tutor call failed, from the API's `reason` or the status code. */
export type TutorFailure = "limit" | "disabled" | "forbidden" | "not_found" | "network" | "unknown";

export function tutorFailure(error: unknown): TutorFailure {
  if (!(error instanceof ApiError)) return "unknown";
  if (error.status === 0) return "network";
  if (error.status === 429) return "limit";
  if (error.status === 503) return "disabled";
  if (error.status === 403) return "forbidden";
  if (error.status === 404) return "not_found";
  return "unknown";
}

const PREFIX = "/api/tutor";

export type TutorClient = ReturnType<typeof createTutorClient>;

export function createTutorClient(options: ClientOptions) {
  const base = options.baseUrl.replace(/\/+$/, "") + PREFIX;
  const doFetch = options.fetch ?? ((input: RequestInfo | URL, init?: RequestInit) => fetch(input, init));

  async function call<T>(method: "GET" | "POST" | "DELETE", path: string, body?: unknown): Promise<T | null> {
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
    return json?.data ?? null;
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
      const body: Record<string, unknown> = { text: question.text };
      if (question.conversationId) body.conversationId = question.conversationId;
      if (question.topicId) body.topicId = question.topicId;
      const data = await call<TutorAnswer>("POST", `/courses/${courseId}/messages`, body);
      if (!data) throw new ApiError(502, `/courses/${courseId}/messages`, null, "Empty tutor answer");
      return data;
    },
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
