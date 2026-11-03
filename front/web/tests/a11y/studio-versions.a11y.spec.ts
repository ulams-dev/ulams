import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * The studio Versions page (/studio/courses/:id/versions, WCAG 2.2 AA, axe-core) against the mock tenant API, at desktop
 * and 360 px phone width (the Playwright projects) with reduced motion on: the releases table with its pager, a course
 * without releases, a course that was not made with the builder, another author's course and a failure with retry, in
 * English, Polish and Simplified Chinese. The studio signs in as the demo admin of the mock tenant.
 *
 * Mock courses (tests/mock-api/server.mjs): 1 twenty-eight releases (two pages), 2 no release, 3 not made with the
 * builder, 4 another author's, 5 fails once, 6 three releases, 9 a base with a variant (three releases). The comparison
 * itself is in studio-compare.a11y.spec.ts; here are the controls of this page that lead to it.
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

const id = (kind: "v" | "r", course: number, n: number) => `01${kind}${String(course).padStart(2, "0")}${String(n).padStart(21, "0")}`;

test("every release but the first links to a comparison with the previous release", async ({ page }) => {
  await open(page, 6);
  await expect(page.locator("tbody tr")).toHaveCount(3);
  const link = page.getByRole("link", { name: "Compare release 3 with the previous release" });
  await expect(link).toHaveText("Compare with previous");
  await expect(link).toHaveAttribute("href", `/studio/courses/6/compare?from=${id("r", 6, 2)}&to=${id("r", 6, 3)}&lang=en`);
  await expect(page.locator("tbody tr").last()).toContainText("First release");
  expect(await scan(page), "links").toEqual([]);
  await link.click();
  await expect(page.locator('[role="tree"]')).toBeVisible();
});

test("two releases are ticked by keyboard, a third is refused, and Compare opens them older first", async ({ page }) => {
  await open(page, 6);
  await expect(page.locator("tbody tr")).toHaveCount(3);
  const box = (n: number) => page.getByRole("checkbox", { name: `Select release ${n} to compare` });
  const go = page.getByRole("button", { name: "Compare the two selected" });
  await expect(go).toBeDisabled();
  await expect(page.locator("[data-pick-count]")).toHaveText("Selected for comparison: 0 of 2");

  await box(3).focus();
  await page.keyboard.press("Space");
  await box(1).focus();
  await page.keyboard.press("Space");
  await expect(page.locator("[data-pick-count]")).toContainText("Two are selected. Clear one to choose another.");
  await expect(box(2)).toBeDisabled();
  await expect(page.locator("[data-announcer]")).toHaveText("Selected for comparison: 2 of 2");
  await expect(go).toBeEnabled();
  expect(await scan(page), "two ticked").toEqual([]);

  await go.focus();
  await page.keyboard.press("Enter");
  await expect(page).toHaveURL(new RegExp(`from=${id("v", 6, 1)}&to=${id("v", 6, 3)}&lang=en`));
  await expect(page.locator('[role="tree"]')).toBeVisible();
});

test("clearing the selection frees the other checkboxes", async ({ page }) => {
  await open(page, 6);
  await expect(page.locator("tbody tr")).toHaveCount(3);
  await page.getByRole("checkbox", { name: "Select release 3 to compare" }).check();
  await page.getByRole("checkbox", { name: "Select release 2 to compare" }).check();
  await page.getByRole("button", { name: "Clear the selection" }).click();
  await expect(page.getByRole("checkbox", { name: "Select release 1 to compare" })).toBeEnabled();
  await expect(page.getByRole("checkbox", { name: "Select release 3 to compare" })).not.toBeChecked();
});

test("all versions can be shown, with their state, the release and the branch, and picked for comparison too", async ({ page }) => {
  await open(page, 9);
  await expect(page.locator("tbody tr")).toHaveCount(3);
  const toggle = page.getByLabel("Show all versions, including proposals, rejected and replaced ones");
  await toggle.focus();
  await page.keyboard.press("Space");
  await expect(page.locator("[data-all-versions] table")).toBeVisible();
  await expect(toggle).toBeFocused();
  const all = page.locator("[data-all-versions] tbody tr");
  await expect(all.first()).toContainText("Proposed, not applied");
  await expect(page.locator("[data-all-versions]")).toContainText("Rejected");
  await expect(page.locator("[data-all-versions]")).toContainText("Expert");
  await expect(page.locator("[data-all-versions]")).toContainText("Base course");
  await expect(page.locator("[data-all-versions]")).toContainText("Never released");
  expect(await scan(page), "all versions").toEqual([]);
  expect(await noSidewaysScroll(page), "page scrolls sideways").toBeLessThanOrEqual(1);

  // a base version against a variant version, both from the full list
  await page.locator(`[data-all-versions] input[data-pick="${id("v", 9, 3)}"]`).check();
  await page.locator(`[data-all-versions] input[data-pick="${id("v", 10, 2)}"]`).check();
  await page.getByRole("button", { name: "Compare the two selected" }).click();
  // the older version (by version number) is `from`, whichever branch it is on
  await expect(page).toHaveURL(new RegExp(`from=${id("v", 10, 2)}&to=${id("v", 9, 3)}`));
  await expect(page.locator('[role="tree"]')).toBeVisible();
});

test("the new controls pass axe in Polish and Chinese", async ({ page }) => {
  await open(page, 6, "pl");
  await expect(page.getByRole("button", { name: "Porównaj wybrane dwie" })).toBeDisabled();
  await page.getByLabel("Pokaż wszystkie wersje, także propozycje, odrzucone i zastąpione").check();
  await expect(page.locator("[data-all-versions] table")).toBeVisible();
  expect(await scan(page), "pl").toEqual([]);
  await open(page, 6, "zh");
  await expect(page.getByRole("button", { name: "对比所选的两个" })).toBeDisabled();
  expect(await scan(page), "zh").toEqual([]);
});
