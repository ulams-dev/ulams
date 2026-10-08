import type { AstroCookies } from "astro";
import { ApiError, demoStudentSession, type Tenant } from "@ulams/sdk";
import { config } from "./config.ts";
import { apiFor } from "./data.ts";

export const SESSION_COOKIE = "ulams_session";

interface DemoToken {
  token: string;
  expiresAt: number;
  via: "demo" | "password";
}

/**
 * Demo tokens per tenant. Every visitor of a demo tenant is the same seeded student
 * (that is what `POST /api/demo/login` does too), so one token per tenant is shared:
 * a new visitor does not wait for a login round trip.
 */
const demoTokens = new Map<string, DemoToken>();
const pending = new Map<string, Promise<DemoToken>>();

const DAY = 86_400_000;

function expiry(expiresAt: string | null | undefined): number {
  const parsed = expiresAt ? Date.parse(expiresAt) : NaN;
  return Number.isNaN(parsed) ? Date.now() + DAY : parsed;
}

export function demoCredentials(tenant: Tenant): { email: string; password: string } | null {
  if (!config.demoStudentPassword) return null;
  return { email: config.demoStudentEmail.split("{slug}").join(tenant.slug), password: config.demoStudentPassword };
}

/** A demo student token for the tenant (cached, refreshed when `force`). */
export async function demoToken(tenant: Tenant, force = false): Promise<DemoToken> {
  const cached = demoTokens.get(tenant.apiUrl);
  if (!force && cached && cached.expiresAt - Date.now() > 60_000) return cached;
  const running = pending.get(tenant.apiUrl);
  if (running) return running;
  const promise = demoStudentSession(apiFor(tenant), { fallback: demoCredentials(tenant) })
    .then((session) => {
      const value = { token: session.token, expiresAt: expiry(session.expires_at), via: session.via };
      demoTokens.set(tenant.apiUrl, value);
      return value;
    })
    .finally(() => pending.delete(tenant.apiUrl));
  pending.set(tenant.apiUrl, promise);
  return promise;
}

export function forgetDemoToken(tenant: Tenant, token: string): void {
  if (demoTokens.get(tenant.apiUrl)?.token === token) demoTokens.delete(tenant.apiUrl);
}

export function setSessionCookie(cookies: AstroCookies, token: string, expiresAt: number, secure: boolean): void {
  cookies.set(SESSION_COOKIE, token, {
    httpOnly: true,
    sameSite: "lax",
    secure,
    path: "/",
    expires: new Date(expiresAt),
  });
}

export function clearSessionCookie(cookies: AstroCookies): void {
  cookies.delete(SESSION_COOKIE, { path: "/" });
}

/** Token from the cookie, or a fresh demo session stored in the cookie. Null when login is impossible. */
export async function ensureSession(
  tenant: Tenant,
  cookies: AstroCookies,
  secure: boolean
): Promise<{ token: string; via: "cookie" | "demo" | "password" } | null> {
  const existing = cookies.get(SESSION_COOKIE)?.value;
  if (existing) return { token: existing, via: "cookie" };
  try {
    const demo = await demoToken(tenant);
    setSessionCookie(cookies, demo.token, demo.expiresAt, secure);
    return { token: demo.token, via: demo.via };
  } catch (error) {
    if (error instanceof ApiError) return null;
    throw error;
  }
}

/** After a 401 (tokens are wiped by the hourly demo reset): drop the session and log in again once. */
export async function renewSession(tenant: Tenant, cookies: AstroCookies, secure: boolean, stale: string) {
  forgetDemoToken(tenant, stale);
  clearSessionCookie(cookies);
  try {
    const demo = await demoToken(tenant, true);
    setSessionCookie(cookies, demo.token, demo.expiresAt, secure);
    return demo.token;
  } catch {
    return null;
  }
}
