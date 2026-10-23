import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * The studio Production page (/studio/courses/:id/production, WCAG 2.2 AA, axe-core) against the mock tenant API, at desktop
 * and 360 px phone width (the Playwright projects) with reduced motion on: the measured run with its stage table, cost and
 * element split, an older build with whole-run times only, a course whose elements pre-date provenance, recorded review
 * time, a course that was not made with the builder, another author's course and a failure with retry, in English, Polish
 * and Simplified Chinese. The studio signs in as the demo admin of the mock tenant.
 *
 * Mock courses (tests/mock-api/server.mjs): 1 the measured run, 2 whole-run times and nothing the AI wrote, 3 not made with
 * the builder, 4 another author's, 5 fails once, 6 unknown elements, 7 review time recorded.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const host = (slug: string) => `http://${slug}.app.localhost:${web}`;
const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];

test.use({ contextOptions: { reducedMotion: "reduce" } });

async function scan(page: Page) {
  const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();
  return results.violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.slice(0, 3).map((n) => `${n.target.join(" ")} - ${n.any[0]?.message ?? n.failureSummary ?? ""}`).join(" | ")}`);
}

const noSidewaysScroll = (page: Page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
const slugOf = (project: string) => (project === "desktop" ? "coffee" : "nightsky");

async function open(page: Page, course: number, lang = "en", slug = "coffee") {
  await page.goto(`${host(slug)}/studio/courses/${course}/production?lang=${lang}`, { waitUntil: "load" });
}

test("the measured run: every figure is text, the page passes axe and reflows", async ({ page }) => {
  await open(page, 1);
  await expect(page.locator("h1")).toHaveText("How this course was produced");
  await expect(page.locator("[data-studio-production] table").first().locator("caption")).toHaveText("Time the Course Builder took for each stage, in the order they ran");
  await expect(page.locator("tbody tr").first()).toContainText("Outline");
  await expect(page.locator("tbody tr").first()).toContainText("24 s");
  await expect(page.locator("tbody tr").nth(1)).toContainText("3 min 10 s");
  await expect(page.getByText("7 min 44 s").first()).toBeVisible();
  await expect(page.getByText("$0.2841")).toBeVisible();
  await expect(page.getByText("Not recorded. Review time is only recorded when your administrator turns it on.")).toBeVisible();
  await expect(page.locator('[role="img"]')).toHaveAccessibleName(/Kept as the AI wrote it 49 \(83%\)/);
  expect(await scan(page), "loaded").toEqual([]);
  expect(await noSidewaysScroll(page), "page scrolls sideways").toBeLessThanOrEqual(1);
});

test("no benchmark and no comparison appears on the page", async ({ page }) => {
  await open(page, 1);
  await expect(page.locator("[data-studio-production] table").first()).toBeVisible();
  const text = (await page.locator("main").innerText()).toLowerCase();
  expect(text).toContain("not compared with any other course");
  for (const banned of ["chapman", "industry average", "benchmark", "716"]) expect(text).not.toContain(banned);
});

test("the tables and the downloads are reachable by keyboard and show focus", async ({ page }) => {
  await open(page, 1);
  await expect(page.locator("tbody tr").first()).toBeVisible();
  await expect(page.locator('[data-studio-production] [role="region"][tabindex="0"]').first()).toHaveAccessibleName(/Stage times/);
  await page.locator("#main").focus();
  const stops: string[] = [];
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
    stops.push(stop.label);
    expect(stop.outline, `focus indicator on ${stop.tag} ${stop.label}`).toBe(true);
    if (stop.tag === "BUTTON") expect(Math.min(stop.width, stop.height), `target size of ${stop.label}`).toBeGreaterThanOrEqual(24);
  }
  expect(stops).toContain("Download CSV");
  expect(stops).toContain("Download JSON");
});

test("the CSV download has the columns of the admin export and an empty cell for author time", async ({ page }) => {
  await open(page, 1);
  const [download] = await Promise.all([page.waitForEvent("download"), page.getByRole("button", { name: "Download CSV" }).click()]);
  expect(download.suggestedFilename()).toBe("authoring-metrics-course-1.csv");
  const path = await download.path();
  const text = (await import("node:fs")).readFileSync(path!, "utf8").trimEnd().split("\n") as [string, string];
  expect(text[0]).toMatch(/^course_id,session_id,method_version,computed_at,generation_seconds,/);
  const cells = text[1].split(",");
  expect(cells[text[0].split(",").indexOf("author_minutes")]).toBe("");
  await expect(page.locator("[data-announcer]")).toHaveText("Downloaded authoring-metrics-course-1.csv");
});

test("an older build shows whole-run times and says the AI wrote nothing to classify", async ({ page }) => {
  await open(page, 2);
  await expect(page.locator("tbody tr").first()).toContainText("Whole run only (older build)");
  await expect(page.getByText("No AI elements to classify yet.")).toBeVisible();
  expect(await scan(page)).toEqual([]);
});

test("elements from before provenance are called unknown, not guessed", async ({ page }) => {
  await open(page, 6);
  await expect(page.getByText("Elements from before provenance was recorded are not guessed.")).toBeVisible();
  await expect(page.locator("tbody tr").filter({ hasText: "Unknown (written before this was recorded)" })).toContainText("59");
  expect(await scan(page)).toEqual([]);
});

test("recorded review time and maintenance cost are shown, and a stage that did not finish says so", async ({ page }) => {
  await open(page, 7);
  await expect(page.getByText("2 h 15 min of active screen time")).toBeVisible();
  await expect(page.getByText("$1.52")).toBeVisible();
  await expect(page.locator("tbody tr").first()).toContainText("Did not finish normally");
  expect(await scan(page)).toEqual([]);
});

test("a course that was not made with the builder says so", async ({ page }) => {
  await open(page, 3);
  await expect(page.locator('[data-state="not-built"]')).toContainText("not made with the Course Builder");
  expect(await scan(page)).toEqual([]);
});

test("another author's course is explained", async ({ page }) => {
  await open(page, 4);
  await expect(page.locator('[data-studio-production] [role="alert"]')).toContainText("belongs to another author");
  expect(await scan(page)).toEqual([]);
});

test("an error offers a retry that recovers", async ({ page }, info) => {
  const slug = slugOf(info.project.name);
  const reset = await fetch(`http://127.0.0.1:${process.env.MOCK_API_PORT ?? "4390"}/__mock/authoring-metrics/reset`, { method: "POST", headers: { "x-ulams-api-host": `${slug}.localhost` } });
  expect(reset.ok).toBe(true);
  await open(page, 5, "en", slug);
  await expect(page.locator('[data-studio-production] [role="alert"]')).toContainText("The figures could not be loaded.");
  expect(await scan(page), "error").toEqual([]);
  await page.getByRole("button", { name: "Try again" }).click();
  await expect(page.locator("tbody tr").first()).toBeVisible();
});

test("Polish and Chinese pages pass axe and say their language", async ({ page }) => {
  await open(page, 1, "pl");
  await expect(page.locator("h1")).toHaveText("Jak powstał ten kurs");
  await expect(page.locator("main")).toHaveAttribute("lang", "pl");
  await expect(page.locator("tbody tr").first()).toContainText("Konspekt");
  expect(await scan(page), "pl").toEqual([]);
  await open(page, 1, "zh");
  await expect(page.locator("h1")).toHaveText("这门课程是如何制作的");
  await expect(page.locator("main")).toHaveAttribute("lang", "zh-Hans");
  await expect(page.locator("tbody tr").first()).toContainText("大纲");
  expect(await scan(page), "zh").toEqual([]);
});

test("the studio navigation links to Production on a course page", async ({ page }) => {
  await open(page, 1);
  await expect(page.getByRole("link", { name: "Production" })).toHaveAttribute("aria-current", "page");
});
