import type { APIRoute } from "astro";
import { LOCALES, publicPagePath } from "../i18n/locales.ts";
import { publicOrigin } from "../i18n/locales.ts";

const xml = (value: string) => value.replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll('"', "&quot;");
const PAGES = ["overview", "comparison", "faq"] as const;

export const GET: APIRoute = ({ request, url, locals }) => {
  if (!locals.platform) return new Response("Not found", { status: 404 });
  const origin = publicOrigin(request.headers, url);
  const urls = PAGES.flatMap((page) => LOCALES.map((locale) => ({ page, locale, loc: new URL(publicPagePath(locale, page), origin).href })));
  const body = `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">\n${urls.map(({ page, loc }) => {
    const alternates = [...LOCALES.map((other) => `    <xhtml:link rel="alternate" hreflang="${other === "zh" ? "zh-Hans" : other}" href="${xml(new URL(publicPagePath(other, page), origin).href)}" />`), `    <xhtml:link rel="alternate" hreflang="x-default" href="${xml(new URL(publicPagePath("en", page), origin).href)}" />`].join("\n");
    return `  <url>\n    <loc>${xml(loc)}</loc>\n${alternates}\n  </url>`;
  }).join("\n")}\n</urlset>\n`;
  return new Response(body, { headers: { "Content-Type": "application/xml; charset=utf-8", "Cache-Control": "public, max-age=3600" } });
};
