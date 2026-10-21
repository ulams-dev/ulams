import type { APIRoute } from "astro";
import { cookieName } from "../../../lib/cookies.ts";
import { config } from "../../../lib/config.ts";
import { setSessionCookie } from "../../../lib/session.ts";
import { SOCIAL_COOKIE_BASE, SOCIAL_HEADERS, decodeCookie, exchangeCode } from "../../../lib/social-login.ts";

export const prerender = false;

/**
 * Where the API sends the browser after a social login: `?code=` (one-time) or `?error=`. The code
 * is redeemed server to server with the verifier from the cookie, the access token goes into the
 * session cookie, and the browser is redirected on, so the URL with the code is never shown or kept.
 */
export const GET: APIRoute = async ({ url, cookies, locals, redirect }) => {
  const tenant = locals.tenant;
  if (!tenant) return new Response("Not found", { status: 404, headers: SOCIAL_HEADERS });

  const secure = locals.secure;
  const name = cookieName(SOCIAL_COOKIE_BASE, secure, config.cookieFallbackPrefix);
  const saved = decodeCookie(cookies.get(name)?.value);
  // single use on this side as well: whatever happens next, the verifier is gone
  cookies.delete(name, { path: "/", secure });

  const fail = (reason: string) => {
    const response = redirect(`/login?error=${encodeURIComponent(reason)}`, 303);
    for (const [header, value] of Object.entries(SOCIAL_HEADERS)) response.headers.set(header, value);
    return response;
  };

  const error = url.searchParams.get("error");
  if (error) return fail(error);
  const code = url.searchParams.get("code");
  if (!code || !saved) return fail("invalid");

  const result = await exchangeCode(tenant, code, saved.verifier);
  if (!result.ok) return fail("invalid");
  // the provider gave no e-mail address: finishing the sign-up needs a form this site does not have
  if (!result.complete) return fail("email_required");

  setSessionCookie(cookies, result.token, result.expiresAt, secure);
  const response = redirect(saved.next, 303);
  for (const [header, value] of Object.entries(SOCIAL_HEADERS)) response.headers.set(header, value);
  return response;
};
