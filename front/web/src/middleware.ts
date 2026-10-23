import { defineMiddleware } from "astro:middleware";
import { umamiEnabled } from "@ulams/ui/analytics";
import { config } from "./lib/config.ts";
import { isPlatformHost, tenantForHost } from "./lib/tenant.ts";
import { getFrameOrigins, getSettings, warm } from "./lib/data.ts";
import { tenantLogo } from "./lib/tenant-logo.ts";
import { contentOriginFor, cspHeaders, wantsCsp } from "./lib/csp.ts";
import { ensureSession, readSession } from "./lib/session.ts";
import { H5P_ROUTE } from "./lib/h5p-proxy.ts";
import { imageCache } from "./lib/image-cache.ts";
import { resolveTenant, type Tenant } from "@ulams/sdk/tenant";
import { refuseCrossSite } from "./lib/bff.ts";
import { isSecureRequest } from "./lib/cookies.ts";
import { EntryLookupError, entryCookieName, runWithEntry, type EntryContext } from "./lib/entry.ts";
import { entries, tenantOfSlug } from "./lib/entry-runtime.ts";
import { authorCookieName, authorToken } from "./lib/studio.ts";
import { PREVIEW_HEADERS, isPreviewPath } from "./lib/preview.ts";
import { CLI_HEADERS, isCliPath } from "./lib/cli-authorize.ts";
import { SOCIAL_HEADERS, isSocialPath } from "./lib/social-login.ts";
import { HANDOFF_HEADERS, isHandoffPath } from "./lib/handoff.ts";

let warmed = false;
function warmOnce(): void {
  if (warmed) return;
  warmed = true;
  const firstRule = config.tenantHosts.split(/[,\n]+/)[0]?.split("=>")[0]?.trim() ?? "";
  const tenants = config.warmTenants
    .map((slug) => resolveTenant(firstRule.replace("{slug}", slug), { pattern: config.tenantHosts, adminUrlTemplate: config.adminUrl }))
    .filter((t): t is NonNullable<typeof t> => t !== null);
  warm(tenants);
}

/** The entry point `/go/<key>` chose for this browser on the academy's own host, if it still exists. */
async function entryFromCookie(tenant: Tenant, cookies: import("astro").AstroCookies, secure: boolean): Promise<EntryContext | null> {
  const key = cookies.get(entryCookieName(secure, config.cookieFallbackPrefix))?.value;
  if (!key) return null;
  try {
    const found = await entries.byKey(tenant.slug, key);
    if (found) return { ...found, via: "go" };
    // deleted or disabled since: the cookie is stale
    cookies.delete(entryCookieName(secure, config.cookieFallbackPrefix), { path: "/" });
  } catch {
    // the platform cannot say: show the academy as it is, the cookie stays for the next request
  }
  return null;
}

const SESSION_ROUTES = /^\/(learn|bff|account)(\/|$)/;
const STUDIO_ROUTES = /^\/studio(\/|$)/;

