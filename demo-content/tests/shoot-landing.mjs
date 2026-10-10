// A manual check on the running local stack: screenshots of a demo tenant's landing page with the hero playing (the
// showcase interactive), on a desktop and a phone viewport, and prints whether the hero frame is the real package.
//   node tests/shoot-landing.mjs <tenant> <out-dir>      writes <out-dir>/<tenant>-landing-desktop.png and -phone.png
// FRONT_PORT=4323 shoots a second build of the reference frontend instead of the one behind Caddy.
import { mkdirSync } from "node:fs";
import { join } from "node:path";
import { chromium } from "@playwright/test";

const [tenant, out] = process.argv.slice(2);
if (!tenant || !out) {
  console.error("usage: node tests/shoot-landing.mjs <tenant> <out-dir>");
  process.exit(1);
}
mkdirSync(out, { recursive: true });
const browser = await chromium.launch({ args: ["--use-angle=swiftshader", "--enable-unsafe-swiftshader", "--ignore-gpu-blocklist"] });
for (const [name, viewport] of [["desktop", { width: 1440, height: 900 }], ["phone", { width: 390, height: 844 }]]) {
  const context = await browser.newContext({ viewport, deviceScaleFactor: 1, reducedMotion: "no-preference" });
  const page = await context.newPage();
  await page.goto(`http://${tenant}.app.localhost${process.env.FRONT_PORT ? `:${process.env.FRONT_PORT}` : ""}/`, { waitUntil: "load" });
  await page.locator("iframe").first().waitFor({ timeout: 20000 }).catch(() => {});
  await page.waitForTimeout(9000);
  const frame = await page.locator("iframe").first().getAttribute("src").catch(() => null);
  const live = await page.locator(".u-hero__stage[data-live]").count();
  console.log(`${name}: hero frame ${frame ? frame.slice(0, 90) : "none"}; live stage ${live ? "yes" : "no"}`);
  await page.screenshot({ path: join(out, `${tenant}-landing-${name}.png`) });
  await context.close();
}
await browser.close();
