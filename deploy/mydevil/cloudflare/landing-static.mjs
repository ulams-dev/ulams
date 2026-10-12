#!/usr/bin/env node
// Export the six product pages and their SEO routes from the production Astro build.
// Build with ULAMS_IMAGE_SERVICE=noop, then pass a new output directory and the public host.
import { spawn } from "node:child_process";
import { request } from "node:http";
import { cpSync, existsSync, mkdirSync, writeFileSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

if (!process.argv[2]) throw new Error("usage: landing-static.mjs <new-output-directory> [public-host]");
const out = resolve(process.argv[2]);
if (existsSync(out)) throw new Error("Output directory must be new; existing files will not be replaced.");
const host = process.argv[3] ?? "ulams.app";
const root = resolve(dirname(fileURLToPath(import.meta.url)), "../../..");
const dist = join(root, "front/web/dist");
const port = Number(process.env.ULAMS_STATIC_RENDER_PORT ?? 4399);
const pages = ["/", "/pl/", "/zh/", "/comparison/", "/pl/comparison/", "/zh/comparison/"];
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
    if (!path.includes("comparison")) {
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
  writeFileSync(join(out, "_headers"), "/*\n  X-Content-Type-Options: nosniff\n  Referrer-Policy: strict-origin-when-cross-origin\n/_astro/*\n  Cache-Control: public, max-age=31536000, immutable\n/landing/*\n  Cache-Control: public, max-age=86400\n");
  console.log(`Exported ${pages.length} product pages, robots.txt, sitemap.xml and assets to ${out}`);
} finally {
  server.kill();
}
