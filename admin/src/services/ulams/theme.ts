import type { AxiosRequestConfig } from '@umijs/max';
import { request } from 'umi';

/** Largest logo the API accepts (plan Q15), in bytes; checked here first so a big file is not sent. */
export const LOGO_MAX_BYTES = 200 * 1024;

/** What the screen can tell from the file name and type before the API checks the content. */
export type LogoProblem = 'type' | 'size';

export function logoProblem(file: Pick<File, 'name' | 'size' | 'type'>): LogoProblem | null {
  const name = file.name.toLowerCase();
  const known =
    ['image/png', 'image/svg+xml'].includes(file.type) ||
    name.endsWith('.png') ||
    name.endsWith('.svg');
  if (!known) return 'type';
  return file.size > LOGO_MAX_BYTES ? 'size' : null;
}

/** The stored theme settings of the tenant, by key. */
export type ThemeValues = {
  theme?: API.Setting;
  accent?: API.Setting;
  logo?: API.Setting;
  logoAlt?: API.Setting;
};

export function themeValues(rows: API.Setting[]): ThemeValues {
  const out: ThemeValues = {};
  for (const row of rows) {
    if (row.group === 'theme' && ['theme', 'accent', 'logo', 'logoAlt'].includes(row.key)) {
      out[row.key as keyof ThemeValues] = row;
    }
  }
  return out;
}

/** GET /api/admin/settings?group=theme, every row. */
export async function themeSettings(options?: AxiosRequestConfig) {
  return request<API.SettingsList>('/api/admin/settings', {
    method: 'GET',
    useCache: false,
    params: { group: 'theme', per_page: -1 },
    ...(options || {}),
  });
}

/** POST /api/admin/theme/logo: a PNG or SVG of at most 200 KB; the API checks the content and answers 422 with the reason. */
export async function uploadLogo(file: File, alt: string, options?: AxiosRequestConfig) {
  const body = new FormData();
  body.append('logo', file);
  if (alt.trim() !== '') body.append('alt', alt.trim());

  return request<API.DefaultResponse<{ url: string; mime: string; alt: string | null }>>(
    '/api/admin/theme/logo',
    { method: 'POST', data: body, ...(options || {}) },
  );
}

/** DELETE /api/admin/theme/logo */
export async function removeLogo(options?: AxiosRequestConfig) {
  return request<API.DefaultResponse<unknown>>('/api/admin/theme/logo', {
    method: 'DELETE',
    ...(options || {}),
  });
}

/** The reason an API call was refused: the first validation message, else the response message. */
export function refusal(error: unknown): string | null {
  const data = (
    error as { response?: { data?: { errors?: Record<string, string[]>; message?: string } } }
  )?.response?.data;
  const first = data?.errors ? Object.values(data.errors).flat()[0] : undefined;
  return first ?? data?.message ?? null;
}
