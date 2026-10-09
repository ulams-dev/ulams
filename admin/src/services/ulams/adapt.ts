import { request } from 'umi';

/**
 * Adapt Learning sources, Path B (api/packages/adapt, permission adapt_manage). The whole API
 * answers 404 when ADAPT_SOURCE_ENABLED is off; `isAdaptDisabled` recognises that.
 */

export type AdaptStatus = 'draft' | 'building' | 'built' | 'failed';

export type AdaptSource = {
  id: number;
  title: string;
  current_version: number;
  status: AdaptStatus;
  built_version: number | null;
  scorm_id: number | null;
  last_error: string | null;
  updated_at?: string;
};

export type AdaptVersion = {
  version: number;
  change_note: string | null;
  author_id: number | null;
  created_at: string | null;
};

type Response<T> = { success: boolean; data: T; message: string };

/** True when the error is the 404 of a disabled feature flag. */
export const isAdaptDisabled = (error: any): boolean =>
  (error?.response?.status ?? error?.data?.status ?? error?.status) === 404;

export const adaptSources = () =>
  request<Response<AdaptSource[]>>('/api/admin/adapt', { method: 'GET' });

export const adaptSource = (id: number) =>
  request<Response<AdaptSource>>(`/api/admin/adapt/${id}`, { method: 'GET' });

/** Create from Adapt JSON (an object, or a JSON string pasted or read from a file). */
export const createAdaptSource = (source: unknown, title?: string) =>
  request<Response<AdaptSource>>('/api/admin/adapt', {
    method: 'POST',
    data: {
      source: typeof source === 'string' ? JSON.parse(source) : source,
      title: title || undefined,
    },
  });

export const adaptVersions = (id: number) =>
  request<Response<AdaptVersion[]>>(`/api/admin/adapt/${id}/versions`, { method: 'GET' });

export const adaptSourceJson = (id: number, version?: number) =>
  request<Record<string, unknown>>(`/api/admin/adapt/${id}/source`, {
    method: 'GET',
    params: version ? { version } : {},
  });

export const addAdaptVersion = (id: number, source: unknown, changeNote?: string) =>
  request<Response<AdaptSource>>(`/api/admin/adapt/${id}/versions`, {
    method: 'POST',
    data: {
      source: typeof source === 'string' ? JSON.parse(source) : source,
      change_note: changeNote || undefined,
    },
  });

/** Queues a build (202); poll `adaptSource` while the status is `building`. */
export const buildAdaptSource = (id: number) =>
  request<Response<AdaptSource>>(`/api/admin/adapt/${id}/build`, { method: 'POST' });

export const deleteAdaptSource = (id: number) =>
  request<Response<null>>(`/api/admin/adapt/${id}`, { method: 'DELETE' });

/** Whether a source is still being built, so the screen keeps polling. */
export const isBuilding = (source?: Pick<AdaptSource, 'status'>): boolean =>
  source?.status === 'building';

export const ADAPT_POLL_MS = 3000;
