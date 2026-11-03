import type { APIRoute } from "astro";
import { HANDOFF_COOKIE_BASE, HANDOFF_HEADERS, parseHostParam } from "../../lib/handoff.ts";
import { finishLogin } from "../../lib/login-flow.ts";
import { exchangeCode } from "../../lib/social-login.ts";

export const prerender = false;

/**
 * Step 3, on the TARGET host (ADR 0195): redeems the one-time code with the verifier kept in this browser's cookie, server
 * to server, and sets a session cookie of this host. Where to go afterwards was kept in the cookie by step 1, so the URL
 * with the code is never shown or kept.
 */
export const GET: APIRoute = (ctx) => {
  const host = ctx.request.headers.get("x-forwarded-host") ?? ctx.request.headers.get("host") ?? ctx.url.host;
  const name = parseHostParam(host)?.name;
  if (!name) return new Response("Bad request", { status: 400, headers: HANDOFF_HEADERS });
  return finishLogin(
    ctx,
    (tenant, code, verifier) => exchangeCode(tenant, code, verifier, undefined, "/api/auth/handoff/exchange", { host: name }),
    HANDOFF_COOKIE_BASE,
    HANDOFF_HEADERS
  );
};
