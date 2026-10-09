import type { APIRoute } from "astro";
import { clearAuthorCookie, isStudioCall } from "../../../lib/studio.ts";

/**
 * Studio BFF: the browser calls /studio/api/…; the server adds the author's token (httpOnly
 * cookie) and forwards to the tenant's /api/admin/course-builder/…. JSON, multipart uploads and the
 * AG-UI event stream (piped as it arrives) pass through. Cross-site writes are refused by the
 * middleware; paths outside the allow-list are 404.
 */
export const ALL: APIRoute = async ({ params, request, locals, cookies, url }) => {
  const tenant = locals.tenant;
  const path = `/${params.path ?? ""}`;
  if (!tenant || !isStudioCall(request.method, path)) return json(404, { message: "Not found" });
  if (!locals.authorToken) return json(401, { message: "Sign in to use the Course Builder." });

  const isStream = path.endsWith("/events");
  const headers: Record<string, string> = {
    Accept: isStream ? "text/event-stream" : "application/json",
    Authorization: `Bearer ${locals.authorToken}`,
  };
  const lastEventId = request.headers.get("last-event-id");
  if (lastEventId) headers["Last-Event-ID"] = lastEventId;
  const contentType = request.headers.get("content-type");
  let body: BodyInit | undefined;
  if (!["GET", "HEAD"].includes(request.method)) {
    body = await request.arrayBuffer();
    if (contentType) headers["Content-Type"] = contentType;
  }

  let upstream: Response;
  try {
    upstream = await fetch(`${tenant.apiUrl}/api/admin/course-builder${path}${url.search}`, {
      method: request.method,
      headers,
      body,
      signal: isStream ? request.signal : AbortSignal.timeout(60_000),
    });
  } catch {
    return json(502, { message: "The API is unreachable. Try again in a moment." });
  }
  if (upstream.status === 401) clearAuthorCookie(cookies);

  if (isStream && upstream.ok && upstream.body) {
    return new Response(upstream.body, {
      status: 200,
      headers: { "Content-Type": "text/event-stream", "Cache-Control": "no-cache, no-transform", "X-Accel-Buffering": "no" },
    });
  }
  return new Response(await upstream.text(), {
    status: upstream.status,
    headers: { "Content-Type": upstream.headers.get("content-type") ?? "application/json", "Cache-Control": "no-store" },
  });
};

const json = (status: number, body: unknown) =>
  new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json", "Cache-Control": "no-store" } });
