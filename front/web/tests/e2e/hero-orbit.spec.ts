import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";
import { gzipSync } from "node:zlib";

/**
 * The capability orbit in the platform landing hero: structure, keyboard, pause, reduced motion,
 * layout shift, axe (WCAG 2.2 AA) on desktop and a 360 px phone, the size of its script, and the
 * hero screenshots under tests/screens/ for review. Needs the API for the demo cards of the page.
 */
const port = process.env.WEB_BASE_PORT ?? "4321";
const url = `http://app.localhost:${port}/`;
const LABELS = [
  "Living Course",
  "AI course builder",
  "Preview & discuss",
  "Learner insights & AI tutor",
  "CLI · MCP · REST API",
  "Claude & Claude Code",
  "Site per customer",
  "H5P · SCORM · LTI 1.3",
  "Commerce",
  "Self-hosted, open source",
];

async function open(page: Page) {
  await page.goto(url);
  await expect(page.locator("ulams-orbit")).toHaveCount(1);
  await page.waitForTimeout(1300); // entrance animation of the hero
}

test("the orbit lists every capability as a real link to a section that exists", async ({
  page,
}) => {
  await open(page);
  const links = page.locator("ulams-orbit .u-orb__card");
  await expect(links).toHaveCount(10);
  for (const label of LABELS)
    await expect(
      page.locator("ulams-orbit .u-orb__card", { hasText: label }),
    ).toHaveCount(1);
  const hrefs = await links.evaluateAll((els) =>
    els.map((e) => e.getAttribute("href")!),
  );
  for (const href of hrefs)
    await expect(page.locator(href), href).toHaveCount(1);
  // each card is described by its caption, which is plain text in the DOM
  await expect(page.locator("ulams-orbit [data-cap-text]")).toHaveCount(10);
  await expect(page.locator("#u-orb-cap-0")).toHaveText(
    "Your course updates itself when sources change",
  );
  // the final landing mode shows no roadmap markers
  await expect(page.locator("ulams-orbit .u-orb__soon")).toHaveCount(0);
});

