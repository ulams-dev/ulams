import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * P5R-2 on the learner pages, light and dark, desktop and 360 px (the two Playwright projects), against the mock
 * tenant API: the tenant logo in the header with its alt text, the per-tenant web manifest and the Course JSON-LD.
 *   brandl  the light coffee academy with a PNG logo and an admin-written alt text
 *   brandd  the dark night sky academy with an SVG logo and no alt text (the company name is the alternative)
 *   coffee, nightsky  the same academies without a logo: the company name stays as text
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const host = (slug: string) => `http://${slug}.app.localhost:${web}`;
const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];

test.use({ contextOptions: { reducedMotion: "reduce" } });

async function scan(page: Page) {
  const results = await new AxeBuilder({ page }).withTags(TAGS).exclude("iframe").analyze();
  return results.violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.slice(0, 3).map((n) => `${n.target.join(" ")} - ${n.any[0]?.message ?? n.failureSummary ?? ""}`).join(" | ")}`);
}

async function revealAll(page: Page) {
  await page.evaluate(async () => {
    for (let y = 0; y < document.body.scrollHeight; y += 600) {
      window.scrollTo({ top: y, behavior: "instant" });
      await new Promise((r) => setTimeout(r, 40));
    }
    window.scrollTo({ top: 0, behavior: "instant" });
  });
  await page.waitForTimeout(400);
}

async function open(page: Page, slug: string, path: string) {
  const response = await page.goto(`${host(slug)}${path}`, { waitUntil: "load" });
  expect(response?.status(), `HTTP status of ${path}`).toBe(200);
  await revealAll(page);
}

const LOGOS = [
  { mode: "light", slug: "brandl", alt: "The Coffee Atlas, home", src: /\/storage\/theme\/logo\.png$/ },
  { mode: "dark", slug: "brandd", alt: "Night Sky Explorers", src: /\/storage\/theme\/logo\.svg$/ },
];

for (const logo of LOGOS) {
  for (const path of ["/", "/courses/1", "/account"]) {
    test(`${logo.mode}: the tenant logo in the header of ${path}`, async ({ page }) => {
      await open(page, logo.slug, path);
      const image = page.locator("header .u-brand img.u-brand__img");
      await expect(image).toHaveCount(1);
      await expect(image).toHaveAttribute("src", logo.src);
      await expect(image).toHaveAttribute("alt", logo.alt);
      // the logo is the link's name: the link has no label of its own to override the alt text
      const link = page.locator("header a.u-brand");
      await expect(link).not.toHaveAttribute("aria-label", /.+/);
      await expect(page.getByRole("link", { name: logo.alt, exact: true }).first()).toBeVisible();
      // no mark and no text beside it, and the picture really loaded
      await expect(page.locator("header .u-brand__mark, header .u-brand__name")).toHaveCount(0);
      expect(await image.evaluate((el: HTMLImageElement) => el.complete && el.naturalWidth > 0), "the logo loaded").toBe(true);
      const box = await image.boundingBox();
      expect(box!.height).toBeLessThanOrEqual(40.5);
      // 360 px: nothing pushes the page sideways
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1)).toBe(true);
      const violations = await scan(page);
      expect(violations, violations.join("\n")).toEqual([]);
    });
  }
}

for (const [mode, slug, name] of [["light", "coffee", "The Coffee Atlas"], ["dark", "nightsky", "Night Sky Explorers"]] as const) {
  test(`${mode}: without a logo the header shows the company name as text`, async ({ page }) => {
    await open(page, slug, "/courses/1");
    await expect(page.locator("header img.u-brand__img")).toHaveCount(0);
    await expect(page.locator("header .u-brand__name")).toHaveText(name);
    const violations = await scan(page);
    expect(violations, violations.join("\n")).toEqual([]);
  });
}

interface CourseLd {
  "@context": string;
  "@type": string;
  name: string;
  description: string;
  url: string;
  inLanguage: string;
  provider: { "@type": string; name: string; url: string };
  hasCourseInstance: { "@type": string; courseMode: string[]; courseWorkload?: string };
  offers?: { "@type": string; price: string; priceCurrency: string };
}

async function jsonLd(page: Page): Promise<unknown[]> {
  const blocks = await page.locator('script[type="application/ld+json"]').allTextContents();
  return blocks.map((text) => JSON.parse(text) as unknown);
}

test("a course page carries valid Course structured data", async ({ page }) => {
  await open(page, "brandl", "/courses/1");
  const blocks = (await jsonLd(page)).flat() as CourseLd[];
  expect(blocks).toHaveLength(1);
  const course = blocks[0]!;
  expect(course["@context"]).toBe("https://schema.org");
  expect(course["@type"]).toBe("Course");
  expect(course.name).toBe("The Coffee Atlas — From Seed to Cup");
  expect(course.description.length).toBeGreaterThan(20);
  expect(course.url).toBe(`${host("brandl")}/courses/1`);
  expect(course.inLanguage).toBe("en");
  expect(course.provider).toEqual({ "@type": "Organization", name: "The Coffee Atlas", url: `${host("brandl")}/` });
  expect(course.hasCourseInstance).toMatchObject({ "@type": "CourseInstance", courseMode: ["online", "self-paced"], courseWorkload: "PT9H" });
  // the course has a price, so it has an offer, in major units and an upper-case currency
  expect(course.offers).toMatchObject({ "@type": "Offer", price: "89.00", priceCurrency: "EUR" });
});

test("a free course has no offer and no price", async ({ page }) => {
  await open(page, "nightsky", "/courses/1");
  const course = (await jsonLd(page)).flat()[0] as CourseLd;
  expect(course["@type"]).toBe("Course");
  expect(course.provider.name).toBe("Night Sky Explorers");
  expect(course).not.toHaveProperty("offers");
  expect(JSON.stringify(course)).not.toContain("price");
});

test("the structured data cannot close its script element", async ({ page }) => {
  await open(page, "brandl", "/courses/1");
  const raw = await page.content();
  const block = /<script type="application\/ld\+json">([\s\S]*?)<\/script>/.exec(raw)![1]!;
  expect(block).not.toContain("</");
  expect(block).not.toContain("<");
});

test("the platform pages keep their own structured data and the other pages have none", async ({ page }) => {
  await page.goto(`http://app.localhost:${web}/`);
  const types = (await jsonLd(page)).flat().map((b) => (b as { "@type": string })["@type"]);
  expect(types).toContain("SoftwareApplication");
  expect(types).not.toContain("Course");
  await open(page, "coffee", "/login");
  expect(await jsonLd(page)).toEqual([]);
  await open(page, "coffee", "/account");
  expect(await jsonLd(page)).toEqual([]);
});

