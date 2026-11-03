import type { APIRoute } from "astro";
import { publicOrigin } from "../i18n/locales.ts";

export const GET: APIRoute = ({ request, url, locals }) => {
  if (!locals.platform) return new Response("Not found", { status: 404 });
  const sitemap = new URL("/sitemap.xml", publicOrigin(request.headers, url)).href;
  return new Response(`User-agent: *\nAllow: /\nDisallow: /studio/\nDisallow: /preview/\nSitemap: ${sitemap}\n`, {
    headers: { "Content-Type": "text/plain; charset=utf-8", "Cache-Control": "public, max-age=3600" },
  });
};
