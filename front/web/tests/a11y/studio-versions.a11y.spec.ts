import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * The studio Versions page (/studio/courses/:id/versions, WCAG 2.2 AA, axe-core) against the mock tenant API, at desktop
 * and 360 px phone width (the Playwright projects) with reduced motion on: the releases table with its pager, a course
 * without releases, a course that was not made with the builder, another author's course and a failure with retry, in
 * English, Polish and Simplified Chinese. The studio signs in as the demo admin of the mock tenant.
 *
 * Mock courses (tests/mock-api/server.mjs): 1 twenty-eight releases (two pages), 2 no release, 3 not made with the
 * builder, 4 another author's, 5 fails once, 6 three releases.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const host = (slug: string) => `http://${slug}.app.localhost:${web}`;
const page_ = (course: number, lang = "en") => `/studio/courses/${course}/versions?lang=${lang}`;
const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];

test.use({ contextOptions: { reducedMotion: "reduce" } });

async function scan(page: Page) {
  const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();
  return results.violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.slice(0, 3).map((n) => `${n.target.join(" ")} - ${n.any[0]?.message ?? n.failureSummary ?? ""}`).join(" | ")}`);
}

const noSidewaysScroll = (page: Page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

/** Desktop and phone run in parallel against one mock API: each project has its own tenant for the test that changes mock state. */
const slugOf = (project: string) => (project === "desktop" ? "coffee" : "nightsky");

async function open(page: Page, course: number, lang = "en", slug = "coffee") {
  await page.goto(`${host(slug)}${page_(course, lang)}`, { waitUntil: "load" });
}

test("releases table: every fact is text, passes axe and reflows", async ({ page }) => {
  await open(page, 1);
  await expect(page.locator("h1")).toHaveText("Course versions");
  await expect(page.locator("table caption")).toHaveText("Releases of this course, newest first");
  await expect(page.locator("tbody tr")).toHaveCount(25);
  await expect(page.locator("tbody tr").first().locator("th")).toHaveText("#28");
  await expect(page.locator("tbody tr").first()).toContainText("Demo Author");
  await expect(page.locator("tbody tr").last().locator("th")).toHaveText("#4");
  await expect(page.locator("[data-page-status]")).toHaveText("Page 1 of 2 (releases: 28)");
  expect(await scan(page), "loaded").toEqual([]);
  expect(await noSidewaysScroll(page), "page scrolls sideways").toBeLessThanOrEqual(1);
});

test("the table region and the pager are reachable by keyboard and show focus", async ({ page }) => {
  await open(page, 1);
  await expect(page.locator("tbody tr").first()).toBeVisible();
  await expect(page.locator('[data-studio-versions] [role="region"][tabindex="0"]')).toHaveAccessibleName(/Releases/);

  await page.locator("#main").focus();
  for (let i = 0; i < 40; i++) {
    await page.keyboard.press("Tab");
    const stop = await page.evaluate(() => {
      const el = document.activeElement as HTMLElement | null;
      if (!el || el === document.body) return null;
      const style = getComputedStyle(el);
      const rect = el.getBoundingClientRect();
      return { tag: el.tagName, outline: style.outlineStyle !== "none" && parseFloat(style.outlineWidth) >= 2, width: rect.width, height: rect.height, label: (el.textContent ?? "").trim().slice(0, 30) };
    });
    if (!stop) break;
    expect(stop.outline, `focus indicator on ${stop.tag} ${stop.label}`).toBe(true);
    if (stop.tag === "BUTTON") expect(Math.min(stop.width, stop.height), `target size of ${stop.label}`).toBeGreaterThanOrEqual(24);
  }
});

test("older releases open from the pager by keyboard and are announced", async ({ page }) => {
  await open(page, 1);
  await expect(page.locator("tbody tr").first()).toBeVisible();
  const older = page.getByRole("button", { name: "Older releases" });
  await older.focus();
  await page.keyboard.press("Enter");
  await expect(page.locator("[data-page-status]")).toHaveText("Page 2 of 2 (releases: 28)");
  await expect(page.locator("tbody tr")).toHaveCount(3);
  await expect(page.locator("[data-announcer]")).toHaveText("Releases updated: page 2 of 2.");
  await expect(page.getByRole("heading", { name: "Releases, newest first" })).toBeFocused();
  await expect(page.getByRole("button", { name: "Older releases" })).toBeDisabled();
  expect(await scan(page), "page 2").toEqual([]);
});

test("a course without releases says so", async ({ page }) => {
  await open(page, 2);
  await expect(page.locator('[data-state="empty"]')).toContainText("No releases yet: the course has not been applied.");
  await expect(page.locator("table")).toHaveCount(0);
  expect(await scan(page)).toEqual([]);
});

test("a course that was not made with the builder explains how to get versions", async ({ page }) => {
  await open(page, 3);
  await expect(page.locator('[data-state="not-built"]')).toContainText("Adopt the course to track its versions.");
  expect(await scan(page)).toEqual([]);
});

test("another author's course is explained", async ({ page }) => {
  await open(page, 4);
  await expect(page.locator('[data-studio-versions] [role="alert"]')).toContainText("belongs to another author");
  expect(await scan(page)).toEqual([]);
});

test("an error offers a retry that recovers", async ({ page }, info) => {
  const slug = slugOf(info.project.name);
  const reset = await fetch(`http://127.0.0.1:${process.env.MOCK_API_PORT ?? "4390"}/__mock/course-versions/reset`, { method: "POST", headers: { "x-ulams-api-host": `${slug}.localhost` } });
  expect(reset.ok).toBe(true);
  await open(page, 5, "en", slug);
  await expect(page.locator('[data-studio-versions] [role="alert"]')).toContainText("The releases could not be loaded.");
  expect(await scan(page), "error").toEqual([]);
  await page.getByRole("button", { name: "Try again" }).click();
  await expect(page.locator("tbody tr")).toHaveCount(3);
});

test("Polish and Chinese pages pass axe and say their language", async ({ page }) => {
  await open(page, 6, "pl");
  await expect(page.locator("h1")).toHaveText("Wersje kursu");
  await expect(page.locator("main")).toHaveAttribute("lang", "pl");
  await expect(page.locator("tbody tr").first()).toContainText("Demo Author");
  expect(await scan(page), "pl").toEqual([]);
  await open(page, 6, "zh");
  await expect(page.locator("h1")).toHaveText("课程版本");
  await expect(page.locator("main")).toHaveAttribute("lang", "zh-Hans");
  await expect(page.locator("tbody tr").first()).toBeVisible();
  expect(await scan(page), "zh").toEqual([]);
});

test("the studio navigation links to Versions on a course page", async ({ page }) => {
  await open(page, 6);
  await expect(page.getByRole("link", { name: "Versions" })).toHaveAttribute("aria-current", "page");
});
