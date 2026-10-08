import type { APIRoute } from "astro";

/**
 * Same-origin proxy for the tenant's H5P service (api/h5p, GPL, isolated by ADR 0003).
 * The service only allows framing from the hosts in its frame-ancestors list (without the
 * dev port); served through this origin the player page counts as 'self'. Bytes pass
 * through unchanged; no H5P code is bundled into the front.
 */
const PASS_HEADERS = ["content-type", "cache-control", "etag", "last-modified", "content-security-policy", "location"];

export const ALL: APIRoute = async ({ params, request, locals, url }) => {
  const tenant = locals.tenant;
  if (!tenant) return new Response("Not found", { status: 404 });
  const target = `${tenant.apiUrl}/h5p/${params.path ?? ""}${url.search}`;
  const headers: Record<string, string> = { Accept: request.headers.get("accept") ?? "*/*" };
  const type = request.headers.get("content-type");
  if (type) headers["Content-Type"] = type;
  const auth = request.headers.get("authorization");
  if (auth) headers.Authorization = auth;
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
