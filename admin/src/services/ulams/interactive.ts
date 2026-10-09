import { request } from 'umi';

/** Interactive packages (api/packages/interactive, permission interactive_manage; ADR 0086). */

export type Localised = Record<string, string>;

export type InteractiveStep = {
  id: string;
  title: Localised;
  text: Localised;
  poster?: string;
};

export type InteractiveManifest = {
  id: string;
  title: Localised;
  version: string;
  entry?: string;
  licence: string;
  attribution?: string;
  source?: { url: string; ref?: string };
  locales: string[];
  defaultLocale: string;
  capabilities?: Record<string, boolean>;
  requires?: string[];
  network?: string[];
  steps: InteractiveStep[];
  a11y?: { keyboard?: string; notes?: string };
};

export type InteractivePackage = {
  id: number;
  title: string;
  current_version: number;
  versions_count: number;
  topics_count: number;
  licence: string | null;
  updated_at?: string;
  manifest?: InteractiveManifest | null;
  network?: string[];
  network_allowed?: boolean;
  files?: Record<string, number>;
};

export type InteractiveVersion = {
  version: number;
  manifest_version: string | null;
  licence: string;
  change_note: string | null;
  author_id: number | null;
  files: number;
  total_bytes: number;
  steps: number;
  network: string[];
  created_at: string | null;
};

export type InteractiveTopicable = {
  id?: number;
  value?: number;
  version?: number | null;
  follow_latest?: boolean;
  resolved_version?: number | null;
  start_step?: string | null;
  end_step?: string | null;
  completion_rule?: CompletionRule;
  pass_score?: number | null;
  display?: DisplayMode;
  height?: number;
  text?: string | null;
};

export type CompletionRule = 'on_open' | 'on_range_end' | 'on_complete' | 'on_score';
export type DisplayMode = 'inline' | 'background';

type Response<T> = { success: boolean; data: T; message: string };

export const interactivePackages = (
  params: { current?: number; pageSize?: number; search?: string } = {},
) =>
  request<Response<InteractivePackage[]> & { meta: { total: number } }>('/api/admin/interactive', {
    method: 'GET',
    params: { page: params.current, per_page: params.pageSize, search: params.search || undefined },
  });

export const interactivePackage = (id: number) =>
  request<Response<InteractivePackage>>(`/api/admin/interactive/${id}`, { method: 'GET' });

const upload = (file: File, fields: Record<string, string | undefined>) => {
  const form = new FormData();
  form.append('file', file);
  Object.entries(fields).forEach(([key, value]) => value && form.append(key, value));

  return form;
};

export const createInteractivePackage = (
  file: File,
  title?: string,
  changeNote?: string,
  acceptNetwork = false,
) =>
  request<Response<InteractivePackage>>('/api/admin/interactive', {
    method: 'POST',
    data: upload(file, {
      title,
      change_note: changeNote,
      accept_network: acceptNetwork ? '1' : undefined,
    }),
  });

export const renameInteractivePackage = (id: number, title: string) =>
  request<Response<InteractivePackage>>(`/api/admin/interactive/${id}`, {
    method: 'PUT',
    data: { title },
  });

export const deleteInteractivePackage = (id: number) =>
  request<Response<null>>(`/api/admin/interactive/${id}`, { method: 'DELETE' });

export const interactiveVersions = (id: number) =>
  request<Response<InteractiveVersion[]>>(`/api/admin/interactive/${id}/versions`, {
    method: 'GET',
  });

export const addInteractiveVersion = (
  id: number,
  file: File,
  changeNote?: string,
  acceptNetwork = false,
) =>
  request<Response<InteractivePackage>>(`/api/admin/interactive/${id}/versions`, {
    method: 'POST',
    data: upload(file, {
      change_note: changeNote,
      accept_network: acceptNetwork ? '1' : undefined,
    }),
  });

/** The entry file of a version on the tenant content origin; nothing is tracked. */
export const previewInteractive = (id: number, version?: number) =>
  request<Response<{ url: string; nonce: string; version: number; manifest: InteractiveManifest }>>(
    `/api/admin/interactive/${id}/preview`,
    {
      method: 'GET',
      params: version ? { version } : {},
    },
  );

// ---- helpers (unit-tested in interactive.test.ts)

/** The text of a localised value: the wanted locale, else the default one, else any. */
export const localised = (value: Localised | undefined, locale: string, fallback: string): string =>
  value?.[locale] ?? value?.[fallback] ?? Object.values(value ?? {})[0] ?? '';

/** Options for the start and end step selects, in manifest order. */
export const stepOptions = (
  manifest: Pick<InteractiveManifest, 'steps' | 'defaultLocale'> | null | undefined,
  locale = 'en',
): { value: string; label: string }[] =>
  (manifest?.steps ?? []).map((step, index) => ({
    value: step.id,
    label: `${index + 1}. ${localised(step.title, locale, manifest?.defaultLocale ?? 'en')} (${
      step.id
    })`,
  }));

/** The first problem with a step range for a manifest, or null. */
export const rangeProblem = (
  manifest: Pick<InteractiveManifest, 'steps'> | null | undefined,
  start?: string | null,
  end?: string | null,
): 'unknown-start' | 'unknown-end' | 'order' | null => {
  const ids = (manifest?.steps ?? []).map((s) => s.id);
  if (start && !ids.includes(start)) return 'unknown-start';
  if (end && !ids.includes(end)) return 'unknown-end';
  if (start && end && ids.indexOf(start) > ids.indexOf(end)) return 'order';

  return null;
};

/** Whether the pass score field applies to a completion rule. */
export const needsPassScore = (rule?: CompletionRule) => rule === 'on_score';

/**
 * The topic form fields (the API's names). Booleans go out as 1/0, because the form is sent as
 * multipart data and Laravel's `boolean` rule does not read the string "true".
 */
export const topicFields = (value: InteractiveTopicable): Record<string, unknown> => ({
  value: value.value,
  follow_latest: value.follow_latest ? 1 : 0,
  version: value.follow_latest ? undefined : value.version ?? undefined,
  start_step: value.start_step ?? '',
  end_step: value.end_step ?? '',
  completion_rule: value.completion_rule ?? 'on_range_end',
  pass_score: needsPassScore(value.completion_rule) ? value.pass_score ?? undefined : undefined,
  display: value.display ?? 'inline',
  height: value.height ?? 640,
  text: value.text ?? '',
});

export const formatBytes = (bytes: number): string =>
  bytes >= 1024 * 1024
    ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
    : `${Math.max(1, Math.round(bytes / 1024))} KB`;

/** Whether the tenant switch `ulams_interactive.enabled` (public config, default on) is on. */
export const isInteractiveEnabled = (publicConfig: unknown): boolean => {
  const section = (publicConfig as Record<string, unknown> | undefined)?.ulams_interactive;
  const value = (section as Record<string, unknown> | undefined)?.enabled;

  return !(value === false || value === 'false' || value === '0' || value === 0);
};
