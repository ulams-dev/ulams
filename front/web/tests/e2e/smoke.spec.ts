import { expect, test, type Page } from "@playwright/test";
import { demoCourse } from "./demo-data.ts";

const port = process.env.WEB_BASE_PORT ?? "4321";
const base = (slug: string) => `http://${slug}.app.localhost:${port}`;

// topic types opened per tenant (looked up in the API, see demo-data.ts)
const TENANTS = [
  { slug: "coffee", title: /Learn coffee/, kinds: ["RichText", "Video", "H5P", "GiftQuiz"] },
  { slug: "oncall", title: /Stay calm/, kinds: ["RichText", "Video"] },
  { slug: "nightsky", title: /adventure to the stars/i, kinds: ["preview"] },
];

async function noHorizontalScroll(page: Page) {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow, "horizontal overflow in px").toBeLessThanOrEqual(1);
}

for (const tenant of TENANTS) {
  test.describe(tenant.slug, () => {
    test("landing renders API data with landmarks and a skip link", async ({ page }) => {
      const errors: string[] = [];
      page.on("pageerror", (e) => errors.push(e.message));
      await page.goto(base(tenant.slug));
      await expect(page.locator("h1")).toHaveText(tenant.title);
      await expect(page.locator("header").first()).toBeVisible();
      await expect(page.locator("main#main")).toHaveCount(1);
      await expect(page.locator("footer")).toHaveCount(1);
      await expect(page.locator("a.u-skip")).toHaveAttribute("href", "#main");
      // syllabus comes from the API: one link per seeded topic
      expect(await page.locator("#syllabus li").count()).toBeGreaterThan(5);
      await expect(page.locator(".u-demo summary")).toContainText("resets hourly");
      await noHorizontalScroll(page);
      expect(errors).toEqual([]);
    });

    test("course page opens and links into the player", async ({ page }) => {
      const { courseId } = await demoCourse(tenant.slug);
      await page.goto(`${base(tenant.slug)}/courses/${courseId}`);
      await expect(page.locator("h1")).toBeVisible();
      await expect(page.locator("#syllabus")).toBeVisible();
      await noHorizontalScroll(page);
    });

    for (const kind of tenant.kinds) {
      test(`lesson (${kind}) opens already logged in`, async ({ page }) => {
        const errors: string[] = [];
        page.on("pageerror", (e) => errors.push(e.message));
        const demo = await demoCourse(tenant.slug);
        const topic = kind === "preview" ? demo.previewTopic : demo.topics[kind];
        test.skip(!topic, `no ${kind} topic in the seeded course`);
        const response = await page.goto(`${base(tenant.slug)}/learn/${demo.courseId}/${topic}`);
        expect(response?.status()).toBe(200);
        await expect(page.locator("h1.u-player__title")).toBeVisible();
        await expect(page.locator("ulams-progress")).toBeVisible();
        await expect(page.locator("nav[aria-label='Course program'] a[aria-current='page']")).toHaveCount(1);
        const cookies = await page.context().cookies();
        expect(cookies.find((c) => c.name === "ulams_session")?.httpOnly).toBe(true);
        await noHorizontalScroll(page);
        expect(errors).toEqual([]);
      });
    }
  });
}

test("quiz: start an attempt and see the first question", async ({ page }) => {
  const demo = await demoCourse("coffee");
  await page.goto(`${base("coffee")}/learn/${demo.courseId}/${demo.topics.GiftQuiz}`);
  await page.click("[data-start]");
  // the seeded quiz allows three attempts per hour (the demo resets hourly)
  const question = page.locator(".u-quiz__q legend");
  const noAttempts = page.locator(".u-quiz__error");
  await expect(question.or(noAttempts)).toBeVisible({ timeout: 20_000 });
  if (await question.isVisible()) await expect(page.locator(".u-quiz__dots button")).toHaveCount(8);
});

test("the BFF refuses calls outside its allow-list and cross-site writes", async ({ request }) => {
  expect((await request.get(`${base("coffee")}/bff/api/admin/users`)).status()).toBe(404);
  const cross = await request.patch(`${base("coffee")}/bff/api/courses/progress/1`, {
    headers: { origin: "http://evil.example", "content-type": "application/json" },
    data: { progress: [] },
  });
  expect(cross.status()).toBe(403);
});

