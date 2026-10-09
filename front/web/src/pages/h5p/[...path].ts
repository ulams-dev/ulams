import type { APIRoute } from "astro";
import { h5pSearch, h5pUsesSession } from "../../lib/h5p-proxy.ts";

/**
 * Same-origin proxy for the tenant's H5P service (api/h5p, GPL, isolated by ADR 0003).
 * The service only allows framing from the hosts in its frame-ancestors list (without the
 * dev port); served through this origin the player page counts as 'self'. Bytes pass
 * through unchanged; no H5P code is bundled into the front.
 *
 * Learner state: the frame has no API token (the session cookie is httpOnly and the embed page is
 * told `token: null`). For the player's own calls (see lib/h5p-proxy.ts) the proxy adds the session
 * token server-side, so saved state loads and saves while the token never reaches H5P content code
 * (ADR 0045). The token goes only to the tenant's API host, which is the only target of this route.
 */
const PASS_HEADERS = ["content-type", "cache-control", "etag", "last-modified", "content-security-policy", "location"];

export const ALL: APIRoute = async ({ params, request, locals, url }) => {
  const tenant = locals.tenant;
  if (!tenant) return new Response("Not found", { status: 404 });
  const session = locals.token && h5pUsesSession(request.method, params.path ?? "") ? locals.token : null;
  const target = `${tenant.apiUrl}/h5p/${params.path ?? ""}${session ? h5pSearch(url.search) : url.search}`;
  const headers: Record<string, string> = { Accept: request.headers.get("accept") ?? "*/*" };
  const type = request.headers.get("content-type");
  if (type) headers["Content-Type"] = type;
  const auth = request.headers.get("authorization");
  if (session) {
    headers.Authorization = `Bearer ${session}`;
    // the service keeps the token out of the model's URLs for requests like this one
    headers["X-Ulams-Session-Proxy"] = "1";
  } else if (auth) headers.Authorization = auth;
  const init: RequestInit & { duplex?: "half" } = {
    method: request.method,
    headers,
    redirect: "manual",
    signal: AbortSignal.timeout(30_000),
  };
  if (request.method !== "GET" && request.method !== "HEAD") {
    init.body = request.body;
    init.duplex = "half";
  }
  const upstream = await fetch(target, init).catch(() => null);
  if (!upstream) return new Response("H5P service unreachable", { status: 502 });
  const out = new Headers();
  for (const name of PASS_HEADERS) {
    const value = upstream.headers.get(name);
    if (value) out.set(name, name === "location" ? value.replace(tenant.apiUrl, url.origin) : value);
  }
  // The embed page posts messages only to the origins in its config, which do not include
  // this dev origin (port 4321). Served through this proxy it is only ever framed by this
  // origin, so the list becomes just this origin (also avoids console noise from the others).
  if ((upstream.headers.get("content-type") ?? "").includes("text/html") && (params.path ?? "").startsWith("embed/")) {
    const html = (await upstream.text()).replace(/"allowedOrigins":\[[^\]]*\]/, `"allowedOrigins":[${JSON.stringify(url.origin)}]`);
    return new Response(html, { status: upstream.status, headers: out });
  }
  return new Response(upstream.body, { status: upstream.status, headers: out });
};
