import { request } from 'umi';

/**
 * Learner Insights analytics of one course (`/api/admin/learner-insights`), read-only. Only the fields the course
 * tab shows are typed; the studio Insights page shows the rest. Groups smaller than `minGroupSize` are withheld by
 * the API: a small course answers with `smallGroup: true` and nothing else, a withheld section with `hidden: true`.
 */
export type InsightsPeriod = 7 | 30 | 90;

export interface CourseInsightsSummary {
  courseId: number;
  periodDays: InsightsPeriod;
  minGroupSize: number;
  enrolled: number;
  smallGroup: boolean;
  learners?: { enrolled: number; active: number; completed: number; completionRate: number };
  statuses?: { on_track: number; struggling: number; at_risk: number };
}

export interface InsightsElement {
  elementKey: string;
  label: string;
  hidden: boolean;
  activeLearners?: number;
  struggleRate?: number;
}

export interface CourseInsightsElements {
  courseId: number;
  periodDays: InsightsPeriod;
  minGroupSize: number;
  elements: InsightsElement[];
}

const base = (courseId: number | string) => `/api/admin/learner-insights/courses/${courseId}`;

/** Errors are handled by the tab (a refusal is a sentence, not a notification). */
const quiet = { skipErrorHandler: true };

export async function courseInsightsSummary(courseId: number | string, period: InsightsPeriod) {
  return request<{ data: CourseInsightsSummary }>(`${base(courseId)}/summary`, {
    method: 'GET',
    params: { period },
    ...quiet,
  });
}

export async function courseInsightsElements(courseId: number | string, period: InsightsPeriod) {
  return request<{ data: CourseInsightsElements }>(`${base(courseId)}/elements`, {
    method: 'GET',
    params: { period, sort: 'struggle' },
    ...quiet,
  });
}

/** The learner endpoint of the signed-in user: tells whether Learner Insights is on for the academy. */
export async function learnerInsightsEnabled(): Promise<boolean> {
  const response = await request<{ data: { enabled: boolean } }>('/api/learner-insights/me', {
    method: 'GET',
    ...quiet,
  });
  return response?.data?.enabled === true;
}

/** The author studio's Insights page of a course (the full implementation; the admin tab only summarises it). */
export const insightsStudioPath = (courseId: number | string) =>
  `/studio/courses/${courseId}/insights`;
