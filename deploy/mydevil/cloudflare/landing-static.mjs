#!/usr/bin/env node
// Export the product pages (overview, comparison, FAQ, content cost, reporting, course generation and its case studies, learning elements, style guide and white paper, in three languages), the blog (English and Polish) and their SEO routes from the production Astro build.
// Build and run this with the production env of the front, then pass a new output directory and the public host.
// ULAMS_IMAGE_SERVICE must be `cloudflare` (images become same-host /cdn-cgi/image URLs; check the host first with
// bin/check-images.sh) or `noop` (original images); `astro` would leave /_image URLs that a static host cannot serve.
// ULAMS_ADMIN_URL must be set to the admin pattern, 'https://{slug}-admin.ulams.app'; without it the demo links in the
// export point at the localhost default.
import { spawn } from "node:child_process";
import { request } from "node:http";
import { cpSync, existsSync, mkdirSync, readdirSync, writeFileSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

if (!process.argv[2]) throw new Error("usage: landing-static.mjs <new-output-directory> [public-host]");
const out = resolve(process.argv[2]);
if (existsSync(out)) throw new Error("Output directory must be new; existing files will not be replaced.");
const host = process.argv[3] ?? "ulams.app";
const imageService = (process.env.ULAMS_IMAGE_SERVICE ?? "").trim();
if (!["cloudflare", "noop"].includes(imageService)) {
  throw new Error(`ULAMS_IMAGE_SERVICE must be "cloudflare" or "noop" for the static export (got "${imageService}"); "astro" and unset leave /_image URLs that need a server.`);
}
if (!(process.env.ULAMS_ADMIN_URL ?? "").includes("{slug}")) {
  throw new Error("ULAMS_ADMIN_URL must be set to the admin pattern, e.g. 'https://{slug}-admin.ulams.app'; the default points at localhost.");
}
const root = resolve(dirname(fileURLToPath(import.meta.url)), "../../..");
const dist = join(root, "front/web/dist");
const port = Number(process.env.ULAMS_STATIC_RENDER_PORT ?? 4399);
const overviews = ["/", "/pl/", "/zh/"];
// One directory per post in front/web/src/blog (meta.json, en.md, pl.md); the media is in public/blog and is copied with the client build.
const posts = readdirSync(join(root, "front/web/src/blog"), { withFileTypes: true }).filter((entry) => entry.isDirectory()).map((entry) => entry.name).sort();
const blogPages = ["/blog/", "/pl/blog/", ...posts.flatMap((slug) => [`/blog/${slug}/`, `/pl/blog/${slug}/`])];
const pages = [
  ...overviews,
  "/comparison/", "/pl/comparison/", "/zh/comparison/",
  "/faq/", "/pl/faq/", "/zh/faq/",
  "/content-cost/", "/pl/content-cost/", "/zh/content-cost/",
  "/reporting/", "/pl/reporting/", "/zh/reporting/",
  "/course-generation/", "/pl/course-generation/", "/zh/course-generation/",
  "/course-generation/microdosing-study/", "/pl/course-generation/microdosing-study/", "/zh/course-generation/microdosing-study/",
  "/course-generation/stablecoin-report/", "/pl/course-generation/stablecoin-report/", "/zh/course-generation/stablecoin-report/",
  "/course-generation/docs-repo/", "/pl/course-generation/docs-repo/", "/zh/course-generation/docs-repo/",
  "/course-generation/notion-page/", "/pl/course-generation/notion-page/", "/zh/course-generation/notion-page/",
  "/learning-elements/", "/pl/learning-elements/", "/zh/learning-elements/",
  "/style-guide/", "/pl/style-guide/", "/zh/style-guide/",
  "/white-paper/", "/pl/white-paper/", "/zh/white-paper/",
  ...blogPages,
];
const get = (path) => new Promise((done, reject) => {
  const req = request({ host: "127.0.0.1", port, path, headers: { host, "x-forwarded-host": host, "x-forwarded-proto": "https" } }, (res) => {
    let body = "";
    res.setEncoding("utf8").on("data", (chunk) => body += chunk).on("end", () => done({ status: res.statusCode, body }));
  });
  req.setTimeout(45000, () => req.destroy(new Error(`Render timed out: ${path}`)));
  req.on("error", reject).end();
});
const server = spawn(process.execPath, [join(dist, "server/entry.mjs")], {
  env: { ...process.env, HOST: "127.0.0.1", PORT: String(port) }, stdio: "inherit",
});
try {
  for (let attempt = 0; ; attempt++) {
    try { await get("/"); break; } catch {
      if (server.exitCode !== null || attempt >= 50) throw new Error("Static render server did not start.");
      await new Promise((done) => setTimeout(done, 200));
    }
  }
  cpSync(join(dist, "client"), out, { recursive: true });
  for (const path of pages) {
    let response = await get(path);
    if (overviews.includes(path)) {
      for (let attempt = 0; attempt < 20 && response.status === 200 && !response.body.includes("/learn/"); attempt++) {
        await new Promise((done) => setTimeout(done, 1500));
        response = await get(path);
      }
      if (!response.body.includes("/learn/")) throw new Error(`No live demo course links on ${path}.`);
    }
    if (response.status !== 200) throw new Error(`${path}: HTTP ${response.status}`);
    const html = response.body.replaceAll(`http://localhost:${port}`, `https://${host}`)
      .replaceAll(`:${port}`, "")
      .replace(/http:\/\/([a-z0-9.-]+\.ulams\.app)/g, "https://$1")
      .replace(/\/_image\?href=([^&"')\s]+)[^"')\s]*/g, (_, href) => decodeURIComponent(href.replaceAll("&amp;", "&")));
    if (/\b(?:href|src|action)=["'][^"']*(?:localhost|127\.0\.0\.1)/.test(html)) throw new Error(`Local link in exported page: ${path}`);
    mkdirSync(join(out, path), { recursive: true });
    writeFileSync(join(out, path, "index.html"), html);
  }
  const notFound = await get("/404");
  if (notFound.status !== 404) throw new Error("Expected the dedicated 404 page.");
  writeFileSync(join(out, "404.html"), notFound.body);
  for (const path of ["/robots.txt", "/sitemap.xml"]) {
    const response = await get(path);
    if (response.status !== 200) throw new Error(`${path}: HTTP ${response.status}`);
    writeFileSync(join(out, path.slice(1)), response.body);
  }
  writeFileSync(join(out, "_headers"), "/*\n  X-Content-Type-Options: nosniff\n  Referrer-Policy: strict-origin-when-cross-origin\n/_astro/*\n  Cache-Control: public, max-age=31536000, immutable\n/landing/*\n  Cache-Control: public, max-age=86400\n/blog/*\n  Cache-Control: public, max-age=86400\n");
  console.log(`Exported ${pages.length} pages (${blogPages.length} of them the blog), robots.txt, sitemap.xml and assets to ${out}`);
} finally {
  server.kill();
}
