import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * The how-it-works story on the platform home page, in English, Polish and Chinese, on desktop and at 360 px (the
 * `phone` project). Checked: axe (WCAG 2.2 AA), no horizontal scroll, the static final state with reduced motion and
 * without JS, steps that play as they scroll into view without layout shift, nothing that loops, and the replay
 * button from the keyboard.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const base = `http://app.localhost:${web}`;
const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];
const PREFIXES = [
  ["", "en"],
  ["/pl", "pl"],
  ["/zh", "zh-Hans"],
] as const;
const STEPS = ["how-sources", "how-draft", "how-review", "how-living", "how-learners", "how-evidence"];

/** Every animated part of the story is fully shown and not transformed. */
async function expectFinalState(page: Page) {
  const hidden = await page.evaluate(() =>
    [...document.querySelectorAll<HTMLElement>("#how-it-works [data-dg-step], #how-it-works [data-dg-pop], #how-it-works [data-dg-grow], #how-it-works .hs-step__spine, #how-it-works .hs-step__num")]
      .filter((el) => {
        const style = getComputedStyle(el);
        return Number(style.opacity) < 0.99 || (style.transform !== "none" && style.transform !== "matrix(1, 0, 0, 1, 0, 0)");
      })
      .map((el) => el.className || el.tagName),
  );
  expect(hidden).toEqual([]);
}

test.describe("with reduced motion", () => {
  test.use({ contextOptions: { reducedMotion: "reduce" } });

  for (const [prefix, lang] of PREFIXES) {
    test(`${prefix || "/"}: the story is complete and still, fits the screen and axe finds nothing`, async ({ page }) => {
      await page.goto(`${base}${prefix}/`);
      await expect(page.locator("html")).toHaveAttribute("lang", lang);
      const story = page.locator("main#main > section#how-it-works");
      await expect(story).toBeVisible();
      await expect(story.locator("h2")).toHaveCount(1);
      expect(await story.locator("ol.hs__steps > li").evaluateAll((els) => els.map((el) => el.id))).toEqual(STEPS);
      for (const id of STEPS) {
        const step = page.locator(`#${id}`);
        await expect(step.locator("h3")).toBeVisible();
        await expect(step.locator(".hs-why")).toBeVisible();
        await expect(step.locator(".hs-visual[role='img']")).toHaveAttribute("aria-label", /.{20,}/);
      }
      await expect(page.locator("html.js-dg")).toHaveCount(0);
      await expect(story.locator("[data-dg-control]:visible")).toHaveCount(0);
      await expectFinalState(page);
      expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(0);
      const axe = await new AxeBuilder({ page }).include("#how-it-works").include(".u-hero").withTags(TAGS).analyze();
      expect(axe.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(" ")).join(", ")}`)).toEqual([]);
    });
  }
});

test.describe("without JavaScript", () => {
  test.use({ javaScriptEnabled: false });

  test("the story renders complete, with the replay button hidden", async ({ page }) => {
    await page.goto(`${base}/`);
    await expectFinalState(page);
    await expect(page.locator("#how-it-works [data-dg-control]")).toBeHidden();
    await expect(page.locator("#how-draft .hs-run dd")).toHaveCount(6);
  });
});

test.describe("with motion", () => {
  test.use({ contextOptions: { reducedMotion: "no-preference" } });

  test("steps play as they scroll into view, without layout shift or loops, and end in the final state", async ({ page }) => {
    await page.addInitScript(() => {
      (window as unknown as { __cls: number }).__cls = 0;
      new PerformanceObserver((list) => {
        for (const entry of list.getEntries() as Array<PerformanceEntry & { value: number; hadRecentInput: boolean }>) {
          if (!entry.hadRecentInput) (window as unknown as { __cls: number }).__cls += entry.value;
        }
      }).observe({ type: "layout-shift", buffered: true });
    });
    await page.goto(`${base}/`);
    await expect(page.locator("html.js-dg")).toHaveCount(1);
    const last = page.locator("#how-evidence");
    await expect(last).toHaveClass(/is-armed/);
    await expect(last).not.toHaveClass(/is-played/);
    const before = await page.evaluate(() => (window as unknown as { __cls: number }).__cls);
    for (const id of STEPS) {
      await page.locator(`#${id}`).scrollIntoViewIfNeeded();
      await expect(page.locator(`#${id}`)).toHaveClass(/is-played/);
    }
    await page.locator(".hs-again").scrollIntoViewIfNeeded();
    await page.waitForTimeout(3000);
    const shift = (await page.evaluate(() => (window as unknown as { __cls: number }).__cls)) - before;
    expect(shift).toBeLessThan(0.01);
    await expectFinalState(page);
    // nothing in the story runs forever, so no pause button is needed
    const loops = await page.evaluate(() =>
      document.getAnimations().filter((a) => {
        const target = (a.effect as KeyframeEffect | null)?.target as Element | null;
        return target?.closest("#how-it-works") && a.effect?.getComputedTiming().iterations === Infinity;
      }).length,
    );
    expect(loops).toBe(0);
    const axe = await new AxeBuilder({ page }).include("#how-it-works").withTags(TAGS).analyze();
    expect(axe.violations.map((v) => v.id)).toEqual([]);
  });

  test("the replay button works from the keyboard: it goes back to the start and the steps play again", async ({ page }) => {
    await page.goto(`${base}/pl/`);
    const button = page.locator("#how-it-works [data-dg-control='replay-group']");
    await button.scrollIntoViewIfNeeded();
    await expect(button).toBeVisible();
    await expect(button).toHaveText("Odtwórz historię ponownie");
    await page.locator("#how-evidence").scrollIntoViewIfNeeded();
    await expect(page.locator("#how-evidence")).toHaveClass(/is-played/);
    await button.focus();
    await page.keyboard.press("Enter");
    // focus moves to the story heading area, which scrolls into view; the first step plays again
    await expect(page.locator("#how-it-works .hs__head")).toBeFocused();
    await expect(page.locator("#how-sources")).toHaveClass(/is-armed/);
    await expect(page.locator("#how-sources")).toHaveClass(/is-played/, { timeout: 5000 });
    for (const id of STEPS) await page.locator(`#${id}`).scrollIntoViewIfNeeded();
    await page.locator(".hs-again").scrollIntoViewIfNeeded();
    await page.waitForTimeout(3000);
    await expectFinalState(page);
  });
});
