/**
 * Learner Insights, author and admin side (`/api/admin/learner-insights`): the aggregates of a course
 * (progress, completion, quiz performance, status distribution, nudges and help), the sections that
 * confuse people, and, with the individual-data permission, the learners who struggle and one learner's
 * timeline. Groups smaller than `minGroupSize` are withheld by the API, not hidden here. REST calls
 * return the unwrapped `data`. In the browser the reference app points `baseUrl` at its BFF
 * (`/studio/api`) with `prefix: "/learner-insights"`, which adds the author's token.
 */
import { ApiError, type ClientOptions } from "./client.ts";

export type InsightsPeriod = 7 | 30 | 90;
export const INSIGHTS_PERIODS: readonly InsightsPeriod[] = [7, 30, 90];

export interface CourseInsightsBase {
  courseId: number;
  periodDays: InsightsPeriod;
  minGroupSize: number;
  enrolled: number;
}

/** Fewer enrolled learners than the minimum group: nothing else is sent. */
export interface CourseInsightsWithheld extends CourseInsightsBase {
  smallGroup: true;
}

export interface CourseInsightsFull extends CourseInsightsBase {
  smallGroup: false;
  learners: { enrolled: number; active: number; completed: number; completionRate: number };
  quiz: { attempts: number; averageScore: number | null; passRate: number | null };
  statuses: { on_track: number; struggling: number; at_risk: number };
  nudges: { sent: number; suppressed: number; opened: number; recoveryRate: number | null };
  remediation: { shown: number; helpful: number; notHelpful: number; recoveryRate: number | null };
}

export type CourseInsights = CourseInsightsWithheld | CourseInsightsFull;

/** The rule that made learners struggle on an element (`failed_attempts`, `slow_element`, ...), with how many learners it hit. */
export interface InsightsTopReason {
  rule: string;
  count: number;
}

export interface ElementInsightsHidden {
  elementKey: string;
  label: string;
  hidden: true;
  reason: "not_enough_learners";
}

export interface ElementInsightsShown {
  elementKey: string;
  label: string;
  hidden: false;
  activeLearners: number;
  struggleRate: number;
  remediationRate: number;
  /** Null while fewer than `minGroupSize` closed interventions exist. */
  recoveryRate: number | null;
  topReasons: InsightsTopReason[];
}

export type ElementInsights = ElementInsightsHidden | ElementInsightsShown;

export interface CourseElements {
  courseId: number;
  periodDays: InsightsPeriod;
  minGroupSize: number;
  elements: ElementInsights[];
}

export type ElementSort = "struggle" | "active" | "recovery";
export type LearnerStatusName = "struggling" | "at_risk" | "on_track";

/** Individual data: the API sends it only to holders of `learner_insights_view_individuals` (or authors when the tenant allows). */
export interface StrugglingLearner {
  userId: number;
  name: string;
  status: LearnerStatusName;
  since: string;
  /** Plain-text reasons from the API. */
  reasons: string[];
}

export interface TimelineEntry {
  at: string;
  type: string;
  element: string | null;
  value: number | null;
  data: Record<string, unknown> | null;
  source: string;
}

const PREFIX = "/api/admin/learner-insights";

export type InsightsAnalyticsClient = ReturnType<typeof createInsightsAnalyticsClient>;

/**
 * `prefix` is where the API lives under `baseUrl`: the API path by default, or `/learner-insights` when
 * `baseUrl` points at the studio BFF. `me` is the learner endpoint of the signed-in author; it tells
 * whether the module is on for the academy.
 */
export function createInsightsAnalyticsClient(options: ClientOptions & { prefix?: string }) {
  const base = options.baseUrl.replace(/\/+$/, "") + (options.prefix ?? PREFIX);
  const doFetch = options.fetch ?? ((input: RequestInfo | URL, init?: RequestInit) => fetch(input, init));

  async function get<T>(path: string): Promise<T> {
    const headers: Record<string, string> = { Accept: "application/json", ...options.headers, ...(options.token ? { Authorization: `Bearer ${options.token}` } : {}) };
    let response: Response;
    try {
      response = await doFetch(`${base}${path}`, { method: "GET", headers, signal: AbortSignal.timeout(options.timeoutMs ?? 30_000) });
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

  const query = (params: Record<string, string | number | undefined>): string => {
    const search = new URLSearchParams();
    for (const [key, value] of Object.entries(params)) if (value !== undefined) search.set(key, String(value));
    const text = search.toString();
    return text ? `?${text}` : "";
  };

  return {
    base,
    /** Progress, completion, quiz performance, statuses, nudges and help. 403 without `learner_insights_view` or authorship. */
    summary: (courseId: number, period: InsightsPeriod = 30) => get<CourseInsights>(`/courses/${courseId}/summary${query({ period })}`),
    /** Sections ranked by struggle (default), activity or recovery. */
    elements: (courseId: number, period: InsightsPeriod = 30, sort?: ElementSort) => get<CourseElements>(`/courses/${courseId}/elements${query({ period, sort })}`),
    /** Individuals: 403 unless the permission or the tenant setting allows it. */
    learners: (courseId: number, status?: LearnerStatusName) => get<StrugglingLearner[]>(`/courses/${courseId}/learners${query({ status })}`),
    /** One learner's signals, newest first. Admin only (`learner_insights_view_individuals`). */
    timeline: (userId: number, courseId: number) => get<TimelineEntry[]>(`/learners/${userId}/courses/${courseId}/timeline`),
  };
}

/** The learner endpoint `GET /api/learner-insights/me`, narrowed to what the author's Insights page needs. */
export function createInsightsModuleClient(options: ClientOptions & { prefix?: string }) {
  const base = options.baseUrl.replace(/\/+$/, "") + (options.prefix ?? "/api/learner-insights");
  const doFetch = options.fetch ?? ((input: RequestInfo | URL, init?: RequestInit) => fetch(input, init));
  return {
    /** False when Learner Insights is switched off for the academy. */
    enabled: async (): Promise<boolean> => {
      const headers: Record<string, string> = { Accept: "application/json", ...options.headers, ...(options.token ? { Authorization: `Bearer ${options.token}` } : {}) };
      let response: Response;
      try {
        response = await doFetch(`${base}/me`, { method: "GET", headers, signal: AbortSignal.timeout(options.timeoutMs ?? 30_000) });
      } catch (error) {
        throw new ApiError(0, "/me", null, `API unreachable on /me: ${(error as Error).message}`);
      }
      if (!response.ok) throw new ApiError(response.status, "/me", null, `API ${response.status} on /me`);
      const json = (await response.json()) as { data?: { enabled?: boolean } };
      return json.data?.enabled === true;
    },
  };
}
