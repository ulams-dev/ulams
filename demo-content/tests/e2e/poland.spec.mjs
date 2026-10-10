import AxeBuilder from "@axe-core/playwright";
import { execFileSync } from "node:child_process";
import { cpSync, mkdtempSync, readFileSync, rmSync, statSync } from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join } from "node:path";
import { fileURLToPath } from "node:url";
import { expect, test } from "@playwright/test";
import { pack } from "../../scripts/pack.mjs";
import { validateManifest } from "../../scripts/lib/manifest.mjs";
import { messages, startHarness, unzipPackage, waitForMessage } from "../harness/index.mjs";

/**
 * The poland package as it would be uploaded (zipped by scripts/pack.mjs), played in an opaque-origin sandbox
 * by the real bridge host on a throwaway server with the API's CSP. Checks: it loads and says ready with every
 * step, steps advance and each is reported, EN and PL, a step range, reduced motion stops the map loop and the
 * animations, no request leaves the origin, keyboard use of the panel toggle and the step buttons, a data failure
 * is reported, and axe (WCAG 2.2 AA) on the figures panel, on a phone and on the stand-alone page.
 */
const poland = join(dirname(fileURLToPath(import.meta.url)), "..", "..", "poland");
const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];

let zip;
let dir;
let harness;
let manifest;

test.beforeAll(async () => {
  zip = pack(poland, join(mkdtempSync(join(tmpdir(), "ulams-poland-zip-")), "poland.zip"));
  dir = await unzipPackage(zip);
  manifest = JSON.parse(readFileSync(join(dir, "ulams-interactive.json"), "utf8"));
  harness = await startHarness(dir);
});
test.afterAll(async () => harness?.close());

