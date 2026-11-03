import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * Review time in the studio (ADR 0194, AM-5, WCAG 2.2 AA, axe-core) against the mock tenant API, at desktop and 360 px
 * phone width with reduced motion: the notice page that names exactly what is recorded (English, Polish, Chinese), the
 * indicator that shows only while the academy records review time, and what the browser sends when the page goes away:
 * the course, the whole minute, the place and a random code, and nothing else. Each project has its own tenant, so the
 * on/off switch of the mock does not leak between them.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const api = process.env.MOCK_API_PORT ?? "4390";
const host = (slug: string) => `http://${slug}.app.localhost:${web}`;
const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];
const slugOf = (project: string) => (project === "desktop" ? "coffee" : "nightsky");

test.use({ contextOptions: { reducedMotion: "reduce" } });

async function scan(page: Page) {
  const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();
  return results.violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.slice(0, 3).map((n) => `${n.target.join(" ")} - ${n.any[0]?.message ?? n.failureSummary ?? ""}`).join(" | ")}`);
}

const mock = (slug: string, path: string, init: RequestInit = {}) =>
  fetch(`http://127.0.0.1:${api}${path}`, { ...init, headers: { "x-ulams-api-host": `${slug}.localhost`, "content-type": "application/json" } });
const setReviewTime = async (slug: string, enabled: boolean) => expect((await mock(slug, "/__mock/authoring-metrics/review", { method: "POST", body: JSON.stringify({ enabled }) })).ok).toBe(true);
const posts = async (slug: string) => {
  const body = (await (await mock(slug, "/__mock/authoring-metrics/activity")).json()) as { posts?: Array<Record<string, unknown>>; data?: { posts: Array<Record<string, unknown>> } };
  return body.data?.posts ?? body.posts ?? [];
};

test("the notice names what is measured, stored, not stored, shown and kept, and passes axe", async ({ page }) => {
  await page.goto(`${host("coffee")}/studio/review-time?lang=en`);
  await expect(page.locator("h1")).toHaveText("How review time is recorded");
  for (const heading of ["What is measured", "What is stored", "What is not stored", "What is shown", "How long it is kept", "Who decides"]) {
    await expect(page.getByRole("heading", { name: heading })).toBeVisible();
  }
  await expect(page.locator("main")).toContainText("Never per person");
  await expect(page.locator("main")).toContainText("deleted after 7 days");
  await expect(page.locator("main")).toContainText("Your name or account, your address, your browser or device");
  expect(await scan(page)).toEqual([]);
});

test("the notice is in Polish and Chinese", async ({ page }) => {
  await page.goto(`${host("coffee")}/studio/review-time?lang=pl`);
  await expect(page.locator("h1")).toHaveText("Jak zapisywany jest czas przeglądu");
  await expect(page.locator("main")).toHaveAttribute("lang", "pl");
  await expect(page.locator("main")).toContainText("usuwane po 7 dniach");
  expect(await scan(page), "pl").toEqual([]);
  await page.goto(`${host("coffee")}/studio/review-time?lang=zh`);
  await expect(page.locator("h1")).toHaveText("审阅时间如何记录");
  await expect(page.locator("main")).toHaveAttribute("lang", "zh-Hans");
  expect(await scan(page), "zh").toEqual([]);
});

test("the indicator shows only while the academy records review time, and links to the notice", async ({ page }, info) => {
  const slug = slugOf(info.project.name);
  await setReviewTime(slug, false);
  await page.goto(`${host(slug)}/studio/courses/1/production?lang=en`);
  await expect(page.locator("tbody tr").first()).toBeVisible();
  await expect(page.locator("[data-review-indicator]")).toBeHidden();

  await setReviewTime(slug, true);
  await page.goto(`${host(slug)}/studio/courses/1/production?lang=en`);
  const indicator = page.locator("[data-review-indicator]");
  await expect(indicator).toBeVisible();
  await expect(indicator).toContainText("Review time is recorded for this course (anonymous)");
  expect(await scan(page), "with the indicator").toEqual([]);
  await indicator.getByRole("link", { name: "What is recorded" }).click();
  await expect(page.locator("h1")).toHaveText("How review time is recorded");
  await setReviewTime(slug, false);
});

test("the indicator is in Polish", async ({ page }, info) => {
  const slug = slugOf(info.project.name);
  await setReviewTime(slug, true);
  await page.goto(`${host(slug)}/studio/courses/1/production?lang=pl`);
  await expect(page.locator("[data-review-indicator]")).toContainText("Czas przeglądu jest zapisywany dla tego kursu (anonimowo)");
  expect(await scan(page), "pl").toEqual([]);
  await setReviewTime(slug, false);
});

test("leaving the page reports the active minute: the course, the minute, the place and a random code, nothing else", async ({ page }, info) => {
  const slug = slugOf(info.project.name);
  await setReviewTime(slug, true);
  await page.goto(`${host(slug)}/studio/courses/1/production?lang=en`);
  await expect(page.locator("[data-review-indicator]")).toBeVisible();
  await page.keyboard.press("Tab");
  await page.mouse.move(10, 10);
  await page.goto(`${host(slug)}/studio`);

  await expect.poll(async () => (await posts(slug)).length).toBeGreaterThanOrEqual(1);
  const post = (await posts(slug))[0]!;
  expect(Object.keys(post).sort()).toEqual(["course_id", "minutes", "surface", "tab_nonce"]);
  expect(post.course_id).toBe(1);
  expect(post.surface).toBe("studio");
  expect(String(post.tab_nonce)).toMatch(/^[A-Za-z0-9_-]{22}$/);
  expect((post.minutes as string[]).every((m) => /^\d{4}-\d\d-\d\dT\d\d:\d\d:00\.000Z$/.test(m))).toBe(true);
  await setReviewTime(slug, false);
});

test("nothing is reported while the setting is off", async ({ page }, info) => {
  const slug = slugOf(info.project.name);
  await setReviewTime(slug, false);
  await page.goto(`${host(slug)}/studio/courses/1/production?lang=en`);
  await expect(page.locator("tbody tr").first()).toBeVisible();
  await page.keyboard.press("Tab");
  await page.goto(`${host(slug)}/studio`);

  expect(await posts(slug)).toEqual([]);
});
