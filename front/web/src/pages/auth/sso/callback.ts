import type { APIRoute } from "astro";
import { finishLogin } from "../../../lib/login-flow.ts";
import { exchangeSsoCode } from "../../../lib/sso-login.ts";

export const prerender = false;

/** Where the API sends the browser after a single sign-on: `?code=` or `?error=` (see lib/login-flow.ts). */
export const GET: APIRoute = (ctx) => finishLogin(ctx, (tenant, code, verifier) => exchangeSsoCode(tenant, code, verifier));
