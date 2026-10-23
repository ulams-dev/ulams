import type { AxiosRequestConfig } from '@umijs/max';
import { request } from 'umi';

/** The part of a style profile (ADR 0196) the theme screen reads: the theme tokens the admin may accept as a diff. */
export type StyleProfileTheme = { preset?: string; accent?: string; secondary?: string; fonts?: string[] };

export type StyleProfileSummary = {
  id: string;
  name: string;
  status: 'suggested' | 'approved' | 'superseded';
  profile: { theme?: StyleProfileTheme };
};

/** A style profile id is a ULID; anything else in the link is ignored. */
export const isStyleProfileId = (value: unknown): value is string =>
  typeof value === 'string' && /^[0-9a-z]{26}$/i.test(value);

/** GET /api/admin/course-builder/style-profiles/{id}: the profile whose theme the screen proposes as a diff. */
export async function getStyleProfile(id: string, options?: AxiosRequestConfig) {
  return request<{ success: boolean; data: StyleProfileSummary }>(
    `/api/admin/course-builder/style-profiles/${id.toLowerCase()}`,
    { method: 'GET', useCache: false, ...(options || {}) },
  );
}
