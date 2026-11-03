import type { APIRoute } from "astro";
import { finishLogin } from "../../../lib/login-flow.ts";
import { exchangeCode } from "../../../lib/social-login.ts";

export const prerender = false;

/** Where the API sends the browser after a social login: `?code=` or `?error=` (see lib/login-flow.ts). */
export const GET: APIRoute = (ctx) => finishLogin(ctx, (tenant, code, verifier) => exchangeCode(tenant, code, verifier));
