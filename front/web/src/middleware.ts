import { defineMiddleware } from "astro:middleware";
import { config } from "./lib/config.ts";
import { isPlatformHost, tenantForHost } from "./lib/tenant.ts";
import { getFrameOrigins, warm } from "./lib/data.ts";
import { contentOriginFor, cspHeaders, wantsCsp } from "./lib/csp.ts";
import { ensureSession } from "./lib/session.ts";
import { imageCache } from "./lib/image-cache.ts";
import { resolveTenant } from "@ulams/sdk/tenant";
import { refuseCrossSite } from "./lib/bff.ts";
import { isSecureRequest } from "./lib/cookies.ts";
import { authorCookieName, authorToken } from "./lib/studio.ts";
import { PREVIEW_HEADERS, isPreviewPath } from "./lib/preview.ts";
import { CLI_HEADERS, isCliPath } from "./lib/cli-authorize.ts";

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
  locals.tenant = locals.platform ? null : tenantForHost(host, config);
  locals.token = null;
  locals.sessionVia = null;
  locals.authorToken = null;

  if (locals.tenant && SESSION_ROUTES.test(url.pathname)) {
    // Prefetch/prerender requests may log in too: the next navigation is then instant.
    const session = await ensureSession(locals.tenant, cookies, secure);
    locals.token = session?.token ?? null;
    locals.sessionVia = session?.via ?? null;
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

  const response = await next();
  if (csp && wantsCsp(url.pathname, response.headers.get("content-type"))) {
    const headers = cspHeaders(
      {
        tenant: locals.tenant,
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
  if (!response.headers.has("Cache-Control") && (response.headers.get("content-type") ?? "").includes("text/html")) {
    response.headers.set("Cache-Control", "private, no-cache");
  }
  return response;
});
