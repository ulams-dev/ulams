import AxeBuilder from "@axe-core/playwright";
import { execFileSync } from "node:child_process";
import { existsSync, readFileSync, statSync } from "node:fs";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { chromium, expect, test } from "@playwright/test";
import { messages, startHarness, unzipPackage, waitForMessage } from "../harness/index.mjs";
import { validateManifest } from "../../scripts/lib/manifest.mjs";

/**
 * The gravity package, exactly as it would be uploaded: it is built with scripts/ulams-package.mjs,
 * unzipped, served through a throwaway content origin (with the API's CSP) and played in an opaque-origin
 * sandbox by a host that speaks the real bridge. Checks: it loads in the sandbox and says ready with all
 * its steps, steps advance (goToStep -> stepChanged, a step range), no request leaves the origin, the
 * keyboard-usable controls exist, WebGL failure is reported, and axe finds no WCAG 2.2 AA violation.
 */
const gravity = join(dirname(fileURLToPath(import.meta.url)), "..", "..", "gravity");
const zip = join(gravity, "release", "gravity-ulams-1.0.0.zip");

let dir;
let harness;
let manifest;

test.beforeAll(async () => {
  test.setTimeout(300_000);
  if (!existsSync(zip)) execFileSync("node", ["scripts/ulams-package.mjs"], { cwd: gravity, stdio: "inherit" });
  dir = await unzipPackage(zip);
  manifest = JSON.parse(readFileSync(join(dir, "ulams-interactive.json"), "utf8"));
  harness = await startHarness(dir);
});
test.afterAll(async () => harness?.close());

const external = (page) => {
  const hosts = new Set();
  page.on("request", (r) => {
    const u = new URL(r.url());
    if (!["127.0.0.1", "localhost"].includes(u.hostname) && u.protocol.startsWith("http")) hosts.add(u.host);
  });
  return hosts;
};

test("the zip is a valid package: manifest, posters for every step, licences, no music or Moon photo", () => {
  const files = new Set(
    execFileSync("unzip", ["-Z1", zip]).toString().split("\n").filter(Boolean),
  );
  expect(validateManifest(manifest, files)).toEqual([]);
  expect(manifest.steps).toHaveLength(44);
  expect(manifest.licence).toBe("MIT");
  for (const s of manifest.steps) expect(statSync(join(dir, s.poster)).size).toBeGreaterThan(5_000);
  for (const f of ["LICENSE.txt", "NOTICE.txt", "licenses/inter-OFL.txt", "licenses/roboto-mono-OFL.txt", "earth_daymap.jpg"]) expect(files.has(f), f).toBe(true);
  expect([...files].filter((f) => /\.(mp3|wav|ogg)$/i.test(f) || (!f.startsWith("posters/") && /moon/i.test(f)))).toEqual([]);
  expect(statSync(zip).size).toBeLessThan(5 * 1024 * 1024);
});

test("loads in the opaque sandbox, says ready with every step and asks for nothing outside the origin", async ({ page }) => {
  const hosts = external(page);
  await page.goto(harness.hostUrl({ startStep: "what-is-gravity" }));
  const ready = await waitForMessage(page, "ready");
  expect(ready.steps).toEqual(manifest.steps.map((s) => s.id));
  expect(ready.capabilities).toMatchObject({ steps: true, reducedMotion: false, background: true });
  expect(ready.protocol).toBe(1);
  const frame = page.frames().find((f) => f.url().includes("/interactive/k/v1/"));
  expect(await frame.evaluate(() => window.origin)).toBe("null"); // opaque: no storage, no cookies, no same-origin requests
  await expect(frame.evaluate(() => document.cookie)).rejects.toThrow(/sandboxed/); // no cookies
  await expect(frame.evaluate(() => window.localStorage.getItem("x"))).rejects.toThrow(/sandboxed|denied/); // no storage
  const first = await waitForMessage(page, "stepChanged");
  expect(first.step).toBe("what-is-gravity");
  await expect(frame.locator("canvas#scene")).toBeVisible();
  expect([...hosts]).toEqual([]); // no Google Fonts, no CDN
  expect(harness.strayContentRequests()).toEqual([]);
  expect(harness.appRequests.filter((p) => p !== "/host" && p !== "/host.js")).toEqual([]); // the frame never called the app
});

test("steps advance with goToStep and every step change is reported", async ({ page }) => {
  await page.goto(harness.hostUrl({ startStep: "inertia" }));
  await waitForMessage(page, "ready");
  expect((await waitForMessage(page, "stepChanged", { step: "inertia" })).step).toBe("inertia");
  const ids = ["why-no-fall", "too-slow", "lagrange", "stopped-galaxy", "black-hole"];
  for (const id of ids) {
    await page.evaluate((s) => window.__host.goToStep(s), id);
    await waitForMessage(page, "stepChanged", { step: id });
  }
  const seen = (await messages(page, "stepChanged")).map((m) => m.step);
  expect(seen).toEqual(["inertia", ...ids]);
  const progress = (await messages(page, "progress")).map((m) => m.value);
  expect(progress.at(-1)).toBeCloseTo(manifest.steps.findIndex((s) => s.id === "black-hole") / 43, 3);
  // the package never claims the lesson is complete: the topic's range decides that
  expect(await messages(page, "complete")).toEqual([]);
});