const inFrame = (page) => page.frames().find((f) => f.url().includes("/interactive/k/v1/"));
const external = (page) => {
  const hosts = new Set();
  page.on("request", (r) => {
    const u = new URL(r.url());
    if (!["127.0.0.1", "localhost"].includes(u.hostname) && u.protocol.startsWith("http")) hosts.add(u.host);
  });
  return hosts;
};
const axeProblems = async (page) => (await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(" ")).join(" | ")}`);

test("the zip is a valid package: manifest, posters, licences, fonts, nothing third-party, under 3 MB", () => {
  const files = new Set(execFileSync("unzip", ["-Z1", zip]).toString().split("\n").filter(Boolean));
  expect(validateManifest(manifest, files)).toEqual([]);
  expect(manifest.steps).toHaveLength(40);
  expect(manifest.licence).toBe("MIT");
  for (const s of manifest.steps) expect(statSync(join(dir, s.poster)).size).toBeGreaterThan(3_000);
  for (const f of ["LICENSE.txt", "LICENSE-content.txt", "NOTICE.txt", "fonts/OFL.txt", "data/world.topo.json", "data/steps.json", "data/sources.json", "vendor/interactive-bridge.js", "vendor/atlas.js"]) expect(files.has(f), f).toBe(true);
  expect([...files].filter((f) => /^(scripts|tests)\//.test(f) || /\.(mp4|mp3)$/.test(f) || /Tom Wojcik/i.test(f))).toEqual([]);
  expect(statSync(zip).size).toBeLessThan(3 * 1024 * 1024);
  expect(statSync(join(dir, "data", "world.topo.json")).size).toBeLessThan(400 * 1024);
});

test("loads in the opaque sandbox, says ready with every step, and nothing leaves the origin", async ({ page }) => {
  const hosts = external(page);
  await page.goto(harness.hostUrl({ startStep: "gas" }));
  const ready = await waitForMessage(page, "ready");
  expect(ready.steps).toEqual(manifest.steps.map((s) => s.id));
  expect(ready.capabilities).toMatchObject({ steps: true, reducedMotion: true, background: true });
  const frame = inFrame(page);
  expect(await frame.evaluate(() => window.origin)).toBe("null");
  await expect(frame.evaluate(() => document.cookie)).rejects.toThrow(/sandboxed/);
  await expect(frame.evaluate(() => window.localStorage.getItem("x"))).rejects.toThrow(/sandboxed|denied/);
  expect((await waitForMessage(page, "stepChanged")).step).toBe("gas");
  expect(await frame.locator("#map path").count()).toBeGreaterThan(150); // the countries
  await expect(frame.locator("#story .fig").first()).toBeVisible();
  expect([...hosts]).toEqual([]); // no Google Fonts, no CDN
  expect(harness.strayContentRequests()).toEqual([]);
  expect(harness.appRequests.filter((p) => p !== "/host" && p !== "/host.js")).toEqual([]);
  expect(harness.contentRequests.filter((p) => p.includes("/data/")).sort()).toEqual(["/interactive/k/v1/data/sources.json", "/interactive/k/v1/data/steps.json", "/interactive/k/v1/data/world.topo.json"]);
});

test("every step opens by goToStep and is reported (the whole course range)", async ({ page }) => {
  test.setTimeout(240_000);
  await page.goto(harness.hostUrl({}));
  await waitForMessage(page, "ready");
  for (const s of manifest.steps) {
    await page.evaluate((id) => window.__host.goToStep(id), s.id);
    await waitForMessage(page, "stepChanged", { step: s.id }, 40_000);
  }
  expect(new Set((await messages(page, "stepChanged")).map((m) => m.step)).size).toBe(40);
  const progress = (await messages(page, "progress")).map((m) => m.value);
  expect(progress.at(-1)).toBeCloseTo(1, 5);
  expect(await messages(page, "complete")).toEqual([]); // a topic completes by its range, never by the package
  expect(await messages(page, "error")).toEqual([]);
});

test("a step range limits navigation, the range end is reachable and reported", async ({ page }) => {
  await page.goto(harness.hostUrl({ startStep: "gas", from: "gas", to: "coal" }));
  await waitForMessage(page, "ready");
  await waitForMessage(page, "stepChanged", { step: "gas" });
  await page.evaluate(() => window.__host.goToStep("coal"));
  await waitForMessage(page, "stepChanged", { step: "coal" });
  await page.evaluate(() => window.__host.goToStep("food"));
  await page.waitForTimeout(600);
  expect((await messages(page, "stepChanged")).map((m) => m.step)).toEqual(["gas", "coal"]);
});

test("English and Polish: the locale from init and setLocale change the figures and the title", async ({ page }) => {
  await page.goto(harness.hostUrl({ startStep: "gas", locale: "pl", reducedMotion: "1" })); // final values at once, however slow the runner
  await waitForMessage(page, "ready");
  await waitForMessage(page, "stepChanged", { step: "gas" });
  const frame = inFrame(page);
  await expect(frame.locator("#story .figt").first()).toHaveText(/Gaz: magazyny i trasy dostaw/);
  expect(await frame.locator("html").getAttribute("lang")).toBe("pl");
  await expect(frame.locator("#story .kpi .v").first()).toContainText("99");
  await page.evaluate(() => window.__host.setLocale("en"));
  await expect(frame.locator("#story .figt").first()).toHaveText(/Gas: storage and supply routes/);
  expect(await frame.title()).toBe("Poland, measured");
  await page.evaluate(() => window.__host.setLocale("pl"));
  await expect.poll(() => frame.title()).toBe("Polska w liczbach");
  expect(await frame.locator("#mlist li").count()).toBeGreaterThan(0); // the places on the map, as text
});

test("reduced motion: the map loop stops and the figures show their final values", async ({ page }) => {
  await page.goto(harness.hostUrl({ startStep: "gas", reducedMotion: "1" }));
  await waitForMessage(page, "ready");
  await waitForMessage(page, "stepChanged", { step: "gas" });
  const frame = inFrame(page);
  expect(await frame.locator("html").getAttribute("class")).toContain("reduced");
  await page.waitForTimeout(800);
  const a = await frame.locator("#fx").screenshot();
  await page.waitForTimeout(1200);
  const b = await frame.locator("#fx").screenshot();
  expect(a.equals(b)).toBe(true); // nothing moves
  await expect(frame.locator("#story .kpi .v").first()).toHaveText("99%");
  const animated = await frame.evaluate(() => [...document.querySelectorAll("#story svg")].every((s) => !s.pauseAnimations || s.animationsPaused()));
  expect(animated).toBe(true);
  // without it, the same view keeps moving
  await page.goto(harness.hostUrl({ startStep: "gas" }));
  await waitForMessage(page, "stepChanged", { step: "gas" });
  await page.waitForTimeout(1500);
  const c = await inFrame(page).locator("#fx").screenshot();
  await page.waitForTimeout(700);
  const d = await inFrame(page).locator("#fx").screenshot();
  expect(c.equals(d)).toBe(false);
});

test("pause and resume stop and restart the map loop", async ({ page }) => {
  await page.goto(harness.hostUrl({ startStep: "gas" }));
  await waitForMessage(page, "stepChanged", { step: "gas" });
  await page.waitForTimeout(1500);
  await page.evaluate(() => window.__host.pause());
  await page.waitForTimeout(300);
  const a = await inFrame(page).locator("#fx").screenshot();
  await page.waitForTimeout(900);
  expect((await inFrame(page).locator("#fx").screenshot()).equals(a)).toBe(true);
  await page.evaluate(() => window.__host.resume());
  await page.waitForTimeout(900);
  expect((await inFrame(page).locator("#fx").screenshot()).equals(a)).toBe(false);
});

test("keyboard: minimal chrome has Back and Next buttons that work with Enter and stop at the range ends", async ({ page }) => {
  await page.goto(harness.hostUrl({ chrome: "minimal", startStep: "gas", from: "gas", to: "food" }));
  await waitForMessage(page, "ready");
  await waitForMessage(page, "stepChanged", { step: "gas" });
  const frame = inFrame(page);
  const prev = frame.locator(".stepnav button").first();
  const next = frame.locator(".stepnav button").nth(1);
  await expect(prev).toBeDisabled();
  await next.focus();
  await page.keyboard.press("Enter");
  await waitForMessage(page, "stepChanged", { step: "solar" });
  for (const id of ["coal", "food"]) {
    await frame.locator(".stepnav button").nth(1).focus();
    await page.keyboard.press("Enter");
    await waitForMessage(page, "stepChanged", { step: id });
  }
  await expect(frame.locator(".stepnav button").nth(1)).toBeDisabled();
});

test("phone: the figures panel is behind a native button that Enter and Escape operate", async ({ browser }) => {
  const context = await browser.newContext({ viewport: { width: 390, height: 780 }, hasTouch: true });
  const page = await context.newPage();
  await page.goto(harness.hostUrl({ startStep: "gas" }));
  await waitForMessage(page, "ready");
  await waitForMessage(page, "stepChanged", { step: "gas" });
  const frame = inFrame(page);
  const toggle = frame.locator("#paneltoggle");
  await expect(toggle).toBeVisible();
  await expect(frame.locator("#story")).toBeHidden();
  await toggle.focus();
  await page.keyboard.press("Enter");
  await expect(frame.locator("#story")).toBeVisible();
  await expect(toggle).toHaveAttribute("aria-expanded", "true");
  expect(await axeProblems(page)).toEqual([]);
  await page.keyboard.press("Escape");
  await expect(frame.locator("#story")).toBeHidden();
  await expect(toggle).toHaveAttribute("aria-expanded", "false");
  await context.close();
});

test("reports data-unavailable when the data cannot be loaded", async ({ browser }) => {
  const broken = mkdtempSync(join(tmpdir(), "ulams-poland-broken-"));
  cpSync(dir, broken, { recursive: true });
  rmSync(join(broken, "data", "steps.json"));
  const h = await startHarness(broken);
  const page = await browser.newPage();
  await page.goto(h.hostUrl({ startStep: "gas" }));
  const err = await waitForMessage(page, "error");
  expect(err.code).toBe("data-unavailable");
  await page.close();
  await h.close();
});

test("axe: no WCAG 2.2 AA violation on the figures panel (several steps, both languages) or on the stand-alone page", async ({ page }) => {
  test.setTimeout(300_000);
  for (const [locale, step] of [["en", "hero"], ["en", "gas"], ["pl", "ledger"], ["en", "poverty"], ["pl", "yards"]]) {
    await page.goto(harness.hostUrl({ startStep: step, locale }));
    await waitForMessage(page, "stepChanged", { step });
    await page.waitForTimeout(1800);
    expect(await axeProblems(page), `${locale} ${step}`).toEqual([]);
  }
  await page.goto(`${harness.contentOrigin}/interactive/k/v1/index.html?lang=en`);
  await page.waitForSelector("#story .step");
  await page.waitForTimeout(1500);
  expect(await axeProblems(page), "stand-alone").toEqual([]);
});

test("showcase mode is the map alone: no figures panel, no text, nothing to focus, and the loop's steps open", async ({ page }) => {
  expect(manifest.showcase.steps).toEqual(["solar", "roads", "parcels", "gas"]);
  expect(statSync(join(dir, manifest.showcase.poster)).size).toBeGreaterThan(3_000);
  await page.goto(harness.hostUrl({ chrome: "none", showcase: "1", startStep: "solar" }));
  await waitForMessage(page, "ready");
  await waitForMessage(page, "stepChanged", { step: "solar" });
  const frame = inFrame(page);
  expect(await frame.locator("html").getAttribute("class")).toContain("ix-showcase");
  for (const hidden of ["#story", "#paneltoggle", "#tip", "#top", "#bottom"]) await expect(frame.locator(hidden), hidden).toBeHidden();
  expect(await frame.locator("body").getAttribute("inert")).not.toBeNull();
  for (const id of manifest.showcase.steps.slice(1)) {
    await page.evaluate((s) => window.__host.goToStep(s), id);
    await waitForMessage(page, "stepChanged", { step: id });
  }
});
