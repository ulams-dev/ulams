import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/** Platform landing hierarchy, product proof, responsive layout and WCAG 2.2 AA. */
const port = process.env.WEB_BASE_PORT ?? "4321";
const url = `http://app.localhost:${port}/`;

async function open(page: Page) {
  await page.goto(url);
}

async function revealAll(page: Page) {
  await page.evaluate(async () => {
    for (let y = 0; y < document.body.scrollHeight; y += 600) {
      window.scrollTo({ top: y, behavior: "instant" });
      await new Promise((resolve) => setTimeout(resolve, 40));
    }
    window.scrollTo({ top: 0, behavior: "instant" });
  });
  await page.waitForTimeout(1000);
}

test("the hero explains the product with one calm, cited example", async ({ page }) => {
  await open(page);
  await expect(page.locator("h1")).toContainText("Every claim cited");
  await expect(page.locator(".u-hero .u-actions .u-btn")).toHaveCount(2);
  await expect(page.locator(".u-hero .u-actions .u-btn").first()).toHaveAttribute("href", "#demos");
  await expect(page.locator(".u-hero .u-actions .u-btn").nth(1)).toHaveAttribute("href", "#self-host");
  await expect(page.locator(".u-hero__product")).toHaveAttribute("aria-label", /course draft/);
  await expect(page.locator(".u-prod__badge")).toHaveText("Reviewable draft");
  await expect(page.locator(".u-prod__source")).toContainText("incident-runbook.pdf");
  await expect(page.locator(".u-prod__cites")).toContainText("incident-runbook.pdf §2");
  await expect(page.locator(".u-prod__note")).toContainText("reviews every change before publishing");
  await expect(page.locator("ulams-orbit")).toHaveCount(0);
});

test("product proof and demos appear before detailed product information", async ({ page }) => {
  await open(page);
  const hero = await page.locator(".u-hero").evaluate((e) => e.getBoundingClientRect().top);
  const demos = await page.locator("#demos").evaluate((e) => e.getBoundingClientRect().top);
  const features = await page.locator("#product").evaluate((e) => e.getBoundingClientRect().top);
  const comparison = await page.locator("#compare").evaluate((e) => e.getBoundingClientRect().top);
  expect(hero).toBeLessThan(demos);
  expect(demos).toBeLessThan(features);
  expect(features).toBeLessThan(comparison);
  for (const href of ["#product", "#compare", "https://ulams-dev.github.io/ulams/"]) {
    await expect(page.locator(".u-nav a[href='" + href + "']")).toHaveCount(1);
  }
});

test("the hero product card is static and the page fits the viewport", async ({ page }, info) => {
  await open(page);
  const motion = await page.locator(".u-prod__card").evaluate((el) => getComputedStyle(el).animationName);
  expect(motion).toBe("none");
  const dimensions = await page.evaluate(() => ({
    viewport: document.documentElement.clientWidth,
    page: document.documentElement.scrollWidth,
  }));
  expect(dimensions.page, `horizontal overflow at ${info.project.name}`).toBeLessThanOrEqual(dimensions.viewport);
  await page.locator(".u-hero").screenshot({ path: info.outputPath(`platform-hero-${info.project.name}.png`) });
});

test("the platform landing has no WCAG 2.2 AA violations", async ({ page }) => {
  await open(page);
  await revealAll(page);
  const results = await new AxeBuilder({ page })
    .withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"])
    .analyze();
  const summary = results.violations.map((v) => `${v.id}: ${v.nodes.slice(0, 3).map((n) => `${n.target.join(" ")}\n${n.failureSummary}`).join(" | ")}`);
  expect(summary, summary.join("\n")).toEqual([]);
});
