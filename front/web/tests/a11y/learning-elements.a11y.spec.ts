import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * The learning-elements section of the home page and the /learning-elements/ page, in English, Polish and Chinese,
 * on desktop and at 360 px (the `phone` project): axe (WCAG 2.2 AA), no horizontal scroll, the live practice
 * question and self-test cards from the keyboard, their localised controls, and the state without JavaScript.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const base = `http://app.localhost:${web}`;
const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];
const PREFIXES = [
  ["", "en", "Show answer"],
  ["/pl", "pl", "Pokaż odpowiedź"],
  ["/zh", "zh-Hans", "显示答案"],
] as const;

async function check(page: Page, ...include: string[]) {
  expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(0);
  let builder = new AxeBuilder({ page }).withTags(TAGS);
  for (const selector of include) builder = builder.include(selector);
  const result = await builder.analyze();
  expect(result.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(" ")).join(", ")}`)).toEqual([]);
}

test.describe("with reduced motion", () => {
  test.use({ contextOptions: { reducedMotion: "reduce" } });

  for (const [prefix, lang, show] of PREFIXES) {
    test(`${prefix || "/"}: the home section passes axe, fits the screen and its cards speak the page language`, async ({ page }) => {
      await page.goto(`${base}${prefix}/`);
      await expect(page.locator("html")).toHaveAttribute("lang", lang);
      const section = page.locator("main#main > section#learning-elements");
      await expect(section.locator(".le-live .le-card")).toHaveCount(3);
      await expect(section.locator(".u-flip__toggle").first()).toHaveText(show);
      await check(page, "#learning-elements");
    });

    test(`${prefix}/learning-elements/: passes axe and fits the screen`, async ({ page }) => {
      await page.goto(`${base}${prefix}/learning-elements/`);
      await expect(page.locator("html")).toHaveAttribute("lang", lang);
      await expect(page.locator("h1")).toHaveCount(1);
      await expect(page.locator(".le-card")).toHaveCount(21);
      await check(page);
    });
  }
});

test("the practice question and the self-test cards work from the keyboard", async ({ page }) => {
  await page.goto(`${base}/learning-elements/`);
  const pq = page.locator("#le-practice-question");
  await pq.locator("input[type=radio]").nth(1).focus();
  await page.keyboard.press("Space");
  await pq.locator("button[data-check]").focus();
  await page.keyboard.press("Enter");
  await expect(pq.locator("[data-result]")).toContainText("Correct");
  const cards = page.locator("#le-flip-cards");
  const toggle = cards.locator(".u-flip__toggle").first();
  await expect(toggle).toHaveAttribute("aria-expanded", "false");
  await toggle.focus();
  await page.keyboard.press("Enter");
  await expect(toggle).toHaveAttribute("aria-expanded", "true");
  await expect(toggle).toHaveText("Hide answer");
});

test.describe("without JavaScript", () => {
  test.use({ javaScriptEnabled: false });
  test("both sides of the cards and every element stay readable", async ({ page }) => {
    await page.goto(`${base}/learning-elements/`);
    await expect(page.locator("#le-flip-cards .u-flip__back").first()).toBeVisible();
    await expect(page.locator("#le-practice-question [data-nojs]")).toBeVisible();
    await expect(page.locator(".le-card__purpose:visible")).toHaveCount(21);
  });
});
