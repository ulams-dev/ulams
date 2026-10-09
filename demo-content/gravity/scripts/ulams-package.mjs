// Builds the ulams content package of the gravity simulator:
//   yarn workspace @ulams/demo-gravity package [--no-posters]
//
// 1. vite build (relative base) into dist/
// 2. renders one poster per step (WebP, 1280x720) with Chromium, from ?ulams-poster#<step>
// 3. writes ulams-interactive.json, LICENSE.txt and NOTICE.txt into dist/
// 4. zips dist/ into release/gravity-ulams-<version>.zip
//
// The package holds no music and no Moon photograph (no clear licence); see ../NOTICE.
import { execFileSync } from "node:child_process";
import { copyFileSync, cpSync, existsSync, mkdirSync, rmSync, writeFileSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { chromium } from "@playwright/test";
import sharp from "sharp";
import { build } from "vite";
import { serveFolder } from "../../scripts/lib/static-server.mjs";
import { buildManifest, version } from "./manifest.mjs";

const root = join(dirname(fileURLToPath(import.meta.url)), "..");
const dist = join(root, "dist");
const noPosters = process.argv.includes("--no-posters");

await build({ root, logLevel: "warn" });

const manifest = await buildManifest({ posters: !noPosters });

if (!noPosters) {
  mkdirSync(join(dist, "posters"), { recursive: true });
  const server = await serveFolder(dist, { csp: false });
  const browser = await chromium.launch({ args: ["--use-angle=swiftshader", "--enable-unsafe-swiftshader", "--ignore-gpu-blocklist", "--enable-webgl"] });
  const context = await browser.newContext({ viewport: { width: 1280, height: 720 }, deviceScaleFactor: 1, reducedMotion: "no-preference" });
  const queue = manifest.steps.map((s) => s.id);
  const worker = async () => {
    const page = await context.newPage();
    for (let id = queue.shift(); id; id = queue.shift()) {
      await page.goto(`${server.url}/index.html?ulams-poster#${id}`);
      await page.waitForFunction(() => document.body.classList.contains("ulams-booted"), null, { timeout: 60_000 });
      await page.waitForTimeout(3500); // let the step's animation develop
      const png = await page.screenshot({ type: "png" });
      await sharp(png).webp({ quality: 70 }).toFile(join(dist, "posters", `${id}.webp`));
      process.stdout.write(`poster ${id}\n`);
    }
    await page.close();
  };
  await Promise.all([worker(), worker(), worker()]);
  await browser.close();
  await server.close();
}

writeFileSync(join(dist, "ulams-interactive.json"), `${JSON.stringify(manifest, null, 2)}\n`);
copyFileSync(join(root, "LICENSE"), join(dist, "LICENSE.txt"));
copyFileSync(join(root, "NOTICE"), join(dist, "NOTICE.txt"));
// the licence texts of the bundled fonts and of Three.js travel with them
mkdirSync(join(dist, "licenses"), { recursive: true });
const nm = join(root, "..", "..", "node_modules");
for (const [name, from] of [["inter-OFL.txt", "@fontsource-variable/inter/LICENSE"], ["roboto-mono-OFL.txt", "@fontsource/roboto-mono/LICENSE"], ["three-MIT.txt", "three/LICENSE"]]) {
  copyFileSync(join(nm, from), join(dist, "licenses", name));
}

const release = join(root, "release");
mkdirSync(release, { recursive: true });
const zip = join(release, `gravity-ulams-${version}.zip`);
rmSync(zip, { force: true });
execFileSync("zip", ["-qrXD", zip, "."], { cwd: dist });
console.log(`wrote ${zip}`);
