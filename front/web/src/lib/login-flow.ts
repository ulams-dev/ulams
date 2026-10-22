import type { APIContext } from "astro";
import type { Tenant } from "@ulams/sdk";
import { ownOrigins } from "./bff.ts";
import { cookieName } from "./cookies.ts";
import { config } from "./config.ts";
import { setSessionCookie } from "./session.ts";
import {
  SOCIAL_COOKIE_BASE,
  SOCIAL_COOKIE_MAX_AGE,
  SOCIAL_HEADERS,
  challengeFor,
  decodeCookie,
  encodeCookie,
  newVerifier,
  safeNext,
  type Exchange,
} from "./social-login.ts";

/**
 * The browser side of every redirect-based login (social, single sign-on; ADR 0121): keep a fresh
 * verifier in an httpOnly cookie and send only its challenge out; on the way back redeem the one-time
 * code with that verifier, server to server. One implementation, so the security properties are the same
 * for every provider.
 */
type Ctx = Pick<APIContext, "request" | "url" | "cookies" | "locals" | "redirect">;

const withHeaders = (response: Response): Response => {
  for (const [name, value] of Object.entries(SOCIAL_HEADERS)) response.headers.set(name, value);
  return response;
};

/**
 * Starts a login. `startUrl` builds the tenant API URL from this site's callback URL and the challenge.
 * `callbackPath` is the page the API sends the browser back to.
 */
export function beginLogin(ctx: Ctx, callbackPath: string, startUrl: (tenant: Pick<Tenant, "apiUrl">, returnUrl: string, challenge: string) => string): Response {
  const { request, url, cookies, locals, redirect } = ctx;
  const tenant = locals.tenant;
  if (!tenant) return new Response("Not found", { status: 404, headers: SOCIAL_HEADERS });

  // this site's own origin as the browser sees it (the Host the proxy forwards), the one the API allow-lists
  const secure = locals.secure;
  const host = request.headers.get("x-forwarded-host") ?? request.headers.get("host") ?? url.host;
  const origin = ownOrigins(host, secure)[0];
  if (!origin) return new Response("Bad request", { status: 400, headers: SOCIAL_HEADERS });

  const verifier = newVerifier();
  cookies.set(cookieName(SOCIAL_COOKIE_BASE, secure, config.cookieFallbackPrefix), encodeCookie({ verifier, next: safeNext(url.searchParams.get("next")) }), {
    httpOnly: true,
    sameSite: "lax", // the return trip is a top-level GET, which Lax cookies accompany
    secure,
    path: "/",
    maxAge: SOCIAL_COOKIE_MAX_AGE,
  });

  return withHeaders(redirect(startUrl(tenant, `${origin}${callbackPath}`, challengeFor(verifier)), 302));
}

/**
 * Where the API sends the browser after a login: `?code=` (one-time) or `?error=`. The code is redeemed
 * with the verifier from the cookie, the access token goes into the session cookie, and the browser is
 * redirected on, so the URL with the code is never shown or kept.
 */
export async function finishLogin(
  ctx: Ctx,
  redeem: (tenant: Pick<Tenant, "apiUrl">, code: string, verifier: string) => Promise<Exchange>
): Promise<Response> {
  const { url, cookies, locals, redirect } = ctx;
  const tenant = locals.tenant;
  if (!tenant) return new Response("Not found", { status: 404, headers: SOCIAL_HEADERS });

  const secure = locals.secure;
  const name = cookieName(SOCIAL_COOKIE_BASE, secure, config.cookieFallbackPrefix);
  const saved = decodeCookie(cookies.get(name)?.value);
  // single use on this side as well: whatever happens next, the verifier is gone
  cookies.delete(name, { path: "/", secure });

  const fail = (reason: string) => withHeaders(redirect(`/login?error=${encodeURIComponent(reason)}`, 303));

  const error = url.searchParams.get("error");
  if (error) return fail(error);
  const code = url.searchParams.get("code");
  if (!code || !saved) return fail("invalid");

  const result = await redeem(tenant, code, saved.verifier);
  if (!result.ok) return fail("invalid");
  // the provider gave no e-mail address: finishing the sign-up needs a form this site does not have
  if (!result.complete) return fail("email_required");

  setSessionCookie(cookies, result.token, result.expiresAt, secure);
  return withHeaders(redirect(saved.next, 303));
}
