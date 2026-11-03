import type { APIRoute } from "astro";
import { config } from "../../lib/config.ts";
import { sessionCookieOptions } from "../../lib/cookies.ts";
import { EntryLookupError, KEY_PATTERN, entryCookieName, safeNext } from "../../lib/entry.ts";
import { entries } from "../../lib/entry-runtime.ts";

/**
 * `/go/<key>` on an academy's own host (ADR 0195, the fallback that needs no DNS): looks through the entry point `key`
 * for the rest of the browser session. Only presentation changes (theme, what is shown first); access never depends on
 * it. `/go/main` goes back to the academy's own pages. The cookie holds the key, never a token, and dies with the
 * session; a stale key is ignored and removed by the middleware.
 */
export const GET: APIRoute = async ({ params, url, locals, cookies, redirect }) => {
  const key = params.key ?? "";
  // the academy's own host only: on an entry host the entry point is already chosen by the address
  if (!locals.tenant || locals.entry?.via === "host") return new Response("Not found", { status: 404 });
  const name = entryCookieName(locals.secure, config.cookieFallbackPrefix);
  const next = safeNext(url.searchParams.get("next"));

  if (key === "main") {
    cookies.delete(name, { path: "/" });
    return redirect(next, 303);
  }
  if (!KEY_PATTERN.test(key)) return new Response("Not found", { status: 404 });
  try {
    if (!(await entries.byKey(locals.tenant.slug, key))) return new Response("Not found", { status: 404 });
  } catch (error) {
    if (!(error instanceof EntryLookupError)) throw error;
    return new Response("This address cannot be served right now. Try again in a moment.", { status: 503, headers: { "Retry-After": "5", "Cache-Control": "no-store" } });
  }
  // a session cookie: no expiry, no Domain, `__Host-` on https like the session cookies
  const { expires: _expires, ...options } = sessionCookieOptions(locals.secure, Date.now());
  cookies.set(name, key, options);
  return redirect(next, 303);
};