test("a step range limits navigation: the range end is reachable, a step outside it is ignored", async ({ page }) => {
  await page.goto(harness.hostUrl({ startStep: "too-slow", from: "too-slow", to: "too-fast" }));
  await waitForMessage(page, "ready");
  await waitForMessage(page, "stepChanged", { step: "too-slow" });
  await page.evaluate(() => window.__host.goToStep("too-fast"));
  await waitForMessage(page, "stepChanged", { step: "too-fast" });
  await page.evaluate(() => window.__host.goToStep("lagrange"));
  await page.waitForTimeout(800);
  expect((await messages(page, "stepChanged")).map((m) => m.step)).toEqual(["too-slow", "too-fast"]);
});

test("every step of the tour can be opened and reports itself (the whole course range)", async ({ page }) => {
  test.setTimeout(240_000);
  await page.goto(harness.hostUrl({}));
  await waitForMessage(page, "ready");
  for (const s of manifest.steps) {
    await page.evaluate((id) => window.__host.goToStep(id), s.id);
    await waitForMessage(page, "stepChanged", { step: s.id }, 20_000);
  }
  const seen = new Set((await messages(page, "stepChanged")).map((m) => m.step));
  expect(seen.size).toBe(44);
});

test("keyboard: the controls a step needs are native buttons in the page; the lesson stepper does the rest", async ({ page }) => {
  await page.goto(harness.hostUrl({ startStep: "lagrange" }));
  await waitForMessage(page, "ready");
  await waitForMessage(page, "stepChanged", { step: "lagrange" });
  const frame = page.frames().find((f) => f.url().includes("/interactive/k/v1/"));
  const l1 = frame.locator('.tour-lag button[data-i="1"]');
  await expect(l1).toBeVisible();
  await l1.focus();
  await page.keyboard.press("Enter");
  await expect(l1).toHaveClass(/held/);
  // none chrome: no tour text or navigation inside the frame, the lesson page shows them
  await expect(frame.locator(".tour-body")).toBeHidden();
  await expect(frame.locator(".tour-foot")).toBeHidden();
  await expect(frame.locator("#info-btn")).toBeHidden();
});

test("minimal chrome keeps Back and Next, and stays inside the range", async ({ page }) => {
  await page.goto(harness.hostUrl({ chrome: "minimal", startStep: "too-slow", from: "too-slow", to: "too-fast" }));
  await waitForMessage(page, "ready");
  await waitForMessage(page, "stepChanged", { step: "too-slow" });
  const frame = page.frames().find((f) => f.url().includes("/interactive/k/v1/"));
  await expect(frame.locator(".tour-prev")).toBeDisabled();
  await frame.locator(".tour-next").focus();
  await page.keyboard.press("Enter");
  await waitForMessage(page, "stepChanged", { step: "too-fast" });
  await expect(frame.locator(".tour-next")).toBeDisabled();
});

test("reduced motion and the Polish locale are honoured and the scene still works", async ({ page }) => {
  await page.goto(harness.hostUrl({ reducedMotion: "1", locale: "pl", chrome: "minimal", startStep: "polaris" }));
  await waitForMessage(page, "ready");
  await waitForMessage(page, "stepChanged", { step: "polaris" });
  const frame = page.frames().find((f) => f.url().includes("/interactive/k/v1/"));
  await expect(frame.locator(".tour-prev")).toHaveText(/Wstecz/);
  await expect(frame.locator("body")).toHaveClass(/ulams-chrome-minimal/);
});

test("reports webgl-unavailable when the browser has no WebGL", async () => {
  const browser = await chromium.launch({ args: ["--disable-3d-apis", "--disable-gpu", "--disable-software-rasterizer"] });
  const page = await browser.newPage();
  await page.goto(harness.hostUrl({ startStep: "inertia", timeout: "6000" }));
  const err = await waitForMessage(page, "error", {}, 20_000);
  expect(err.code).toBe("webgl-unavailable");
  await browser.close();
});

test("axe: no WCAG 2.2 AA violation in the lesson host with the package, or in the package on its own", async ({ page }) => {
  const tags = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];
  await page.goto(harness.hostUrl({ startStep: "lagrange" }));
  await waitForMessage(page, "stepChanged", { step: "lagrange" });
  await page.waitForTimeout(1500);
  const embedded = await new AxeBuilder({ page }).withTags(tags).analyze();
  expect(embedded.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(" ")).join(" | ")}`)).toEqual([]);
  // standalone (no lesson page): the full tour panel
  await page.goto(`${harness.contentOrigin}/interactive/k/v1/index.html#polaris`);
  await page.waitForFunction(() => document.body.classList.contains("ulams-booted"));
  await page.waitForTimeout(1500);
  const alone = await new AxeBuilder({ page }).withTags(tags).analyze();
  expect(alone.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(" ")).join(" | ")}`)).toEqual([]);
});
