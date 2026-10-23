import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * The builder walkthrough and the advantages on the home page, and the /white-paper/ and /style-guide/
 * pages, in English, Polish and Chinese, on desktop and at 360 px (the `phone` project). Checked: axe (WCAG 2.2 AA),
 * no horizontal scroll, every walkthrough step present without JS, one step at a time with the keyboard controls,
 * and the print button of the white paper.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const base = `http://app.localhost:${web}`;
const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];
const PREFIXES = [
  ["", "en"],
  ["/pl", "pl"],
  ["/zh", "zh-Hans"],
] as const;
const STEPS = 9;

async function noHorizontalScroll(page: Page) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(0);
}
async function axe(page: Page, ...include: string[]) {
  let builder = new AxeBuilder({ page }).withTags(TAGS);
  for (const selector of include) builder = builder.include(selector);
  const result = await builder.analyze();
  expect(result.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(" ")).join(", ")}`)).toEqual([]);
}

test.describe("with reduced motion", () => {
  test.use({ contextOptions: { reducedMotion: "reduce" } });

  for (const [prefix, lang] of PREFIXES) {
    test(`${prefix || "/"}: the walkthrough and the advantages pass axe and fit the screen`, async ({ page }) => {
      await page.goto(`${base}${prefix}/`);
      await expect(page.locator("html")).toHaveAttribute("lang", lang);
      const walk = page.locator("main#main > section#builder-walkthrough");
      await expect(walk).toBeVisible();
      await expect(walk.locator("[data-wt-panel]")).toHaveCount(STEPS);
      await expect(walk.locator("[data-wt-panel]:visible")).toHaveCount(1);
      await expect(walk.locator("[data-wt-controls]")).toBeVisible();
      await expect(page.locator("main#main > section#advantages li")).toHaveCount(6);
      await noHorizontalScroll(page);
      await axe(page, "#advantages", "#builder-walkthrough", "#demos", "#white-paper-cta");
    });

    test(`${prefix}/white-paper/: passes axe, fits the screen and offers the print button`, async ({ page }) => {
      await page.goto(`${base}${prefix}/white-paper/`);
      await expect(page.locator("html")).toHaveAttribute("lang", lang);
      await expect(page.locator("h1")).toHaveCount(1);
      await expect(page.locator(".wp-section")).toHaveCount(8);
      await expect(page.locator("[data-wp-print]")).toBeVisible();
      await noHorizontalScroll(page);
      await axe(page);
    });

    test(`${prefix}/style-guide/: passes axe and fits the screen`, async ({ page }) => {
      await page.goto(`${base}${prefix}/style-guide/`);
      await expect(page.locator("html")).toHaveAttribute("lang", lang);
      await expect(page.locator("h1")).toHaveCount(1);
      await expect(page.locator(".sg-preset")).toHaveCount(6);
      await noHorizontalScroll(page);
      await axe(page);
    });
  }
});

test.describe("without JavaScript", () => {
  test.use({ javaScriptEnabled: false });

  test("every walkthrough step is shown, the controls and the print button stay hidden", async ({ page }) => {
    await page.goto(`${base}/`);
    await expect(page.locator("#builder-walkthrough [data-wt-panel]:visible")).toHaveCount(STEPS);
    await expect(page.locator("#builder-walkthrough figcaption:visible")).toHaveCount(STEPS);
    await expect(page.locator("#builder-walkthrough [data-wt-controls]")).toBeHidden();
    await page.goto(`${base}/white-paper/`);
    await expect(page.locator("[data-wp-print]")).toBeHidden();
    await expect(page.locator(".wp-print__note")).toBeVisible();
  });
});

test("the walkthrough moves one step at a time from the keyboard", async ({ page }) => {
  await page.goto(`${base}/`);
  const walk = page.locator("#builder-walkthrough");
  const status = walk.locator("[data-wt-out]");
  await expect(status).toHaveText("Step 1 of 9");
  const next = walk.locator("[data-wt-next]");
  await next.focus();
  await page.keyboard.press("Enter");
  await expect(status).toHaveText("Step 2 of 9");
  await expect(walk.locator("#wt-audience")).toBeVisible();
  await expect(walk.locator("#wt-connect")).toBeHidden();
  await page.keyboard.press("ArrowRight");
  await expect(status).toHaveText("Step 3 of 9");
  await expect(walk.locator('[data-wt-link][aria-current="step"]')).toHaveAttribute("href", "#wt-tone");
  const last = walk.locator('[data-wt-link][href="#wt-lesson"]');
  await last.focus();
  await page.keyboard.press("Enter");
  await expect(status).toHaveText("Step 9 of 9");
  await expect(next).toHaveAttribute("aria-disabled", "true");
  await expect(walk.locator("#wt-lesson img")).toBeVisible();
});

test.describe("hero journey", () => {
  const BEATS = 8;
  /** Lesson text plus blocks in every lesson, then the final test: the full course of the static beat. */
  const COURSE_BLOCKS = 13;
  const open = (page: Page) => page.locator('#hero-journey [role="tab"][aria-selected="true"]');

  test.describe("with reduced motion", () => {
    test.use({ contextOptions: { reducedMotion: "reduce" } });
    for (const [prefix, lang] of PREFIXES) {
      test(`${prefix || "/"}: tabs without autoplay, one beat at a time, axe clean, fits the screen`, async ({ page }) => {
        await page.goto(`${base}${prefix}/`);
        await expect(page.locator("html")).toHaveAttribute("lang", lang);
        const journey = page.locator(".u-hero #hero-journey");
        await expect(journey).toHaveClass(/is-live/);
        await expect(journey.locator('[role="tab"]')).toHaveCount(BEATS);
        await expect(journey.locator("[data-hj-controls]")).toBeHidden();
        await expect(open(page)).toHaveId("hero-journey-tab-source");
        await page.waitForTimeout(4500);
        await expect(open(page)).toHaveId("hero-journey-tab-source");
        await journey.locator("#hero-journey-tab-draft").click();
        await expect(journey.locator('#hero-journey-caption-draft h2')).toBeVisible();
        await expect(journey.locator('#hero-journey-caption-source h2')).toBeHidden();
        await expect(journey.locator('[data-hj-scene="draft"] .hj-block:visible')).toHaveCount(COURSE_BLOCKS);
        await noHorizontalScroll(page);
        await axe(page, ".u-hero");
      });
    }
  });

  test.describe("without JavaScript", () => {
    test.use({ javaScriptEnabled: false });
    test("every beat is readable as text, with the full course as the picture", async ({ page }) => {
      await page.goto(`${base}/`);
      await expect(page.locator("#hero-journey [data-hj-caption] h2:visible")).toHaveCount(BEATS);
      await expect(page.locator('#hero-journey [data-hj-scene="draft"] .hj-block:visible')).toHaveCount(COURSE_BLOCKS);
      await expect(page.locator("#hero-journey [data-hj-tabs]")).toBeHidden();
      await expect(page.locator("#hero-journey [data-hj-controls]")).toBeHidden();
    });
  });

  test.describe("with motion", () => {
    test.use({ contextOptions: { reducedMotion: "no-preference" } });

    test("the headline renders at once; the journey plays, a tab holds it, pause freezes it, all without layout shift", async ({ page }) => {
      await page.addInitScript(() => {
        (window as unknown as { __cls: number }).__cls = 0;
        new PerformanceObserver((list) => {
          for (const entry of list.getEntries() as Array<PerformanceEntry & { value: number; hadRecentInput: boolean }>) {
            if (!entry.hadRecentInput) (window as unknown as { __cls: number }).__cls += entry.value;
          }
        }).observe({ type: "layout-shift", buffered: true });
      });
      await page.goto(`${base}/`);
      await expect(page.locator(".u-hero h1")).toBeVisible();
      const journey = page.locator("#hero-journey");
      await expect(journey).toHaveClass(/is-live/, { timeout: 5000 });
      await expect(open(page)).toHaveId("hero-journey-tab-source");
      await expect(open(page)).toHaveId("hero-journey-tab-chat", { timeout: 6000 });
      await expect(open(page)).toHaveId("hero-journey-tab-brand", { timeout: 11000 });
      await axe(page, ".u-hero");
      // opening a tab holds the loop on that beat
      await journey.locator("#hero-journey-tab-review").click();
      await expect(journey).toHaveClass(/is-held/);
      await page.waitForTimeout(4000);
      await expect(open(page)).toHaveId("hero-journey-tab-review");
      // arrows move between tabs
      await page.keyboard.press("ArrowRight");
      await expect(open(page)).toHaveId("hero-journey-tab-learners");
      await expect(open(page)).toBeFocused();
      const toggle = journey.locator("[data-hj-toggle]");
      await toggle.focus();
      await page.keyboard.press("Enter");
      await expect(toggle).toContainText("Pause the animation");
      await page.keyboard.press("Enter");
      await expect(toggle).toContainText("Play the animation");
      await expect(journey).toHaveClass(/is-paused/);
      expect(await page.evaluate(() => (window as unknown as { __cls: number }).__cls)).toBeLessThan(0.02);
      await noHorizontalScroll(page);
    });
  });
});
