import type { APIRoute } from "astro";
import { beginLogin } from "../../../lib/login-flow.ts";
import { SOCIAL_HEADERS } from "../../../lib/social-login.ts";
import { SSO_CALLBACK_PATH, isSsoSlug, listProviders, ssoStartUrl } from "../../../lib/sso-login.ts";

export const prerender = false;

/** Starts a single sign-on: only for providers the tenant lists as enabled. */
export const GET: APIRoute = async (ctx) => {
  const tenant = ctx.locals.tenant;
  const slug = ctx.params.slug;
  if (!tenant || !isSsoSlug(slug) || !(await listProviders(tenant)).some((p) => p.slug === slug)) {
    return new Response("Not found", { status: 404, headers: SOCIAL_HEADERS });
  }
  return beginLogin(ctx, SSO_CALLBACK_PATH, (t, returnUrl, challenge) => ssoStartUrl(t, slug, returnUrl, challenge));
};
