import type { AstroCookies } from "astro";
import { ApiError, createCourseBuilderClient, createLivingCourseClient, type Tenant } from "@ulams/sdk";
import { apiFor } from "./data.ts";

/**
 * Author session for the Course Builder studio. The Passport token of a tutor or admin lives in an
 * httpOnly cookie (separate from the learner session); the browser only talks to the BFF
 * (/studio/api/…), which adds it. On demo tenants the studio signs in as the demo admin.
 */
export const AUTHOR_COOKIE = "ulams_author";

export function setAuthorCookie(cookies: AstroCookies, token: string, expiresAt: number, secure: boolean): void {
  cookies.set(AUTHOR_COOKIE, token, { httpOnly: true, sameSite: "lax", secure, path: "/", expires: new Date(expiresAt) });
}

export function clearAuthorCookie(cookies: AstroCookies): void {
  cookies.delete(AUTHOR_COOKIE, { path: "/" });
}

const expiry = (value: string | null | undefined) => {
  const parsed = value ? Date.parse(value) : NaN;
  return Number.isNaN(parsed) ? Date.now() + 86_400_000 : parsed;
};

/** Cookie token, else a demo admin login when the tenant runs in demo mode; null means "sign in". */
export async function authorToken(tenant: Tenant, cookies: AstroCookies, secure: boolean): Promise<string | null> {
  const existing = cookies.get(AUTHOR_COOKIE)?.value;
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
  return createCourseBuilderClient({ baseUrl: tenant.apiUrl, token, timeoutMs: 20_000 });
}

/** Server-side Living Course client for SSR pages. */
export function livingCourseFor(tenant: Tenant, token: string) {
  return createLivingCourseClient({ baseUrl: tenant.apiUrl, token, timeoutMs: 20_000 });
}

export { STUDIO_RULES, LIVING_COURSE_RULES, LIVING_COURSE_PREFIX, isStudioCall, isLivingCourseCall } from "./studio-rules.ts";
