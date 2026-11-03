import { request } from 'umi';

/**
 * The authoring metric of the courses built with the Course Builder (`/api/admin/authoring-metrics`, ADR 0194):
 * the time every course took to produce, what the model calls cost and what became of the AI's elements. Read-only;
 * needs the permission `authoring_metrics_read`. Author time is `null` until review time is recorded.
 */
export interface CourseMetric {
  course_id: number;
  course_title: string | null;
  session_id: string;
  method_version: number;
  computed_at: string;
  time: {
    generation_seconds: number;
    ai_edit_seconds: number;
    interview_seconds: number;
    model_seconds: number;
    author_minutes: number | null;
  };
  cost: {
    build_micro_usd: number;
    maintenance_micro_usd: number;
    llm_calls: number;
    llm_calls_failed: number;
  };
  counts: {
    lessons: number;
    questions: number;
    blocks: number;
    cited_blocks: number;
    learner_minutes_estimate: number;
  };
  hours?: {
    work_hours: number | null;
    learner_hours: number;
    hours_per_learner_hour: number | null;
    reason: string | null;
  };
  elements: {
    ai_elements: number;
    accepted_unchanged: number;
    revised_on_request: number;
    edited_by_author: number;
    unknown: number;
    author_written: number;
  };
}

/** The academy-wide figure: a median over the courses that have it, with how many. No industry figure is involved. */
export interface MetricSummary {
  status: 'ok' | 'not_enough_courses' | 'author_time_not_recorded';
  n_courses: number;
  n_with_figure: number;
  min_courses: number;
  median_hours_per_learner_hour: number | null;
  own_baseline: { hours_per_learner_hour: number; label: 'own_estimate' } | null;
  maintenance: { courses: number; cost_micro_usd: number };
  formula: string;
  method_version: number;
}

export interface MetricFilter {
  from?: string;
  to?: string;
}

const BASE = '/api/admin/authoring-metrics/courses';

/** Errors are handled by the page (a refusal is a sentence, not a notification). */
const quiet = { skipErrorHandler: true };

export async function courseMetrics(filter: MetricFilter) {
  return request<{ data: CourseMetric[] }>(BASE, { method: 'GET', params: filter, ...quiet });
}

export async function metricSummary(filter: MetricFilter) {
  return request<{ data: MetricSummary }>('/api/admin/authoring-metrics/summary', {
    method: 'GET',
    params: filter,
    ...quiet,
  });
}

/** The file the API builds, as a Blob; the page saves it. */
export async function exportCourseMetrics(format: 'csv' | 'jsonl', filter: MetricFilter) {
  return request<Blob>(`${BASE}/export`, {
    method: 'GET',
    params: { ...filter, format },
    responseType: 'blob',
    ...quiet,
  });
}

/** Deletes every recorded review minute and daily total; the figures that are not personal stay. */
export async function deleteRecordedReviewTime() {
  return request<{ data: { minutes: number; days: number } }>(
    '/api/admin/authoring-metrics/activity',
    {
      method: 'DELETE',
      ...quiet,
    },
  );
}