test("platform landing sells the product and links every demo", async ({ page }) => {
  const errors: string[] = [];
  page.on("pageerror", (e) => errors.push(e.message));
  await page.goto(`http://app.localhost:${port}/`);
  await expect(page.locator("h1")).toContainText("true to their sources");
  const cards = page.locator("#demos li");
  await expect(cards).toHaveCount(3);
  for (const slug of ["coffee", "oncall", "nightsky"]) {
    await expect(page.locator(`#demos a[href^="http://${slug}.app.localhost"]`)).toHaveCount(1);
    await expect(page.locator(`#demos a[href^="http://${slug}.admin.localhost"]`)).toHaveCount(1);
  }
  await expect(page.locator("#demos")).toContainText("reset every hour");
  expect(await page.locator(".u-status--coming").count()).toBeGreaterThan(2);
  await noHorizontalScroll(page);
  expect(errors).toEqual([]);
});

test("account lists my courses with progress and logs out", async ({ page }) => {
  await page.goto(`${base("coffee")}/account`);
  await expect(page.locator("h1")).toBeVisible();
  await expect(page.locator(".u-account__course").first()).toBeVisible();
  await expect(page.locator(".u-account__progress").first()).toHaveAttribute("aria-valuenow", /\d+/);
  await page.click("text=Log out");
  await expect(page).toHaveURL(`${base("coffee")}/`);
  expect((await page.context().cookies()).find((c) => c.name === "ulams_session")).toBeUndefined();
});

test("events listing and details", async ({ page }) => {
  await page.goto(`${base("oncall")}/events`);
  await expect(page.locator("h1")).toHaveText("Live sessions and events");
  for (const kind of ["webinar", "in-person", "consultation"]) {
    const link = page.locator(`a[href^="/events/${kind}/"]`).first();
    await expect(link).toBeVisible();
  }
  await page.locator('a[href^="/events/consultation/"]').first().click();
  await expect(page.locator("h1")).toBeVisible();
  await expect(page.locator("text=Open slots")).toBeVisible();
});

test("tenant accent from API settings is applied server-side", async ({ request }) => {
  const html = await (await request.get(base("coffee"))).text();
  expect(html).toMatch(/\[data-theme="coffee"\]\{--ulams-color-accent:#[0-9a-f]{6}/);
});

test("platform comparison: two groups behind a segmented control, ulams column, as-of line and sources", async ({ page }) => {
  await page.goto(`http://app.localhost:${port}/#compare`);
  const first = page.locator("#compare .u-compare__panel").nth(0).locator("table");
  const second = page.locator("#compare .u-compare__panel").nth(1).locator("table");
  await expect(first.locator("caption")).toContainText("Feature comparison");
  expect(await first.locator('thead th[scope="col"]').count()).toBe(8);
  expect(await first.locator('tbody th[scope="row"]').count()).toBe(22);
  const sections = first.locator('tbody th[scope="rowgroup"]');
  await expect(sections).toHaveText(["Developer & headless", "AI", "Content standards", "Business"]);
  await expect(first.locator("tbody").first().locator('th[scope="row"]').first()).toContainText("REST API");
  await expect(first.locator("thead th.is-ours")).toContainText("ulams");
  await expect(second).toBeHidden();
  await page.locator('#compare label:has-text("Enterprise suites")').click();
  await expect(second).toBeVisible();
  await expect(first).toBeHidden();
  expect(await second.locator('thead th[scope="col"]').count()).toBe(8);
  expect(await second.locator('tbody th[scope="row"]').count()).toBe(27);
  await expect(second.locator("thead th.is-ours")).toContainText("ulams");
  await expect(second.locator("thead")).toContainText("Articulate 360");
  await expect(page.locator("#compare")).toContainText("As of");
  await page.locator("#compare summary").click();
  expect(await page.locator("#compare details li a[href^='https://']").count()).toBeGreaterThan(60);
  await noHorizontalScroll(page);
});
