import type { AxiosRequestConfig } from '@umijs/max';
import { request } from 'umi';

/** One column of a report dataset and what a report may do with it (GET /api/admin/reporting/datasets). */
export type ReportColumn = {
  name: string;
  type: 'string' | 'int' | 'decimal' | 'bool' | 'date' | 'datetime' | 'enum';
  label: string;
  filterOps: string[];
  groupable: boolean;
  aggregatable: boolean;
  personal: boolean;
  enumValues: string[];
};

export type ReportDataset = {
  key: string;
  label: string;
  description: string;
  permission: string;
  subject_column: string | null;
  aggregate_only: boolean;
  date_columns: string[];
  columns: ReportColumn[];
};

/** A report definition, schema report-definition/v1. */
export type ReportDefinition = {
  dataset: string;
  columns?: string[];
  filters?: { column: string; op: string; value?: unknown }[];
  dateRange?: { column: string; preset?: string; from?: string; to?: string };
  groupBy?: (string | { column: string; bucket: string })[];
  aggregates?: { fn: string; column?: string; as: string }[];
  sort?: { column: string; dir?: 'asc' | 'desc' }[];
  limit?: number;
};

export type ReportResult = {
  columns: { name: string; type: string; label: string }[];
  rows: Record<string, unknown>[];
};

export type ReportMeta = {
  mode: 'rows' | 'grouped';
  total_rows: number;
  preview_rows: number;
  truncated: boolean;
  withheld_groups: number;
  min_group_size: number | null;
};

export type SavedReport = {
  id: string;
  name: string;
  description: string | null;
  dataset: string | null;
  visibility: 'private' | 'roles';
  shared_roles: string[];
  definition: ReportDefinition;
  is_owner: boolean;
  can_edit: boolean;
  runnable: boolean;
  needs_repair: boolean;
  problems: { path: string; message: string }[];
  last_run_at: string | null;
};

type Envelope<T> = { success: boolean; data: T; message?: string; meta?: ReportMeta };

export async function listDatasets(options?: AxiosRequestConfig) {
  return request<Envelope<ReportDataset[]>>('/api/admin/reporting/datasets', {
    method: 'GET',
    ...(options || {}),
  });
}

export async function previewReport(definition: ReportDefinition, options?: AxiosRequestConfig) {
  return request<Envelope<ReportResult>>('/api/admin/reporting/query', {
    method: 'POST',
    data: definition,
    skipErrorHandler: true,
    ...(options || {}),
  });
}

export async function listSavedReports(options?: AxiosRequestConfig) {
  return request<Envelope<SavedReport[]>>('/api/admin/reporting/saved', {
    method: 'GET',
    ...(options || {}),
  });
}

export async function saveReport(
  body: {
    name: string;
    description?: string;
    visibility: 'private' | 'roles';
    shared_roles?: string[];
    definition: ReportDefinition;
  },
  options?: AxiosRequestConfig,
) {
  return request<Envelope<SavedReport>>('/api/admin/reporting/saved', {
    method: 'POST',
    data: body,
    skipErrorHandler: true,
    ...(options || {}),
  });
}

export async function updateSavedReport(
  id: string,
  body: Partial<{
    name: string;
    description: string;
    visibility: 'private' | 'roles';
    shared_roles: string[];
    definition: ReportDefinition;
  }>,
  options?: AxiosRequestConfig,
) {
  return request<Envelope<SavedReport>>(`/api/admin/reporting/saved/${id}`, {
    method: 'PUT',
    data: body,
    skipErrorHandler: true,
    ...(options || {}),
  });
}

export async function deleteSavedReport(id: string, options?: AxiosRequestConfig) {
  return request<Envelope<null>>(`/api/admin/reporting/saved/${id}`, {
    method: 'DELETE',
    ...(options || {}),
  });
}

/** The file of a saved report run (csv or jsonl), as a blob. */
export async function downloadSavedReport(id: string, format: 'csv' | 'jsonl') {
  return request<Blob>(`/api/admin/reporting/saved/${id}/run`, {
    method: 'GET',
    params: { format },
    responseType: 'blob',
  });
}
