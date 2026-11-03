import { tenantFetch } from "../../lib/api-transport.ts";
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
    tenantFetch(tenant)(`${tenant.apiUrl}${path}${url.search}`, {
      method: request.method,
      headers: {
        Accept: rule.stream ? "text/event-stream" : "application/json",
        "Content-Type": "application/json",
        Authorization: `Bearer ${token}`,
        "Current-timezone": request.headers.get("current-timezone") ?? "UTC",
        // the API picks the language from X-Locale (the translations package), not Accept-Language
        ...(rule.stream && request.headers.get("last-event-id") ? { "Last-Event-ID": request.headers.get("last-event-id")! } : {}),
        ...(request.headers.get("x-locale") ? { "X-Locale": request.headers.get("x-locale")! } : {}),
      },
      body,
      // an event stream lives as long as the browser keeps it open (the API closes it after ~25 s)
      signal: rule.stream ? request.signal : AbortSignal.timeout(20_000),
    });

  let response = await forward(locals.token).catch(() => null);
  if (response?.status === 401) {
    const fresh = await renewSession(tenant, cookies, locals.secure, locals.token);
    if (fresh) response = await forward(fresh).catch(() => null);
  }
  if (!response) return json(502, { message: "API unreachable" });
  if (rule.writesProgress && response.ok) invalidateProgress(tenant);
  if (rule.stream && response.ok && response.body) {
    return new Response(response.body, {
      status: 200,
      headers: { "Content-Type": "text/event-stream", "Cache-Control": "no-cache, no-transform", "X-Accel-Buffering": "no" },
    });
  }
  return new Response(await response.text(), {
    status: response.status,
    headers: { "Content-Type": response.headers.get("content-type") ?? "application/json", "Cache-Control": "no-store" },
  });
};

const json = (status: number, body: unknown) =>
  new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json", "Cache-Control": "no-store" } });
