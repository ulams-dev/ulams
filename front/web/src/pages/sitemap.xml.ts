import type { APIRoute } from "astro";
import { LOCALES, LOCALE_META, publicOrigin, publicPagePath } from "../i18n/locales.ts";
import { BLOG_LOCALES, blogPath, blogSources } from "../lib/blog.ts";

const xml = (value: string) => value.replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll('"', "&quot;");
const PAGES = ["overview", "comparison"] as const;

export const GET: APIRoute = ({ request, url, locals }) => {
  if (!locals.platform) return new Response("Not found", { status: 404 });
  const origin = publicOrigin(request.headers, url);
  const entry = (loc: string, alternates: Array<{ hreflang: string; href: string }>, lastmod?: string) => {
    const links = alternates.map(({ hreflang, href }) => `    <xhtml:link rel="alternate" hreflang="${hreflang}" href="${xml(href)}" />`).join("\n");
    return `  <url>\n    <loc>${xml(loc)}</loc>\n${lastmod ? `    <lastmod>${lastmod}</lastmod>\n` : ""}${links}\n  </url>`;
  };
  const absolute = (path: string) => new URL(path, origin).href;
  const pageEntries = PAGES.flatMap((page) =>
    LOCALES.map((locale) => entry(absolute(publicPagePath(locale, page)), [
      ...LOCALES.map((other) => ({ hreflang: other === "zh" ? "zh-Hans" : other, href: absolute(publicPagePath(other, page)) })),
      { hreflang: "x-default", href: absolute(publicPagePath("en", page)) },
    ]))
  );
  // The blog exists in English and Polish; each post links to its other-language version.
  const blogAlternates = (slug?: string) => [
    ...BLOG_LOCALES.map((code) => ({ hreflang: LOCALE_META[code].lang, href: absolute(blogPath(code, slug)) })),
    { hreflang: "x-default", href: absolute(blogPath("en", slug)) },
  ];
  const newest = blogSources[0]?.date;
  const blogEntries = [
    ...BLOG_LOCALES.map((code) => entry(absolute(blogPath(code)), blogAlternates(), newest)),
    ...blogSources.flatMap((post) => BLOG_LOCALES.map((code) => entry(absolute(blogPath(code, post.slug)), blogAlternates(post.slug), post.date))),
  ];
  const body = `<?xml version="1.0" encoding="UTF-8"?>\n<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">\n${[...pageEntries, ...blogEntries].join("\n")}\n</urlset>\n`;
  return new Response(body, { headers: { "Content-Type": "application/xml; charset=utf-8", "Cache-Control": "public, max-age=3600" } });
};
