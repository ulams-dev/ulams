import { tenantFetch } from "./api-transport.ts";
import type { AstroCookies } from "astro";
import { ApiError, createCourseBuilderClient, createLivingCourseClient, createRest, type Tenant } from "@ulams/sdk";
import { apiFor } from "./data.ts";
import { AUTHOR_BASE, cookieName, sessionCookieOptions } from "./cookies.ts";
import { config } from "./config.ts";

/**
 * Author session for the Course Builder studio. The Passport token of a tutor or admin lives in an
 * httpOnly cookie (separate from the learner session); the browser only talks to the BFF
 * (/studio/api/…), which adds it. On demo tenants the studio signs in as the demo admin.
 */
export function authorCookieName(secure: boolean): string {
  return cookieName(AUTHOR_BASE, secure, config.cookieFallbackPrefix);
}

export function setAuthorCookie(cookies: AstroCookies, token: string, expiresAt: number, secure: boolean): void {
  cookies.set(authorCookieName(secure), token, sessionCookieOptions(secure, expiresAt));
}

export function clearAuthorCookie(cookies: AstroCookies, secure: boolean): void {
  cookies.delete(authorCookieName(secure), { path: "/", secure });
}

const expiry = (value: string | null | undefined) => {
  const parsed = value ? Date.parse(value) : NaN;
  return Number.isNaN(parsed) ? Date.now() + 86_400_000 : parsed;
};

/** Cookie token, else a demo admin login when the tenant runs in demo mode; null means "sign in". */
export async function authorToken(tenant: Tenant, cookies: AstroCookies, secure: boolean): Promise<string | null> {
  const existing = cookies.get(authorCookieName(secure))?.value;
  if (existing) return existing;
  try {
    const demo = await apiFor(tenant).auth.demoLogin("admin");
    setAuthorCookie(cookies, demo.token, expiry(demo.expires_at), secure);
    return demo.token;
  } catch (error) {
    if (error instanceof ApiError) return null;
    throw error;
  }
}

export async function loginAuthor(tenant: Tenant, cookies: AstroCookies, email: string, password: string, secure: boolean): Promise<void> {
  const result = await apiFor(tenant).auth.login(email, password);
  setAuthorCookie(cookies, result.token, expiry(result.expires_at), secure);
}

/** Server-side builder client for SSR pages. */
export function builderFor(tenant: Tenant, token: string) {
  return createCourseBuilderClient({ baseUrl: tenant.apiUrl, fetch: tenantFetch(tenant), token, timeoutMs: 20_000 });
}

/** Server-side Living Course client for SSR pages. */
export function livingCourseFor(tenant: Tenant, token: string) {
  return createLivingCourseClient({ baseUrl: tenant.apiUrl, fetch: tenantFetch(tenant), token, timeoutMs: 20_000 });
}

/**
 * Title of a course for the Insights page header: `null` when the course is unknown (the page is a 404), the
 * course id's fallback is the caller's. A 403 (an author who cannot read the course record) is not an error
 * here: the page still works from the analytics API, which has its own authorisation.
 */
export async function courseTitleFor(tenant: Tenant, token: string, courseId: number): Promise<{ found: boolean; title: string | null }> {
  const rest = createRest({ baseUrl: tenant.apiUrl, fetch: tenantFetch(tenant), token, timeoutMs: 20_000 });
  try {
    const course = await rest.data<{ title?: string | null }>(`/api/admin/courses/${courseId}`);
    return { found: true, title: course?.title?.trim() || null };
  } catch (error) {
    if (error instanceof ApiError && error.status === 404) return { found: false, title: null };
    if (error instanceof ApiError) return { found: true, title: null };
    throw error;
  }
}

export { STUDIO_RULES, LIVING_COURSE_RULES, LIVING_COURSE_PREFIX, INSIGHTS_RULES, INSIGHTS_PREFIX, isStudioCall, isLivingCourseCall, isInsightsCall, insightsTarget } from "./studio-rules.ts";