test("keyboard: tab reaches the cards, focus is visible, shows the caption and pauses", async ({
  page,
}) => {
  await open(page);
  const card = page.locator("ulams-orbit .u-orb__card", {
    hasText: "Commerce",
  });
  await card.focus();
  await page.keyboard.press("Shift+Tab");
  await page.keyboard.press("Tab");
  await expect(card).toBeFocused();
  await expect(page.locator("#u-orb-cap-8")).toBeVisible();
  await expect(page.locator("ulams-orbit")).toHaveAttribute("data-hold", "");
  const outline = await card
    .locator(".u-orb__face")
    .evaluate((e) => getComputedStyle(e).outlineStyle);
  expect(outline).not.toBe("none");
  await page.waitForTimeout(4800);
  await expect(page.locator("#u-orb-cap-8")).toBeVisible(); // held while focused
  // Enter follows the link to its section
  await page.keyboard.press("Enter");
  await expect(page).toHaveURL(/#compare$/);
});

test("the highlight moves on by itself and the Pause button stops it", async ({
  page,
}) => {
  await open(page);
  const reduced = await page.evaluate(
    () => matchMedia("(prefers-reduced-motion: reduce)").matches,
  );
  test.skip(reduced, "no cycling under reduced motion");
  await expect(page.locator("ulams-orbit .is-on")).toHaveCount(1);
  const first = await page
    .locator("ulams-orbit [data-cap].is-on")
    .getAttribute("data-cap");
  await expect
    .poll(
      async () =>
        page.locator("ulams-orbit [data-cap].is-on").getAttribute("data-cap"),
      { timeout: 9000 },
    )
    .not.toBe(first);
  await page.locator("ulams-orbit [data-pause]").click();
  await expect(page.locator("ulams-orbit")).toHaveAttribute("data-paused", "");
  const frozen = await page
    .locator("ulams-orbit [data-cap].is-on")
    .getAttribute("data-cap");
  await page.waitForTimeout(5000);
  expect(
    await page.locator("ulams-orbit [data-cap].is-on").getAttribute("data-cap"),
  ).toBe(frozen);
});

test("hovering a card pauses the rotation", async ({ page, isMobile }) => {
  test.skip(
    !!isMobile || (page.viewportSize()?.width ?? 0) < 600,
    "tile grid does not rotate",
  );
  await open(page);
  const arm = page.locator("ulams-orbit .u-orb__arm").first();
  await expect(arm).toHaveCSS("animation-play-state", "running");
  await page
    .locator("ulams-orbit .u-orb__card", { hasText: "Commerce" })
    .locator(".u-orb__face")
    .hover({ force: true });
  await expect(arm).toHaveCSS("animation-play-state", "paused");
});

test("no layout shift: the stage keeps its size while it animates", async ({
  page,
}) => {
  await open(page);
  const size = () =>
    page
      .locator("ulams-orbit .u-orb__stage")
      .evaluate((e) => [
        e.getBoundingClientRect().width,
        e.getBoundingClientRect().height,
      ]);
  const before = await size();
  await page.waitForTimeout(4800); // a highlight change and a caption swap
  expect(await size()).toEqual(before);
  const cls = await page.evaluate(
    () =>
      new Promise<number>((resolve) => {
        let total = 0;
        new PerformanceObserver((l) => {
          for (const e of l.getEntries() as unknown as Array<{
            value: number;
            hadRecentInput: boolean;
          }>)
            if (!e.hadRecentInput) total += e.value;
        }).observe({ type: "layout-shift", buffered: true });
        setTimeout(() => resolve(total), 300);
      }),
  );
  expect(cls).toBeLessThan(0.05);
});

test("axe: the hero has no WCAG 2.2 AA violations", async ({ page }) => {
  await open(page);
  // axe on the whole page; the orbit is highlighted at different moments, so scan twice
  for (let i = 0; i < 2; i++) {
    const results = await new AxeBuilder({ page })
      .withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"])
      .include(".u-hero")
      .analyze();
    const summary = results.violations.map(
      (v) =>
        `${v.id}: ${v.nodes
          .slice(0, 3)
          .map((n) => n.target.join(" "))
          .join(" | ")}`,
    );
    expect(summary, summary.join("\n")).toEqual([]);
    await page.waitForTimeout(4400);
  }
});

test("the script is a few hundred bytes: under 2 KB gzip", async ({ page }) => {
  await page.goto(url);
  const html = await page.content();
  const scripts = [...html.matchAll(/<script[^>]*>([\s\S]*?)<\/script>/g)]
    .map((m) => m[1] ?? "")
    .filter((s) => s.includes('define("ulams-orbit"'));
  let size = 0;
  if (scripts.length) size = gzipSync(scripts[0] ?? "").length;
  else {
    // not inlined: a module script of its own
    const srcs = await page
      .locator("script[src]")
      .evaluateAll((els) => els.map((e) => (e as HTMLScriptElement).src));
    for (const src of srcs) {
      const body = await (await page.request.get(src)).text();
      if (body.includes('define("ulams-orbit"')) size = gzipSync(body).length;
    }
  }
  expect(size, "orbit script found").toBeGreaterThan(100);
  expect(size).toBeLessThan(2048);
  test
    .info()
    .annotations.push({
      type: "orbit-js-gzip-bytes",
      description: String(size),
    });
});

test("screenshots of the hero: normal and reduced motion", async ({
  page,
  browser,
}, info) => {
  await open(page);
  await page.addStyleTag({
    content: "ulams-orbit *{animation-play-state:paused !important}",
  });
  await page
    .locator(".u-hero")
    .screenshot({ path: `tests/screens/hero-${info.project.name}.png` });
  if (info.project.name === "desktop") {
    const ctx = await browser.newContext({
      viewport: { width: 1440, height: 900 },
      reducedMotion: "reduce",
    });
    const rm = await ctx.newPage();
    await open(rm);
    // static arrangement: all ten labels visible, nothing moving
    for (const label of LABELS)
      await expect(
        rm
          .locator("ulams-orbit .u-orb__card", { hasText: label })
          .locator(".u-orb__face"),
      ).toBeVisible();
    expect(
      await rm
        .locator("ulams-orbit .u-orb__arm")
        .first()
        .evaluate((e) => getComputedStyle(e).animationName),
    ).toBe("none");
    await expect(rm.locator("ulams-orbit [data-pause]")).toBeHidden();
    const boxes = await rm
      .locator("ulams-orbit .u-orb__face")
      .evaluateAll((els) => els.map((e) => e.getBoundingClientRect().toJSON()));
    for (let i = 0; i < boxes.length; i++)
      for (let j = 0; j < i; j++) {
        const a = boxes[i];
        const b = boxes[j];
        const overlap =
          a.left < b.right &&
          b.left < a.right &&
          a.top < b.bottom &&
          b.top < a.bottom;
        expect(
          overlap,
          `cards ${i} and ${j} overlap in the static arrangement`,
        ).toBe(false);
      }
    await rm
      .locator(".u-hero")
      .screenshot({ path: "tests/screens/hero-reduced-motion.png" });
    await ctx.close();
  }
});
