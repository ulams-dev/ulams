/**
 * Learner side of the Living Course (`/api/living-course`): the caller's own update notices and the
 * opt-in marker for lessons whose source changed. REST calls return the unwrapped `data`. In the
 * browser `baseUrl` is the learner BFF (`/bff`), which adds the session token.
 */
import { ApiError, type ClientOptions } from "./client.ts";

export type NoticeKind = "topic_updated" | "question_reattempt" | "topic_retired" | "course_extended";

export interface LearnerNotice {
  id: number;
  kind: NoticeKind;
  topicId: number | null;
  topicTitle: string | null;
  giftQuestionId: number | null;
  /** The author's learner note (plain text); empty when none was written. */
  message: string | null;
  createdAt: string | null;
}

export interface NoticeStatus {
  id: number;
  /** `dismissed` once read; a `question_reattempt` notice stays `open` until the quiz is retaken. */
  status: string;
}

export interface TopicFreshness {
  topicId: number;
  since: string | null;
}

const PREFIX = "/api/living-course";

export type LivingLearnerClient = ReturnType<typeof createLivingLearnerClient>;

export function createLivingLearnerClient(options: ClientOptions) {
  const base = options.baseUrl.replace(/\/+$/, "") + PREFIX;
  const doFetch = options.fetch ?? ((input: RequestInfo | URL, init?: RequestInit) => fetch(input, init));

  async function call<T>(method: "GET" | "POST", path: string): Promise<T | null> {
    const headers: Record<string, string> = {
      Accept: "application/json",
      "Current-timezone": options.timezone ?? "UTC",
      ...options.headers,
    };
    if (options.token) headers.Authorization = `Bearer ${options.token}`;
    let response: Response;
    try {
      response = await doFetch(`${base}${path}`, { method, headers, signal: AbortSignal.timeout(options.timeoutMs ?? 15_000) });
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
    /** Open notices of the caller for a course, oldest first. */
    notices: async (courseId: number): Promise<LearnerNotice[]> => (await call<LearnerNotice[]>("GET", `/courses/${courseId}/notices`)) ?? [],
    /** "Mark as reviewed". A re-attempt notice stays open until the quiz is retaken. */
    dismiss: async (noticeId: number): Promise<NoticeStatus> => (await call<NoticeStatus>("POST", `/notices/${noticeId}/dismiss`)) ?? { id: noticeId, status: "dismissed" },
    /** Topics with an update under review; empty unless the course shows it to learners. */
    freshness: async (courseId: number): Promise<TopicFreshness[]> => (await call<{ topics: TopicFreshness[] }>("GET", `/courses/${courseId}/freshness`))?.topics ?? [],
  };
}