test("a tenant host serves its own web manifest, the platform host the product's", async ({ request }) => {
  const tenant = await request.get(`${host("brandl")}/site.webmanifest`);
  expect(tenant.status()).toBe(200);
  expect(tenant.headers()["content-type"]).toContain("application/manifest+json");
  const manifest = await tenant.json();
  expect(manifest.name).toBe("The Coffee Atlas");
  expect(manifest.theme_color).toBe("#f6f1e9");
  expect(manifest.background_color).toBe("#f6f1e9");
  expect(manifest.icons[0]).toMatchObject({ sizes: "any", type: "image/png" });
  expect(manifest.icons[0].src).toMatch(/\/storage\/theme\/logo\.png$/);
  // the platform icons stay, so the app remains installable
  expect(manifest.icons.map((i: { src: string }) => i.src)).toEqual(expect.arrayContaining(["/icon-192.png", "/icon-512.png"]));

  const dark = await (await request.get(`${host("brandd")}/site.webmanifest`)).json();
  expect(dark.name).toBe("Night Sky Explorers");
  expect(dark.short_name).toBe("Night Sky Ex");
  expect(dark.theme_color).toBe("#13153a");
  expect(dark.icons[0]).toMatchObject({ type: "image/svg+xml" });

  const plain = await (await request.get(`${host("coffee")}/site.webmanifest`)).json();
  expect(plain.icons.map((i: { src: string }) => i.src)).toEqual(["/icon-192.png", "/icon-512.png", "/icon-maskable-512.png"]);

  const platform = await (await request.get(`http://app.localhost:${web}/site.webmanifest`)).json();
  expect(platform).toMatchObject({ name: "ulams", short_name: "ulams", theme_color: "#0F2B46" });
});
