// Renders one poster per step of a package that supports ?ulams-poster#<step> (WebP, 1280x720), dev only, output committed:
//   node scripts/posters.mjs <package-dir>
// The page sets data-ix-ready on <html> once the step is drawn. Rendered in reduced-motion so the final state shows.
import { mkdirSync, readFileSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import { chromium } from "@playwright/test";
import sharp from "sharp";
import { serveFolder } from "./lib/static-server.mjs";

const dir = resolve(process.argv[2] ?? ".");
const manifest = JSON.parse(readFileSync(join(dir, "ulams-interactive.json"), "utf8"));
mkdirSync(join(dir, "posters"), { recursive: true });
const server = await serveFolder(dir, { csp: false });
const browser = await chromium.launch({ args: ["--use-angle=swiftshader", "--enable-unsafe-swiftshader"] });
const context = await browser.newContext({ viewport: { width: 1280, height: 720 }, deviceScaleFactor: 1, reducedMotion: "reduce" });
for (const step of manifest.steps) {
  const page = await context.newPage(); // a new document per step: only the hash differs between URLs
  await page.goto(`${server.url}/index.html?ulams-poster#${step.id}`);
  await page.waitForSelector("html[data-ix-ready]", { timeout: 30_000 });
  await page.waitForTimeout(400);
  const png = await page.screenshot({ type: "png" });
  const out = join(dir, step.poster ?? `posters/${step.id}.webp`);
  await sharp(png).webp({ quality: 62, effort: 5 }).toFile(out);
  console.log(`poster ${step.id} -> ${out.slice(dir.length + 1)}`);
  await page.close();
}
// the still of the landing hero: the first showcase step, picture only (?ulams-showcase hides the card and the controls)
if (manifest.showcase?.poster) {
  const page = await context.newPage();
  await page.goto(`${server.url}/index.html?ulams-poster&ulams-showcase#${manifest.showcase.steps[0]}`);
  await page.waitForSelector("html[data-ix-ready]", { timeout: 30_000 });
  await page.waitForTimeout(400);
  const out = join(dir, manifest.showcase.poster);
  mkdirSync(dirname(out), { recursive: true });
  await sharp(await page.screenshot({ type: "png" })).webp({ quality: 62, effort: 5 }).toFile(out);
  console.log(`showcase poster -> ${manifest.showcase.poster}`);
  await page.close();
}
await browser.close();
await server.close();
