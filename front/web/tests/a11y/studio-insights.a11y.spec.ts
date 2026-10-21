import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * The studio Insights page (/studio/courses/:id/insights, WCAG 2.2 AA, axe-core) against the mock tenant API, at
 * desktop and 360 px phone width (the Playwright projects) with reduced motion on: the full page with the
 * confusing-sections table and the individual learners, a group below the minimum, a period without activity,
 * individual data that is not allowed, a failure with retry, a refusal and a module that is off, in English,
 * Polish and Simplified Chinese. The studio signs in as the demo admin of the mock tenant.
 *
 * Mock courses (tests/mock-api/server.mjs): 1 full, 2 small group, 3 empty period, 4 no individual data, 5 fails once,
 * 6 not allowed. The `polski` tenant has Learner Insights switched off.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const host = (slug: string) => `http://${slug}.app.localhost:${web}`;
const page_ = (course: number, lang = "en") => `/studio/courses/${course}/insights?lang=${lang}`;
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

test("full page: figures, confusing sections and learners pass axe and reflow", async ({ page }) => {
  await open(page, 1);
  await expect(page.locator("h1")).toHaveText("Course insights");
  await expect(page.locator("[data-studio-insights] h2", { hasText: "Privacy" })).toBeVisible();
  await expect(page.locator("[data-studio-insights]")).toContainText("Groups smaller than 10 learners are hidden");
  await expect(page.locator('[data-row="question:3"]')).toContainText("41%");
  await expect(page.locator('[data-row="topic:15"]')).toContainText("Hidden: fewer than 10 learners");
  await expect(page.locator('[data-row="21"]')).toContainText("Ada Lovelace");
  expect(await scan(page), "loaded").toEqual([]);
  expect(await noSidewaysScroll(page), "page scrolls sideways").toBeLessThanOrEqual(1);
});

test("the table is reachable and scrollable by keyboard, every control shows focus", async ({ page }) => {
  await open(page, 1);
  await expect(page.locator('[data-row="21"]')).toBeVisible();
  const regions = page.locator('[data-studio-insights] [role="region"][tabindex="0"]');
  await expect(regions).toHaveCount(2);
  await expect(regions.first()).toHaveAccessibleName(/Sections that confuse people/);

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
    if (stop.tag === "BUTTON" || stop.tag === "SELECT") expect(Math.min(stop.width, stop.height), `target size of ${stop.label}`).toBeGreaterThanOrEqual(24);
  }
});

test("changing the period reloads the page and announces it", async ({ page }) => {
  await open(page, 1);
  const select = page.getByLabel("Period");
  await select.focus();
  await select.selectOption("7");
  await expect(page.locator("[data-announcer]")).toHaveText("Insights updated: Last 7 days.");
  await expect(select).toBeFocused();
  expect(await scan(page), "after reload").toEqual([]);
});

test("activity of a learner opens under the list and takes the focus", async ({ page }) => {
  await open(page, 1);
  await page.getByRole("button", { name: /Show activity.*Ada Lovelace/ }).click();
  const heading = page.getByRole("heading", { name: "Activity of Ada Lovelace" });
  await expect(heading).toBeFocused();
  await expect(page.locator("[data-activity] table")).toContainText("Answered a question");
  expect(await scan(page), "with the activity").toEqual([]);
});

test("a group below the minimum shows only the explanation", async ({ page }) => {
  await open(page, 2);
  await expect(page.locator('[data-state="small-group"]')).toContainText("Not enough learners yet: 6 enrolled");
  await expect(page.locator("table")).toHaveCount(0);
  expect(await scan(page)).toEqual([]);
});

test("a period without activity has empty states", async ({ page }) => {
  await open(page, 3);
  await expect(page.locator("[data-studio-insights]")).toContainText("No quiz attempts were finished in this period.");
  await expect(page.locator("[data-studio-insights]")).toContainText("No section had learner activity in this period.");
  expect(await scan(page)).toEqual([]);
});

test("individual data that is not allowed is explained, the aggregates stay", async ({ page }) => {
  await open(page, 4);
  await expect(page.locator("[data-studio-insights]")).toContainText("Individual learners are visible only to people with the Learner Insights individuals permission");
  await expect(page.locator('[data-row="21"]')).toHaveCount(0);
  await expect(page.locator("[data-studio-insights]")).toContainText("Who needs support");
  expect(await scan(page)).toEqual([]);
});

test("an error offers a retry that recovers", async ({ page }, info) => {
  const slug = slugOf(info.project.name);
  const reset = await fetch(`http://127.0.0.1:${process.env.MOCK_API_PORT ?? "4390"}/__mock/insights/reset`, { method: "POST", headers: { "x-ulams-api-host": `${slug}.localhost` } });
  expect(reset.ok).toBe(true);
  await open(page, 5, "en", slug);
  await expect(page.locator('[data-studio-insights] [role="alert"]')).toContainText("The insights could not be loaded.");
  expect(await scan(page), "error").toEqual([]);
  await page.getByRole("button", { name: "Try again" }).click();
  await expect(page.locator("[data-studio-insights]")).toContainText("Progress and completion");
});

test("a refusal says who can see insights and offers no retry", async ({ page }) => {
  await open(page, 6);
  await expect(page.locator('[data-studio-insights] [role="alert"]')).toContainText("You can see insights for courses you author");
  await expect(page.getByRole("button", { name: "Try again" })).toHaveCount(0);
  expect(await scan(page)).toEqual([]);
});

test("a module that is off says so and links to the academy settings", async ({ page }) => {
  await open(page, 1, "en", "polski");
  await expect(page.locator('[data-state="module-off"]')).toContainText("Learner Insights is off for this academy");
  await expect(page.getByRole("link", { name: "Open the academy settings" })).toBeVisible();
  expect(await scan(page)).toEqual([]);
});

for (const [lang, heading, htmlLang] of [
  ["pl", "Wgląd w kurs", "pl"],
  ["zh", "课程洞察", "zh-Hans"],
] as const) {
  test(`${lang}: content is translated, marked with its language and passes axe`, async ({ page }) => {
    await open(page, 1, lang);
    await expect(page.locator("h1")).toHaveText(heading);
    await expect(page.locator("#main")).toHaveAttribute("lang", htmlLang);
    await expect(page.locator('[data-row="question:3"]')).toBeVisible();
    await expect(page.getByRole("navigation", { name: lang === "pl" ? "Język" : "语言" })).toBeVisible();
    expect(await scan(page)).toEqual([]);
    expect(await noSidewaysScroll(page), "page scrolls sideways").toBeLessThanOrEqual(1);
  });
}

test("the language follows the browser when ?lang= is absent", async ({ browser }) => {
  const context = await browser.newContext({ locale: "pl-PL", reducedMotion: "reduce" });
  const page = await context.newPage();
  await page.goto(`${host("coffee")}/studio/courses/1/insights`, { waitUntil: "load" });
  await expect(page.locator("h1")).toHaveText("Wgląd w kurs");
  await context.close();
});

test("an unknown course is a 404 and the BFF accepts no writes", async ({ page, request }) => {
  const response = await page.goto(`${host("coffee")}/studio/courses/999/insights`);
  expect(response?.status()).toBe(404);
  const write = await request.post(`${host("coffee")}/studio/api/learner-insights/backfill`, { headers: { Origin: host("coffee") }, data: {} });
  expect(write.status()).toBe(404);
});

test("the course session nav has an Insights link, the current page is marked", async ({ page }) => {
  await open(page, 1);
  await expect(page.getByRole("navigation", { name: "Course Builder" }).getByRole("link", { name: "Insights" })).toHaveAttribute("aria-current", "page");
});
