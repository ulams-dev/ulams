import { expect, test, type Page } from "@playwright/test";

const port = process.env.WEB_BASE_PORT ?? "4321";
const base = (slug: string) => `http://${slug}.app.localhost:${port}`;

const TENANTS = [
  { slug: "coffee", course: 1, title: /Learn coffee/, topics: [3, 1, 5, 16] },
  { slug: "oncall", course: 1, title: /Stay calm/, topics: [4, 1] },
  { slug: "nightsky", course: 2, title: /adventure to the stars/i, topics: [16] },
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
      await page.goto(`${base(tenant.slug)}/courses/${tenant.course}`);
      await expect(page.locator("h1")).toBeVisible();
      await expect(page.locator("#syllabus")).toBeVisible();
      await noHorizontalScroll(page);
    });

    for (const topic of tenant.topics) {
      test(`lesson ${topic} opens already logged in`, async ({ page }) => {
        const errors: string[] = [];
        page.on("pageerror", (e) => errors.push(e.message));
        const response = await page.goto(`${base(tenant.slug)}/learn/${tenant.course}/${topic}`);
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
  await page.goto(`${base("coffee")}/learn/1/16`);
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
