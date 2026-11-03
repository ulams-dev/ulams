/**
 * Small JSON transport shared by the learner clients that read `/api/...` envelopes: one request,
 * the unwrapped `data` and the `meta` of paginated answers. Errors become `ApiError` (status 0 when
 * the API is unreachable).
 */
import { ApiError, type ClientOptions } from "./client.ts";

export interface Page<T> {
  items: T[];
  total: number;
  perPage: number;
  currentPage: number;
  lastPage: number;
}

export function createRest(options: ClientOptions) {
  const origin = options.baseUrl.replace(/\/+$/, "");
  const doFetch = options.fetch ?? ((input: RequestInfo | URL, init?: RequestInit) => fetch(input, init));

  async function request<T>(path: string, query?: Record<string, string | number | undefined | null>): Promise<{ data: T | null; meta: Record<string, number> | null }> {
    const qs = new URLSearchParams();
    for (const [key, value] of Object.entries(query ?? {})) if (value !== undefined && value !== null && value !== "") qs.set(key, String(value));
    const url = `${origin}${path}${qs.size ? `?${qs}` : ""}`;
    const headers: Record<string, string> = { Accept: "application/json", "Current-timezone": options.timezone ?? "UTC", ...options.headers };
    if (options.token) headers.Authorization = `Bearer ${options.token}`;
    let response: Response;
    try {
      response = await doFetch(url, { method: "GET", headers, signal: AbortSignal.timeout(options.timeoutMs ?? 15_000) });
    } catch (error) {
      throw new ApiError(0, path, null, `API unreachable on ${path}: ${(error as Error).message}`);
    }
    const text = await response.text();
    let json: { data?: T; meta?: Record<string, number>; message?: string } | null;
    try {
      json = text ? JSON.parse(text) : null;
    } catch {
      json = { message: text.slice(0, 200) };
    }
    if (!response.ok) throw new ApiError(response.status, path, json, json?.message ?? `API ${response.status} on ${path}`);
    return { data: json?.data ?? null, meta: json?.meta ?? null };
  }

  return {
    origin,
    data: async <T>(path: string, query?: Record<string, string | number | undefined | null>): Promise<T | null> => (await request<T>(path, query)).data,
    page: async <T>(path: string, query?: Record<string, string | number | undefined | null>): Promise<Page<T>> => {
      const { data, meta } = await request<T[]>(path, query);
      const items = Array.isArray(data) ? data : [];
      return {
        items,
        total: meta?.total ?? items.length,
        perPage: meta?.per_page ?? items.length,
        currentPage: meta?.current_page ?? 1,
        lastPage: meta?.last_page ?? 1,
      };
    },
  };
}
