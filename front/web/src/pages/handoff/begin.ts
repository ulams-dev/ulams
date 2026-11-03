import type { APIRoute } from "astro";
import { entries } from "../../lib/entry-runtime.ts";
import { HANDOFF_COOKIE_BASE, HANDOFF_HEADERS, HANDOFF_ISSUE_PATH, parseHostParam, siblingOrigin } from "../../lib/handoff.ts";
import { isSiblingHost } from "../../lib/handoff-runtime.ts";
import { isSpeculative, keepVerifier } from "../../lib/login-flow.ts";
import { challengeFor, newVerifier, safeNext } from "../../lib/social-login.ts";

export const prerender = false;

/**
 * Step 1, on the TARGET host (ADR 0195): the learner is on another host of this academy and wants to continue here.
 * Keeps a fresh verifier in an httpOnly cookie of this host and sends only its challenge to the source host.
 */
export const GET: APIRoute = async (ctx) => {
  const { request, url, locals, redirect } = ctx;
  const tenant = locals.tenant;
  const deny = (status: number) => new Response(status === 503 ? "Try again in a moment." : "Not found", { status, headers: HANDOFF_HEADERS });
  if (!tenant) return deny(404);
  // a speculative request must not leave a verifier behind that the real one would have to share the cookie with
  if (isSpeculative(request.headers)) return new Response(null, { status: 204, headers: HANDOFF_HEADERS });

  const host = request.headers.get("x-forwarded-host") ?? request.headers.get("host") ?? url.host;
  const from = parseHostParam(url.searchParams.get("from"));
  const origin = from ? siblingOrigin(from, host, locals.secure) : null;
  if (!from || !origin || from.name === parseHostParam(host)?.name) return deny(404);
  // only another host of THIS academy: the main host or one of its active entry points
  const verdict = await isSiblingHost(tenant, from.name, entries);
  if (verdict !== "yes") return deny(verdict === "unknown" ? 503 : 404);

  const verifier = newVerifier();
  const next = safeNext(url.searchParams.get("next"));
  keepVerifier(ctx, HANDOFF_COOKIE_BASE, verifier, next);

  const issue = new URL(HANDOFF_ISSUE_PATH, origin);
  issue.searchParams.set("to", host);
  issue.searchParams.set("challenge", challengeFor(verifier));
  const response = redirect(issue.toString(), 303);
  for (const [name, value] of Object.entries(HANDOFF_HEADERS)) response.headers.set(name, value);
  return response;
};
