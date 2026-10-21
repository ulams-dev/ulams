import type { APIRoute } from "astro";
import { tenantFetch } from "../../../lib/api-transport.ts";

/**
 * The PDF of one of the learner's own certificates. The BFF buffers text and cannot carry binary, so this
 * route streams it with the learner's token: the API checks the owner (403 for anyone else, which is shown
 * as 404 so ids cannot be probed). `attachment` and `no-store`: it is a personal record.
 */
export const GET: APIRoute = async ({ params, locals, url }) => {
  const tenant = locals.tenant;
  const id = String(params.id ?? "");
  if (!tenant || !/^\d{1,12}$/.test(id)) return new Response("Not found", { status: 404, headers: { "Cache-Control": "no-store" } });
  if (!locals.token) return Response.redirect(new URL(`/login?next=${encodeURIComponent(url.pathname)}`, url), 303);

  let upstream: Response;
  try {
    upstream = await tenantFetch(tenant)(`${tenant.apiUrl}/api/pdfs/generate/${id}`, {
      headers: { Accept: "application/pdf", Authorization: `Bearer ${locals.token}` },
      signal: AbortSignal.timeout(30_000),
    });
  } catch {
    return plain(502, "The certificate could not be fetched right now. Try again in a moment.");
  }
  if (upstream.status === 401) return Response.redirect(new URL(`/login?next=${encodeURIComponent(url.pathname)}`, url), 303);
  if (upstream.status === 403 || upstream.status === 404) return plain(404, "Not found");
  if (!upstream.ok) return plain(upstream.status === 422 ? 502 : 503, "The certificate could not be prepared right now. Try again in a moment.");

  const headers = new Headers({
    "Content-Type": "application/pdf",
    "Content-Disposition": upstream.headers.get("content-disposition") ?? `attachment; filename="certificate-${id}.pdf"`,
    "Cache-Control": "private, no-store",
    "X-Content-Type-Options": "nosniff",
  });
  const length = upstream.headers.get("content-length");
  if (length) headers.set("Content-Length", length);
  return new Response(upstream.body, { status: 200, headers });
};

const plain = (status: number, text: string) => new Response(text, { status, headers: { "Content-Type": "text/plain; charset=utf-8", "Cache-Control": "no-store" } });
