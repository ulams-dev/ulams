import type { APIRoute } from "astro";
import { ownOrigins } from "../../../lib/bff.ts";
import { cookieName } from "../../../lib/cookies.ts";
import { config } from "../../../lib/config.ts";
import {
  SOCIAL_CALLBACK_PATH,
  SOCIAL_COOKIE_BASE,
  SOCIAL_COOKIE_MAX_AGE,
  SOCIAL_HEADERS,
  challengeFor,
  enabledProviders,
  encodeCookie,
  isSocialProvider,
  newVerifier,
  safeNext,
  startUrl,
} from "../../../lib/social-login.ts";

export const prerender = false;

/** Starts a social login: keeps a fresh verifier in an httpOnly cookie and sends only its challenge to the API. */
export const GET: APIRoute = ({ params, request, url, cookies, locals, redirect }) => {
  const tenant = locals.tenant;
  const provider = params.provider;
  if (!tenant || !isSocialProvider(provider) || !enabledProviders(config.socialLogin).includes(provider)) {
    return new Response("Not found", { status: 404, headers: SOCIAL_HEADERS });
  }

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

  const returnUrl = `${origin}${SOCIAL_CALLBACK_PATH}`;
  const response = redirect(startUrl(tenant, provider, returnUrl, challengeFor(verifier)), 302);
  for (const [name, value] of Object.entries(SOCIAL_HEADERS)) response.headers.set(name, value);
  return response;
};
