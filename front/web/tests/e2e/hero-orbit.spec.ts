import AxeBuilder from "@axe-core/playwright";
import { expect, test } from "@playwright/test";

const port = process.env.WEB_BASE_PORT ?? "4321";
const url = `http://app.localhost:${port}/`;

test("the hero carries the journey as tabs, followed by the advantages and the builder walkthrough", async ({ page }) => {
  await page.emulateMedia({ reducedMotion: "reduce" });
  await page.goto(url);
  await expect(page.locator(".u-hero .policy-story, ulams-policy-story, #how-it-works")).toHaveCount(0);
  await expect(page.locator(".u-hero .u-actions .u-btn").first()).toHaveAttribute("href", "#demos");
  await expect(page.locator('.u-hero #hero-journey [role="tab"]')).toHaveCount(8);
  const advantages = page.locator("main > section#advantages");
  await expect(advantages.locator("li")).toHaveCount(6);
  await expect(advantages).toContainText("7 min 44 s");
  await expect(page.locator("main > section#builder-walkthrough")).toHaveCount(1);
  await expect(page.locator("#agents .u-lead")).toContainText("Claude Code");
});

test("self-hosting lists three deployment guides", async ({ page }) => {
  await page.emulateMedia({ reducedMotion: "reduce" });
  await page.goto(url);
  const section = page.locator("#self-host");
  await section.scrollIntoViewIfNeeded();
  await expect(section.locator(".u-cta__bullets li")).toHaveCount(3);
  await expect(section.locator(".u-lead")).toContainText("Kubernetes");
  await expect(section.locator(".u-cta__bullets a")).toHaveCount(3);
  await expect(section.locator(".u-cta__bullets li").last()).toContainText("Native manifests");
  const results = await new AxeBuilder({ page }).include("#self-host").withTags(["wcag2a", "wcag2aa", "wcag21aa", "wcag22aa"]).analyze();
  expect(results.violations).toEqual([]);
});
