import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";

const port = process.env.WEB_BASE_PORT ?? "4321";
const url = `http://app.localhost:${port}/`;

test("the hero leads straight into the how-it-works story, with no tabbed lifecycle", async ({ page }) => {
  await page.emulateMedia({ reducedMotion: "reduce" });
  await page.goto(url);
  await expect(page.locator(".u-hero .policy-story, ulams-policy-story")).toHaveCount(0);
  await expect(page.locator(".u-hero .u-actions .u-btn").first()).toHaveAttribute("href", "#demos");
  const story = page.locator("main > section#how-it-works");
  await expect(story).toHaveCount(1);
  expect(await story.evaluate((el) => el.previousElementSibling?.classList.contains("u-hero"))).toBe(true);
  await expect(story.locator("ol.hs__steps > li h3")).toHaveCount(6);
  await expect(story).toContainText("One measured run");
  await expect(story).toContainText("7 min 44 s");
  await expect(page.locator("#agents .u-lead")).toContainText("Codex, OpenCode, Gemini CLI");
});

test("the former principles section tells the sourced Ulam story", async ({ page }) => {
  await page.emulateMedia({ reducedMotion: "reduce" });
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

test("standards include the sourced headless-format paper", async ({ page }) => {
  await page.emulateMedia({ reducedMotion: "reduce" });
  await page.goto(url);
  const note = page.locator("#standards .u-chips__research");
  await note.scrollIntoViewIfNeeded();
  await expect(note.locator("h3")).toHaveText("ULAM Headless Course Format");
  await expect(note).toContainText("Mateusz Wojczal");
  await expect(note.locator("time")).toHaveAttribute("datetime", "2022-01-31");
  await expect(note.locator("a")).toHaveCount(2);
  await expect(note.locator("a").first()).toHaveAttribute("href", "https://escolalms.github.io/headless-format/paper.pdf");
  await expect(note.locator("a").last()).toHaveAttribute("href", "https://github.com/EscolaLMS/headless-format");
  const results = await new AxeBuilder({ page }).include(".u-chips__research").withTags(["wcag2a", "wcag2aa", "wcag21aa", "wcag22aa"]).analyze();
  expect(results.violations).toEqual([]);
});

test("self-hosting lists three deployment guides and cloud examples", async ({ page }) => {
  await page.emulateMedia({ reducedMotion: "reduce" });
  await page.goto(url);
  const section = page.locator("#self-host");
  await section.scrollIntoViewIfNeeded();
  await expect(section.locator(".u-cta__bullets li")).toHaveCount(3);
  await expect(section.locator(".u-lead")).toContainText("AWS, Google Cloud or Azure");
  await expect(section.locator(".u-cta__bullets a")).toHaveCount(3);
  await expect(section.locator(".u-cta__bullets li").last()).toContainText("native manifests");
  await expect(section.locator(".u-cta__note")).toHaveText("Helm chart coming soon.");
  const results = await new AxeBuilder({ page }).include("#self-host").withTags(["wcag2a", "wcag2aa", "wcag21aa", "wcag22aa"]).analyze();
  expect(results.violations).toEqual([]);
});
