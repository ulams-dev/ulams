import { ApiError, createClient, type Course, type Tenant, type TopicProgress } from "@ulams/sdk";
import { cache } from "./cache.ts";
import { config } from "./config.ts";
import { siteModel, type RawSiteData, type SiteModel } from "./view-model.ts";

/** Server-side API client for a tenant (straight to the tenant API, not through the browser). */
export const apiFor = (tenant: Tenant, token?: string | null) =>
  createClient({ baseUrl: tenant.apiUrl, token: token ?? null, timeoutMs: 10_000, timezone: "UTC" });

const key = (tenant: Tenant, name: string) => `${tenant.apiUrl}|${name}`;

/** Cached public data (stale-while-revalidate). */
export function publicData<T>(tenant: Tenant, name: string, fetcher: () => Promise<T>, ttlMs = config.cacheTtlMs): Promise<T> {
  return cache.get(key(tenant, name), fetcher, { ttlMs, maxStaleMs: 30 * 60_000 });
}

const settle = <T>(promise: Promise<T>, fallback: T): Promise<T> => promise.catch(() => fallback);

export function getSettings(tenant: Tenant) {
  return publicData(tenant, "settings", () => apiFor(tenant).settings.public());
}

export function getCourses(tenant: Tenant) {
  return publicData(tenant, "courses", () => apiFor(tenant).courses.list({ per_page: 24 }).then((r) => r.data));
}

export function getCourse(tenant: Tenant, id: number) {
  return publicData(tenant, `course:${id}`, () => apiFor(tenant).courses.get(id));
}

/** Everything a landing or course page shows, fetched in parallel; a failing endpoint only empties its section. */
export async function getRawSiteData(tenant: Tenant, courseId?: number): Promise<RawSiteData> {
  const api = apiFor(tenant);
  const [settings, courses, tutors, webinars, events, products] = await Promise.all([
    settle(getSettings(tenant), null),
    settle(getCourses(tenant), []),
    settle(publicData(tenant, "tutors", () => api.courses.tutors()), []),
    settle(publicData(tenant, "webinars", () => api.events.webinars({ per_page: 6 })), []),
    settle(publicData(tenant, "events", () => api.events.stationary({ per_page: 6 })), []),
    settle(publicData(tenant, "products", () => api.products.list({ per_page: 12 })), []),
  ]);
  const id = courseId ?? courses[0]?.id;
  const course = id ? await settle<Course | null>(getCourse(tenant, id), null) : null;
  return { settings, courses, course, tutors, webinars, events, products };
}

export async function getSiteModel(tenant: Tenant, courseId?: number): Promise<SiteModel> {
  return siteModel(await getRawSiteData(tenant, courseId), tenant);
}

/** Fetches the public data of the given tenants in the background (server start). */
export function warm(tenants: Tenant[]): void {
  for (const tenant of tenants) void getRawSiteData(tenant).catch(() => undefined);
}

export interface ProgramResult {
  course: Course;
  /** False when the user cannot open the program (403): only preview topics play. */
  access: boolean;
  progress: TopicProgress[];
}

const tokenKey = (token: string) => {
  let h = 0;
  for (let i = 0; i < token.length; i++) h = (h * 31 + token.charCodeAt(i)) | 0;
  return (h >>> 0).toString(36);
};

/**
 * Course program for a user (cached per token for a short time so prev/next is instant),
 * falling back to the public course outline when the user has no access.
 */
export async function getProgram(tenant: Tenant, token: string, courseId: number): Promise<ProgramResult> {
  const api = apiFor(tenant, token);
  const k = `program:${tokenKey(token)}:${courseId}`;
  const progressKey = `progress:${tokenKey(token)}:${courseId}`;
  const programP = publicData(tenant, k, () => api.courses.program(courseId), 60_000)
    .then((course) => ({ course, access: true }))
    .catch(async (error: unknown) => {
      if (error instanceof ApiError && error.status === 403) {
        return { course: await getCourse(tenant, courseId), access: false };
      }
      throw error;
    });
  const progressP = publicData(tenant, progressKey, () => api.progress.course(courseId), 5_000).catch(() => [] as TopicProgress[]);
  const [program, progress] = await Promise.all([programP, progressP]);
  return { ...program, progress: program.access ? progress : [] };
}

/** The learner's profile, cached per session for 10 minutes. */
export function getProfile(tenant: Tenant, token: string) {
  return publicData(tenant, `profile:${tokenKey(token)}`, () => apiFor(tenant, token).auth.me(), 10 * 60_000);
}

/** Progress in every course of the learner (account page), cached briefly and refreshed in the background. */
export function getAllProgress(tenant: Tenant, token: string) {
  return publicData(tenant, `progress:all:${tokenKey(token)}`, () => apiFor(tenant, token).progress.all(), 20_000);
}

/** Starts the slow per-learner requests so the account page is ready when it is opened. */
export function warmLearner(tenant: Tenant, token: string): void {
  void getProfile(tenant, token).catch(() => undefined);
  void getAllProgress(tenant, token).catch(() => undefined);
}

/** Drops cached progress after a write through the BFF. */
export function invalidateProgress(tenant: Tenant): void {
  cache.delete(key(tenant, "progress:"));
}

/**
 * Starts a SCO on the tenant content origin (api/docs/content-origin.md): the API returns a player
 * URL on <slug>.content.<base> carrying a SCO-scoped tracking token in its fragment. Null when the
 * tenant has no content origin (the legacy API player is used then). Never cached: every call
 * issues a new token for this learner.
 */
export async function scormLaunch(tenant: Tenant, token: string, uuid: string): Promise<string | null> {
  try {
    const response = await fetch(`${tenant.apiUrl}/api/scorm/launch/${encodeURIComponent(uuid)}`, {
      method: "POST",
      headers: { Accept: "application/json", Authorization: `Bearer ${token}` },
      signal: AbortSignal.timeout(4000),
    });
    if (!response.ok) return null;
    const body = (await response.json()) as { data?: { url?: string | null } };
    return typeof body.data?.url === "string" ? body.data.url : null;
  } catch {
    return null;
  }
}

/**
 * Whether a SCORM package's entry file can be loaded. The API serves packages from
 * /storage/scorm/…, which 404s for tenants whose public storage is not linked; then the
 * player shows an explanation instead of a frame with a 404 page inside.
 */
export async function scormAvailable(tenant: Tenant, uuid: string): Promise<boolean> {
  return publicData(
    tenant,
    `scorm:${uuid}`,
    async () => {
      const show = await fetch(`${tenant.apiUrl}/api/scorm/show/${encodeURIComponent(uuid)}`, {
        headers: { Accept: "application/json" },
        signal: AbortSignal.timeout(4000),
      });
      if (!show.ok) return false;
      const body = (await show.json()) as { data?: { entry_url_absolute?: string } };
      const entry = body.data?.entry_url_absolute;
      if (!entry) return false;
      const head = await fetch(entry, { method: "HEAD", signal: AbortSignal.timeout(4000) });
      return head.ok;
    },
    5 * 60_000
  ).catch(() => false);
}
