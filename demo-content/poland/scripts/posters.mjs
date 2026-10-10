// Renders one poster per step (WebP) with Chromium, dev only, output committed:
//   node scripts/posters.mjs [step-id ... | showcase]
// The lesson page shows a poster instead of the live frame when the learner asks for less motion and the
// package cannot honour it, or when the frame does not start. This package honours reduced motion, but the
// poster also serves as the step's preview. Rendered in reduced-motion so the figures show their final values.
import { mkdirSync, readFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { chromium } from "@playwright/test";
import sharp from "sharp";
import { serveFolder } from "../../scripts/lib/static-server.mjs";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const steps = JSON.parse(readFileSync(join(root, "data", "steps.json"), "utf8")).steps.map((s) => s.id);
const args = process.argv.slice(2); // step ids, and "showcase" for the hero's still; none: everything
const wantShowcase = !args.length || args.includes("showcase");
const only = args.filter((a) => a !== "showcase");
const todo = args.length ? only : steps;
mkdirSync(join(root, "posters"), { recursive: true });

const server = await serveFolder(root, { csp: false });
const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1280, height: 720 }, deviceScaleFactor: 1, reducedMotion: "reduce" });
const queue = [...todo];
const worker = async () => {
  for (let id = queue.shift(); id; id = queue.shift()) {
    const page = await context.newPage(); // a new document per step: only the hash differs between URLs
    await page.goto(`${server.url}/index.html?ulams-poster&lang=en#${id}`);
    await page.waitForFunction(() => document.querySelector("#story .step"), null, { timeout: 30_000 });
    await page.waitForTimeout(700);
    const png = await page.screenshot({ type: "png" });
    await sharp(png).webp({ quality: 58, effort: 5 }).toFile(join(root, "posters", `${id}.webp`));
    process.stdout.write(`poster ${id}\n`);
    await page.close();
  }
};
await Promise.all([worker(), worker(), worker()]);
// the still of the landing hero: the first showcase step, the map alone (?ulams-showcase drops the figures panel)
if (wantShowcase) {
  const { SHOWCASE_STEPS } = await import("./build-manifest.mjs");
  const page = await context.newPage();
  await page.goto(`${server.url}/index.html?ulams-poster&ulams-showcase&lang=en#${SHOWCASE_STEPS[0]}`);
  await page.waitForFunction(() => document.querySelector("#story .step"), null, { timeout: 30_000 });
  await page.waitForTimeout(700);
  await sharp(await page.screenshot({ type: "png" })).webp({ quality: 58, effort: 5 }).toFile(join(root, "posters", "showcase.webp"));
  process.stdout.write("poster showcase\n");
  await page.close();
}
await browser.close();
await server.close();
