import { defineMiddleware } from "astro:middleware";
import { config } from "./lib/config.ts";
import { isPlatformHost, tenantForHost } from "./lib/tenant.ts";
import { warm } from "./lib/data.ts";
import { ensureSession } from "./lib/session.ts";
import { imageCache } from "./lib/image-cache.ts";
import { resolveTenant } from "@ulams/sdk/tenant";
import { isSameOrigin } from "./lib/bff.ts";

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

export const onRequest = defineMiddleware(async (context, next) => {
  warmOnce();
  const { request, url, locals, cookies } = context;
  const host = request.headers.get("x-forwarded-host") ?? request.headers.get("host") ?? url.host;

  if (url.pathname === "/_image") {
    return imageCache(url, () => next());
  }

  // CSRF: every state-changing request must come from this site (forms, BFF, H5P proxy).
  const ownOrigin = `${url.protocol}//${host}`;
  if (!isSameOrigin(request, ownOrigin)) {
    return new Response(JSON.stringify({ message: "Cross-site request refused" }), {
      status: 403,
      headers: { "Content-Type": "application/json" },
    });
  }

  locals.platform = isPlatformHost(host, config.platformHosts);
  locals.tenant = locals.platform ? null : tenantForHost(host, config);
  locals.token = null;
  locals.sessionVia = null;

  if (locals.tenant && SESSION_ROUTES.test(url.pathname)) {
    // Prefetch/prerender requests may log in too: the next navigation is then instant.
    const session = await ensureSession(locals.tenant, cookies, url.protocol === "https:");
    locals.token = session?.token ?? null;
    locals.sessionVia = session?.via ?? null;
  }

  const response = await next();
  response.headers.set("X-Content-Type-Options", "nosniff");
  response.headers.set("Referrer-Policy", "strict-origin-when-cross-origin");
  if (!response.headers.has("Cache-Control") && (response.headers.get("content-type") ?? "").includes("text/html")) {
    response.headers.set("Cache-Control", "private, no-cache");
  }
  return response;
});