export const onRequest = defineMiddleware(async (context, next) => {
  warmOnce();
  const { request, url, locals, cookies } = context;
  const host = request.headers.get("x-forwarded-host") ?? request.headers.get("host") ?? url.host;

  if (url.pathname === "/_image") {
    return imageCache(url, () => next());
  }

  // CSRF: every state-changing request must come from this site (forms, BFF, H5P proxy).
  const secure = isSecureRequest(request.headers, url.protocol, config.cookieSecure);
  locals.secure = secure;
  const refused = refuseCrossSite(request, host, secure);
  if (refused) return refused;

  locals.platform = isPlatformHost(host, config.platformHosts);
  locals.entry = null;
  let resolved: Tenant | null = null;
  if (!locals.platform) {
    resolved = resolveTenant(host, { pattern: config.tenantHosts, adminUrlTemplate: config.adminUrl });
    if (!resolved && url.pathname !== "/healthz") {
      // a host no rule matches may be an entry point of an academy; "the platform cannot say" must not fall through
      // to the default tenant (that would show another academy on this host)
      try {
        const found = await entries.byHost(host);
        const owner = found ? tenantOfSlug(found.tenant) : null;
        if (found && owner) {
          resolved = owner;
          locals.entry = { ...found, via: "host" };
        }
      } catch (error) {
        if (!(error instanceof EntryLookupError)) throw error;
        return new Response("This address cannot be served right now. Try again in a moment.", { status: 503, headers: { "Retry-After": "5", "Cache-Control": "no-store", "Content-Type": "text/plain; charset=utf-8" } });
      }
    }
    resolved ??= tenantForHost(host, config);
    if (resolved && !locals.entry) locals.entry = await entryFromCookie(resolved, cookies, secure);
  }
  locals.tenant = resolved;
  locals.token = null;
  locals.sessionVia = null;
  locals.authorToken = null;

  if (locals.tenant && SESSION_ROUTES.test(url.pathname)) {
    // Prefetch/prerender requests may log in too: the next navigation is then instant.
    const session = await ensureSession(locals.tenant, cookies, secure);
    locals.token = session?.token ?? null;
    locals.sessionVia = session?.via ?? null;
  } else if (locals.tenant && H5P_ROUTE.test(url.pathname)) {
    // The /h5p proxy acts as the learner when the cookie is there (ADR 0045). It never starts a
    // session itself: the lesson page does, and the player's assets are public.
    locals.token = readSession(cookies, secure) ?? null;
  }

  // Course Builder studio: the author's session (tutor or admin), separate from the learner's
  if (locals.tenant && STUDIO_ROUTES.test(url.pathname) && url.pathname !== "/studio/login") {
    locals.authorToken = await authorToken(locals.tenant, cookies, secure);
    if (!locals.authorToken && !url.pathname.startsWith("/studio/api/")) {
      return context.redirect(`/studio/login?next=${encodeURIComponent(url.pathname)}`, 303);
    }
  }

  // Author preview: only the studio author's own cookie counts. Never the learner (demo student)
  // session and never the demo-admin auto sign-in of the studio: without the cookie it is a 404.
  if (locals.tenant && isPreviewPath(url.pathname)) {
    locals.authorToken = cookies.get(authorCookieName(secure))?.value ?? null;
  }

  // CSP: the tool origins are cached (5 min), asked for while the page renders
  const csp = wantsCsp(url.pathname, null) && !url.pathname.startsWith("/_") ? (locals.tenant ? getFrameOrigins(locals.tenant) : Promise.resolve([] as string[])) : null;

  // The tenant's logo for the site header: cached public settings, never a reason to hold the page up
  if (csp && locals.tenant) {
    const tenant = locals.tenant;
    locals.tenantLogo = await Promise.race([
      getSettings(tenant).then((settings) => tenantLogo(settings)).catch(() => null),
      new Promise<null>((resolve) => setTimeout(() => resolve(null), 900)),
    ]);
  }

  const response = await runWithEntry(locals.entry, () => next());
  if (locals.entry && !locals.entry.seo.index) response.headers.set("X-Robots-Tag", "noindex, nofollow");
  if (csp && wantsCsp(url.pathname, response.headers.get("content-type"))) {
    const headers = cspHeaders(
      {
        tenant: locals.tenant,
        analytics: umamiEnabled(host),
        toolOrigins: await csp,
        contentOrigin: locals.tenant ? contentOriginFor(config.contentOrigin, locals.tenant.slug) : null,
        storageOrigins: config.storageOrigins,
      },
      config.cspEnforce
    );
    for (const [name, value] of Object.entries(headers)) response.headers.set(name, value);
  }
  if (isPreviewPath(url.pathname)) {
    for (const [name, value] of Object.entries(PREVIEW_HEADERS)) response.headers.set(name, value);
  }
  response.headers.set("X-Content-Type-Options", "nosniff");
  response.headers.set("Referrer-Policy", "strict-origin-when-cross-origin");
  if (isCliPath(url.pathname)) {
    // device-login approval: never cached, never framed, the code never leaves through Referer
    for (const [name, value] of Object.entries(CLI_HEADERS)) response.headers.set(name, value);
  }
  if (isSocialPath(url.pathname)) {
    // the callback URL carries a one-time code: never cached, never passed on in a Referer
    for (const [name, value] of Object.entries(SOCIAL_HEADERS)) response.headers.set(name, value);
  }
  if (isHandoffPath(url.pathname)) {
    // the hand-off pages carry a one-time code: never cached, never passed on in a Referer
    for (const [name, value] of Object.entries(HANDOFF_HEADERS)) response.headers.set(name, value);
  }
  if (!response.headers.has("Cache-Control") && (response.headers.get("content-type") ?? "").includes("text/html")) {
    response.headers.set("Cache-Control", "private, no-cache");
  }
  return response;
});
