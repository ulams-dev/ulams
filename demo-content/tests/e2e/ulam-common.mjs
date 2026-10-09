import AxeBuilder from "@axe-core/playwright";
import { execFileSync } from "node:child_process";
import { mkdtempSync, readFileSync, readdirSync, statSync } from "node:fs";
import { tmpdir } from "node:os";
import { join, relative } from "node:path";
import { pack } from "../../scripts/pack.mjs";
import { validateManifest } from "../../scripts/lib/manifest.mjs";
import { messages, startHarness, unzipPackage, waitForMessage } from "../harness/index.mjs";

export const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];
export const inFrame = (page) => page.frames().find((f) => f.url().includes("/interactive/k/v1/"));
export const axeProblems = async (page) => (await new AxeBuilder({ page }).withTags(TAGS).analyze()).violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(" ")).join(" | ")}`);

const walk = (dir, out = []) => {
  for (const name of readdirSync(dir)) {
    const path = join(dir, name);
    if (statSync(path).isDirectory()) walk(path, out);
    else out.push(path);
  }
  return out;
};

/**
 * The checks every Ulam interactive must pass, registered into the calling spec file.
 * @param {{ test: any, expect: any }} t  Playwright's test and expect
 * @param {{ name: string, dir: string, steps: string[], completeAt?: string, scoreOnly?: boolean, maxCodeKb?: number, maxTotalMb?: number }} o
 *   completeAt: the step whose visit sends `complete` (none when the package completes by score or by range)
 */
export function ulamSuite({ test, expect }, { name, dir, steps, completeAt, maxCodeKb = 150, maxTotalMb = 3 }) {
  let unzipped;
  let zip;
  let harness;
  let manifest;
  test.describe.configure({ mode: "serial" });

  test.beforeAll(async () => {
    zip = pack(dir, join(mkdtempSync(join(tmpdir(), `ulams-${name}-zip-`)), `${name}.zip`));
    unzipped = await unzipPackage(zip);
    manifest = JSON.parse(readFileSync(join(unzipped, "ulams-interactive.json"), "utf8"));
    harness = await startHarness(unzipped);
  });
  test.afterAll(async () => harness?.close());

  const open = async (page, params = {}) => {
    await page.goto(harness.hostUrl({ chrome: "full", ...params })); // inline lessons ask for the full chrome
    await waitForMessage(page, "ready");
    return inFrame(page);
  };

  test(`${name}: the zip is a valid package, MIT, with posters, small and with no third-party files`, () => {
    const files = new Set(execFileSync("unzip", ["-Z1", zip]).toString().split("\n").filter(Boolean));
    expect(validateManifest(manifest, files)).toEqual([]);
    expect(manifest.steps.map((s) => s.id)).toEqual(steps);
    expect(manifest.licence).toBe("MIT");
    expect(manifest.capabilities.reducedMotion).toBe(true);
    expect(manifest.requires).toEqual([]);
    expect(manifest.network).toEqual([]);
    for (const s of manifest.steps) expect(statSync(join(unzipped, s.poster)).size, s.poster).toBeGreaterThan(2_000);
    for (const f of ["LICENSE.txt", "LICENSE-content.txt", "vendor/interactive-bridge.js", "vendor/ulam-shell.js", "vendor/ulam-shell.css"]) expect(files.has(f), f).toBe(true);
    expect([...files].filter((f) => /^(scripts|tests)\//.test(f) || /\.(mp3|mp4|mov)$/.test(f))).toEqual([]);
    const codeKb = walk(unzipped).filter((f) => !/\/(posters|data)\//.test(f) && !/\.(webp|png|jpg|woff2?)$/.test(f)).reduce((n, f) => n + statSync(f).size, 0) / 1024;
    expect(codeKb, "code size in KB").toBeLessThan(maxCodeKb);
    expect(statSync(zip).size).toBeLessThan(maxTotalMb * 1024 * 1024);
    expect(relative(unzipped, join(unzipped, "index.html"))).toBe("index.html");
  });

  test(`${name}: loads in the opaque sandbox, says ready with every step and asks for nothing outside the origin`, async ({ page }) => {
    const hosts = new Set();
    page.on("request", (r) => {
      const u = new URL(r.url());
      if (!["127.0.0.1", "localhost"].includes(u.hostname) && u.protocol.startsWith("http")) hosts.add(u.host);
    });
    const frame = await open(page);
    const ready = (await messages(page, "ready"))[0];
    expect(ready.steps).toEqual(steps);
    expect(ready.capabilities).toMatchObject({ steps: true, reducedMotion: true });
    expect(await frame.evaluate(() => window.origin)).toBe("null");
    await expect(frame.evaluate(() => document.cookie)).rejects.toThrow(/sandboxed/);
    await expect(frame.evaluate(() => window.localStorage.getItem("x"))).rejects.toThrow(/sandboxed|denied/);
    expect((await waitForMessage(page, "stepChanged")).step).toBe(steps[0]);
    await expect(frame.locator("#ix-card")).toBeVisible(); // inline: the lesson page asks for the full chrome
    expect([...hosts]).toEqual([]);
    expect(harness.strayContentRequests()).toEqual([]);
    expect(harness.appRequests.filter((p) => p !== "/host" && p !== "/host.js")).toEqual([]);
    expect(await messages(page, "error")).toEqual([]);
  });

  test(`${name}: the step buttons work with the keyboard alone and the whole course range is reachable`, async ({ page }) => {
    const frame = await open(page);
    await waitForMessage(page, "stepChanged", { step: steps[0] });
    await expect(frame.locator(".ix-prev")).toBeDisabled();
    await frame.locator(".ix-next").focus();
    for (const id of steps.slice(1)) {
      await page.keyboard.press("Enter");
      await waitForMessage(page, "stepChanged", { step: id });
      if (id !== steps.at(-1)) await frame.locator(".ix-next").focus();
    }
    await expect(frame.locator(".ix-next")).toBeDisabled();
    await expect(frame.locator(".ix-step-no")).toHaveText(`Step ${steps.length} of ${steps.length}`);
    // Back with the keyboard
    await frame.locator(".ix-prev").focus();
    await page.keyboard.press("Enter");
    await waitForMessage(page, "stepChanged", { step: steps.at(-2) });
    const completes = await messages(page, "complete");
    expect(completes.length).toBe(completeAt ? 1 : 0);
    expect(await messages(page, "error")).toEqual([]);
  });

  test(`${name}: goToStep from the lesson page opens every step and is reported`, async ({ page }) => {
    await open(page);
    for (const id of steps) {
      await page.evaluate((s) => window.__host.goToStep(s), id);
      await waitForMessage(page, "stepChanged", { step: id });
    }
    expect(new Set((await messages(page, "stepChanged")).map((m) => m.step)).size).toBe(steps.length);
    const progress = (await messages(page, "progress")).map((m) => m.value);
    expect(progress.at(-1)).toBeCloseTo(1, 5);
  });

  test(`${name}: a step range limits navigation`, async ({ page }) => {
    test.skip(steps.length < 3, "needs three steps");
    const frame = await open(page, { startStep: steps[1], from: steps[1], to: steps[2] });
    await waitForMessage(page, "stepChanged", { step: steps[1] });
    await expect(frame.locator(".ix-prev")).toBeDisabled();
    await frame.locator(".ix-next").focus();
    await page.keyboard.press("Enter");
    await waitForMessage(page, "stepChanged", { step: steps[2] });
    await expect(frame.locator(".ix-next")).toBeDisabled();
    await page.evaluate((s) => window.__host.goToStep(s), steps[0]);
    await page.waitForTimeout(400);
    expect((await messages(page, "stepChanged")).map((m) => m.step)).toEqual([steps[1], steps[2]]);
  });

  test(`${name}: none chrome hides the card, reduced motion is honoured, poster mode renders a still`, async ({ page }) => {
    const frame = await open(page, { chrome: "none", reducedMotion: "1", startStep: steps.at(-1) });
    await waitForMessage(page, "stepChanged", { step: steps.at(-1) });
    await expect(frame.locator("#ix-card")).toBeHidden();
    expect(await frame.locator("html").getAttribute("class")).toContain("reduced");
    expect(await frame.locator("html").getAttribute("class")).toContain("ix-none");
    await page.goto(`${harness.contentOrigin}/interactive/k/v1/index.html?ulams-poster#${steps[1]}`);
    await page.waitForSelector("html[data-ix-ready]");
    await expect(page.locator("#ix-card")).toBeHidden();
    expect(await page.locator("html").getAttribute("class")).toContain("ix-poster");
  });

  test(`${name}: axe finds no WCAG 2.2 AA violation in any step (full chrome, none, and a phone)`, async ({ page, browser }) => {
    for (const id of steps) {
      await open(page, { startStep: id });
      await waitForMessage(page, "stepChanged", { step: id });
      await page.waitForTimeout(500);
      expect(await axeProblems(page), `${id} full`).toEqual([]);
    }
    await open(page, { startStep: steps.at(-1), chrome: "none" });
    await waitForMessage(page, "stepChanged", { step: steps.at(-1) });
    expect(await axeProblems(page), "none").toEqual([]);
    const phone = await browser.newContext({ viewport: { width: 390, height: 780 }, hasTouch: true });
    const p = await phone.newPage();
    await p.goto(harness.hostUrl({ chrome: "full", startStep: steps[1] }));
    await waitForMessage(p, "stepChanged", { step: steps[1] });
    await p.waitForTimeout(500);
    expect(await axeProblems(p), "phone").toEqual([]);
    await phone.close();
  });

  return { open, get harness() { return harness; }, get manifest() { return manifest; } };
}
