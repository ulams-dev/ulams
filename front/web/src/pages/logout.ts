import type { APIRoute } from "astro";
import { tenantFetch } from "../lib/api-transport.ts";
import { clearSessionCookie, readSession } from "../lib/session.ts";

/**
 * Logs out of this host. With `?everywhere=1` it first asks the API to end every login session of the learner (one per
 * host of the academy after a hand-off, ADR 0195), with the learner's own token; scoped tokens of agents and CI stay.
 * Best effort: whatever the API answers, this host's cookie is cleared.
 */
export const POST: APIRoute = async ({ cookies, redirect, locals, url }) => {
  const token = readSession(cookies, locals.secure);
  if (locals.tenant && token && url.searchParams.get("everywhere") === "1") {
    try {
      await tenantFetch(locals.tenant)(new URL("/api/auth/logout", locals.tenant.apiUrl), {
        method: "POST",
        headers: { "Content-Type": "application/json", Accept: "application/json", Authorization: `Bearer ${token}` },
        body: JSON.stringify({ everywhere: true }),
        signal: AbortSignal.timeout(5_000),
      });
    } catch {
      // the cookie goes either way; the sessions of the other hosts end when they expire
    }
  }
  clearSessionCookie(cookies, locals.secure);
  return redirect("/", 303);
};
