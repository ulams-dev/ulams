import type { APIRoute } from "astro";
import { config } from "../../../lib/config.ts";
import { beginLogin } from "../../../lib/login-flow.ts";
import { SOCIAL_CALLBACK_PATH, SOCIAL_HEADERS, enabledProviders, isSocialProvider, startUrl } from "../../../lib/social-login.ts";

export const prerender = false;

/** Starts a social login: keeps a fresh verifier in an httpOnly cookie and sends only its challenge to the API. */
export const GET: APIRoute = (ctx) => {
  const provider = ctx.params.provider;
  if (!ctx.locals.tenant || !isSocialProvider(provider) || !enabledProviders(config.socialLogin).includes(provider)) {
    return new Response("Not found", { status: 404, headers: SOCIAL_HEADERS });
  }
  return beginLogin(ctx, SOCIAL_CALLBACK_PATH, (tenant, returnUrl, challenge) => startUrl(tenant, provider, returnUrl, challenge));
};
