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
  elements: {
    ai_elements: number;
    accepted_unchanged: number;
    revised_on_request: number;
    edited_by_author: number;
    unknown: number;
    author_written: number;
  };
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

/** The file the API builds, as a Blob; the page saves it. */
export async function exportCourseMetrics(format: 'csv' | 'jsonl', filter: MetricFilter) {
  return request<Blob>(`${BASE}/export`, {
    method: 'GET',
    params: { ...filter, format },
    responseType: 'blob',
    ...quiet,
  });
}
