import type { APIRoute } from "astro";
import { entries } from "../../lib/entry-runtime.ts";
import { HANDOFF_COMPLETE_PATH, HANDOFF_HEADERS, issueCode, parseHostParam, siblingOrigin } from "../../lib/handoff.ts";
import { isSiblingHost } from "../../lib/handoff-runtime.ts";
import { isSpeculative } from "../../lib/login-flow.ts";
import { readSession } from "../../lib/session.ts";
import { safeNext } from "../../lib/social-login.ts";

export const prerender = false;

/**
 * Step 2, on the SOURCE host (ADR 0195): the learner is signed in here and the target host sent them back with the
 * challenge of its verifier. Asks the API for a one-time code with the learner's own token and sends the browser on to
 * the target host with the code. The token itself never leaves this server.
 */
export const GET: APIRoute = async ({ request, url, locals, cookies, redirect }) => {
  const tenant = locals.tenant;
  const deny = (status: number) => new Response(status === 503 ? "Try again in a moment." : "Not found", { status, headers: HANDOFF_HEADERS });
  if (!tenant) return deny(404);
  if (isSpeculative(request.headers)) return new Response(null, { status: 204, headers: HANDOFF_HEADERS });

  const host = request.headers.get("x-forwarded-host") ?? request.headers.get("host") ?? url.host;
  const to = parseHostParam(url.searchParams.get("to"));
  const challenge = url.searchParams.get("challenge") ?? "";
  const origin = to ? siblingOrigin(to, host, locals.secure) : null;
  if (!to || !origin || !/^[A-Za-z0-9_-]{43}$/.test(challenge) || to.name === parseHostParam(host)?.name) return deny(404);

  // not signed in on this host: sign in first, then come straight back here (the challenge is public, a code is not made yet)
  const token = readSession(cookies, locals.secure);
  if (!token) return redirect(`/login?next=${encodeURIComponent(`${url.pathname}${url.search}`)}`, 303);

  const verdict = await isSiblingHost(tenant, to.name, entries);
  if (verdict !== "yes") return deny(verdict === "unknown" ? 503 : 404);

  const issued = await issueCode(tenant, token, to.name, challenge);
  if (!issued.ok) return deny(404);

  const target = new URL(HANDOFF_COMPLETE_PATH, origin);
  target.searchParams.set("code", issued.code);
  const next = url.searchParams.get("next");
  if (next) target.searchParams.set("next", safeNext(next));
  const response = redirect(target.toString(), 303);
  for (const [name, value] of Object.entries(HANDOFF_HEADERS)) response.headers.set(name, value);
  return response;
};
