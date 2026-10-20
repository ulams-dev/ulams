import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";
const port = process.env.WEB_BASE_PORT ?? "4321";
const url = `http://app.localhost:${port}/pl/comparison/`;

test("the comparison restores the full catalogue under six stakeholders", async ({ page }) => {
  await page.emulateMedia({ reducedMotion: "reduce" });
  await page.goto(url);
  const catalogue = page.locator("#features");
  await expect(catalogue.locator(".u-fx__audience-nav a")).toHaveCount(6);
  await expect(catalogue.locator(".u-fx__audience")).toHaveCount(6);
  await expect(catalogue.locator(".u-fx__group")).toHaveCount(14);
  await expect(catalogue.locator(".u-fx__item")).toHaveCount(101);
  const ids = await catalogue.locator("[id]").evaluateAll((els) => els.map((el) => el.id));
  expect(new Set(ids).size).toBe(ids.length);
  await expect(catalogue.locator("#features-h5p .u-fx__tags li")).toHaveCount(54);
  await expect(catalogue.locator(".u-fx__audience-heading h3")).toContainText(["Uczestnicy", "Autorzy", "HR", "Sprzedaż", "Administratorzy", "IT"]);
  await expect(page.locator("#compare table").first()).toHaveCount(1);
});

test("categories work with the keyboard and expanded content fits the phone", async ({ page }) => {
  await page.emulateMedia({ reducedMotion: "reduce" });
  await page.goto(url);
  const content = page.locator("#features-content");
  await content.locator("summary").focus();
  await page.keyboard.press("Space");
  await expect(content).toHaveAttribute("open", "");
  await expect(content.locator(".u-fx__body")).toBeVisible();
  for (const group of await page.locator(".u-fx__group").all()) await group.evaluate((el) => (el as HTMLDetailsElement).open = true);
  const size = await page.evaluate(() => ({ viewport: document.documentElement.clientWidth, page: document.documentElement.scrollWidth }));
  expect(size.page).toBeLessThanOrEqual(size.viewport);
  if (size.viewport <= 760) {
    const columns = await content.locator(".u-fx__items").evaluate((el) => getComputedStyle(el).gridTemplateColumns.split(" ").length);
    expect(columns).toBe(1);
  }
  const results = await new AxeBuilder({ page }).include("#features").withTags(["wcag2a", "wcag2aa", "wcag21aa", "wcag22aa"]).analyze();
  expect(results.violations.map((v) => v.id)).toEqual([]);
});

test("the complete feature descriptions are rendered without JavaScript", async ({ browser }) => {
  const context = await browser.newContext({ javaScriptEnabled: false });
  const page = await context.newPage();
  await page.goto(url);
  await expect(page.locator(".u-fx__item")).toHaveCount(101);
  await page.locator("#features-content summary").click();
  await expect(page.locator("#features-content .u-fx__body")).toBeVisible();
  const structured = await page.locator('#features script[type="application/ld+json"]').textContent();
  expect(JSON.parse(structured!).itemListElement).toHaveLength(14);
  await context.close();
});

test("the header highlights the current page in every language", async ({ page }) => {
  for (const prefix of ["", "/pl", "/zh"]) {
    for (const route of ["/", "/comparison/"]) {
      const path = `${prefix}${route}`;
      await page.goto(`http://app.localhost:${port}${path}`);
      const desktop = page.locator('.u-nav a[aria-current="page"]');
      const mobile = page.locator('.u-menu__panel a[aria-current="page"]');
      await expect(desktop).toHaveCount(1);
      await expect(mobile).toHaveCount(1);
      await expect(desktop).toHaveAttribute("href", path);
      await expect(mobile).toHaveAttribute("href", path);
    }
  }
});

test("the product names stay visible while the comparison scrolls", async ({ page }) => {
  await page.emulateMedia({ reducedMotion: "reduce" });
  await page.goto(url);
  const scroll = page.locator("#compare .u-compare__scroll").first();
  await scroll.scrollIntoViewIfNeeded();
  const header = scroll.locator("thead th").nth(1);
  const before = await header.boundingBox();
  await scroll.evaluate((el) => {
    if (el.scrollHeight > el.clientHeight) el.scrollTop = 600;
    else window.scrollBy(0, 600);
  });
  await page.waitForTimeout(100);
  const after = await header.boundingBox();
  const top = await scroll.boundingBox();
  expect(after!.y).toBeGreaterThanOrEqual(Math.min(before!.y, top!.y) - 1);
  expect(after!.y).toBeLessThan(page.viewportSize()!.height);
});
