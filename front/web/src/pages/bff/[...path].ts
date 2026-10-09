import type { APIRoute } from "astro";
import { matchBffRule } from "../../lib/bff.ts";
import { invalidateProgress } from "../../lib/data.ts";
import { renewSession } from "../../lib/session.ts";

/**
 * Backend-for-frontend: the browser calls /bff/api/…; the server adds the session token
 * (httpOnly cookie, never readable by scripts) and forwards to the tenant API. Cross-site
 * writes are refused by the middleware.
 */
export const ALL: APIRoute = async ({ params, request, locals, cookies, url }) => {
  const tenant = locals.tenant;
  const path = `/${params.path ?? ""}`;
  const rule = matchBffRule(request.method, path);
  if (!tenant || !rule) return json(404, { message: "Not found" });
  if (!locals.token) return json(401, { message: "No session" });

  const body = request.method === "GET" ? undefined : await request.text();
  const forward = (token: string) =>
    fetch(`${tenant.apiUrl}${path}${url.search}`, {
      method: request.method,
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
        "Current-timezone": request.headers.get("current-timezone") ?? "UTC",
      },
      body,
      signal: AbortSignal.timeout(20_000),
    });

  let response = await forward(locals.token).catch(() => null);
  if (response?.status === 401) {
    const fresh = await renewSession(tenant, cookies, locals.secure, locals.token);
    if (fresh) response = await forward(fresh).catch(() => null);
  }
  if (!response) return json(502, { message: "API unreachable" });
  if (rule.writesProgress && response.ok) invalidateProgress(tenant);
  return new Response(await response.text(), {
    status: response.status,
    headers: { "Content-Type": response.headers.get("content-type") ?? "application/json", "Cache-Control": "no-store" },
  });
};

const json = (status: number, body: unknown) =>
  new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json", "Cache-Control": "no-store" } });
