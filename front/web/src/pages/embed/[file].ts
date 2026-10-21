import type { APIRoute } from "astro";
import badge from "../../embed/ulams-certificate-badge.js?raw";

/**
 * Embeddable widgets for plain pages, built by `scripts/build-embed.mjs`: one script tag, no framework.
 * A module script from another origin needs CORS, so the files carry `Access-Control-Allow-Origin: *`
 * (they are public code, no cookies involved).
 */
const FILES: Record<string, string> = { "ulams-certificate-badge.js": badge };

export const GET: APIRoute = ({ params }) => {
  const body = FILES[String(params.file ?? "")];
  if (!body) return new Response("Not found", { status: 404, headers: { "Cache-Control": "no-store" } });
  return new Response(body, {
    headers: {
      "Content-Type": "text/javascript; charset=utf-8",
      "Access-Control-Allow-Origin": "*",
      "Cross-Origin-Resource-Policy": "cross-origin",
      "Cache-Control": "public, max-age=3600",
      "X-Content-Type-Options": "nosniff",
    },
  });
};
