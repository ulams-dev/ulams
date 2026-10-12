import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";

const port = process.env.WEB_BASE_PORT ?? "4321";
const url = `http://app.localhost:${port}/`;

test("one policy story in the hero includes a reviewed source change and renewal", async ({ page }) => {
  await page.goto(url);
  await expect(page.locator(".u-hero .policy-story")).toHaveCount(1);
  await expect(page.locator(".u-lc")).toHaveCount(0);
  await expect(page.locator(".u-hero .u-actions .u-btn").first()).toHaveAttribute("href", "#demos");
  const story = page.locator("ulams-policy-story");
  await story.scrollIntoViewIfNeeded();
  await story.locator('[role="tab"]').nth(5).click();
  const change = story.locator('[role="tabpanel"]:visible');
  await expect(change).toContainText("One year later");
  await expect(change).toContainText("30 days");
  await expect(change).toContainText("14 days");
  await expect(change).toContainText("Owner reviews before publishing");
  await story.locator('[role="tab"]').nth(6).click();
  await expect(story.locator(".u-status")).toHaveCount(0);
  await expect(story.locator('[role="tabpanel"]:visible')).toContainText("No longer valid");
  await expect(story.locator('[role="tabpanel"]:visible')).toContainText("New certificate");
});

test("chapter navigation supports keyboard selection and pauses playback", async ({ page }) => {
  await page.goto(url);
  const story = page.locator("ulams-policy-story");
  const tabs = story.locator('[role="tab"]');
  await tabs.first().focus();
  await page.keyboard.press("ArrowRight");
  await expect(tabs.nth(1)).toBeFocused();
  await expect(tabs.nth(1)).toHaveAttribute("aria-selected", "true");
  await expect(story.locator("[data-play]")).toHaveAttribute("aria-pressed", "true");
  await page.keyboard.press("End");
  await expect(tabs.last()).toHaveAttribute("aria-selected", "true");
  await page.keyboard.press("Home");
  await expect(tabs.first()).toHaveAttribute("aria-selected", "true");
});

test("reduced motion keeps the opening scene still and scenes fit the viewport", async ({ page }) => {
  await page.emulateMedia({ reducedMotion: "reduce" });
  await page.goto(url);
  const story = page.locator("ulams-policy-story");
  await story.scrollIntoViewIfNeeded();
  await expect(story.locator("[data-play]")).toHaveAttribute("aria-pressed", "true");
  await expect(story.locator(".ps-play-icon")).toBeVisible();
  await expect(story.locator(".ps-pause-icon")).toBeHidden();
  for (let i = 0; i < 7; i++) {
    await story.locator('[role="tab"]').nth(i).click();
    const dimensions = await page.evaluate(() => ({ viewport: document.documentElement.clientWidth, page: document.documentElement.scrollWidth }));
    expect(dimensions.page).toBeLessThanOrEqual(dimensions.viewport);
  }
});

test("each narrative scene passes WCAG 2.2 AA checks", async ({ page }) => {
  await page.emulateMedia({ reducedMotion: "reduce" });
  await page.goto(url);
  const story = page.locator("ulams-policy-story");
  await story.scrollIntoViewIfNeeded();
  for (let i = 0; i < 7; i++) {
    await story.locator('[role="tab"]').nth(i).click();
    const results = await new AxeBuilder({ page }).include(".policy-story").withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"]).analyze();
    expect(results.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(" ")).join(", ")}`)).toEqual([]);
  }
});


test("CEO intake, briefing and department scenes animate in stages with shared playback controls", async ({ page }) => {
  await page.goto(url);
  const story = page.locator("ulams-policy-story");
  await story.scrollIntoViewIfNeeded();
  await expect(story.locator(".ps-upload-actor")).toContainText("CEO");
  await expect(story.locator(".ps-ingest-pipeline")).toContainText("Cited source context");
  await story.locator('[role="tab"]').nth(1).click();
  await expect(story.locator(".ps-brief-pair")).toHaveCount(4);
  await expect(story.locator(".ps-brief-chat")).toContainText("seniority");
  await expect(story.locator(".ps-brief-chat")).toContainText("experience");
  await page.waitForTimeout(800);
  expect(await story.locator(".ps-chat-message--question").first().evaluate((el) => el.getBoundingClientRect().height)).toBeGreaterThan(0);
  expect(await story.locator(".ps-chat-message--question").last().evaluate((el) => el.getBoundingClientRect().height)).toBe(0);
  await expect(story.locator(".ps-chat-typing")).toHaveCount(4);
  await story.locator('[role="tab"]').nth(2).click();
  await expect(story.locator(".ps-role-person svg")).toHaveCount(3);
  const roles = story.locator(".ps-role");
  const delays = await roles.evaluateAll((els) => els.map((el) => getComputedStyle(el).animationDelay));
  expect(new Set(delays).size).toBe(3);
  await story.locator("[data-play]").click();
  await expect(story).toHaveAttribute("data-animation-paused", "");
  expect(await roles.first().evaluate((el) => getComputedStyle(el).animationPlayState)).toBe("paused");
  await story.locator("[data-play]").click();
  expect(await roles.first().evaluate((el) => getComputedStyle(el).animationPlayState)).toBe("running");
  await expect(page.locator("#product img")).toHaveAttribute("src", /stanislaw-ulam-stitch/);
});


test("the former principles section tells the sourced Ulam story", async ({ page }) => {
  await page.goto(url);
  const heritage = page.locator("#product");
  await heritage.scrollIntoViewIfNeeded();
  await expect(heritage.locator("h2")).toContainText("Knowledge grows");
  await expect(heritage.locator("img")).toHaveAttribute("alt", "Stanisław Ulam");
  await expect(heritage.locator(".u-heritage__source")).toHaveAttribute("href", "https://mathshistory.st-andrews.ac.uk/HistTopics/Scottish_Book/");
  await expect(heritage.locator(".u-heritage__connection")).toContainText("Open source");
  const results = await new AxeBuilder({ page }).include("#product").withTags(["wcag2a", "wcag2aa", "wcag21aa", "wcag22aa"]).analyze();
  expect(results.violations).toEqual([]);
});
