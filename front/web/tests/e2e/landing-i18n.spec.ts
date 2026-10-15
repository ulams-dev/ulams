import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * The platform landing in Polish (/pl/) and Simplified Chinese (/zh/): language and meta, hreflang,
 * the header switcher (keeps the section anchor), no horizontal overflow on desktop and at 360 px,
 * axe (WCAG 2.2 AA), the display-status switch, no extra scripts, and a screenshot per language and
 * viewport under tests/screens/landing-<lang>-<desktop|phone>.png. Needs the API for the demo cards.
 */
const port = process.env.WEB_BASE_PORT ?? "4321";
const base = `http://app.localhost:${port}`;
const CASES = [
  { lang: "pl", html: "pl", path: "/pl/", h1: "Kursy z dokumentacji", title: /^ulams — otwarty LMS/, label: "PL", skip: "Przejdź do treści", coming: "Wkrótce", demos: "Dema" },
  { lang: "zh", html: "zh-Hans", path: "/zh/", h1: "从文档构建课程", title: /^ulams — 开放的 AI 原生 LMS/, label: "中文", skip: "跳到正文", coming: "即将推出", demos: "演示" },
] as const;

async function open(page: Page, path: string) {
  const response = await page.goto(`${base}${path}`);
  expect(response?.status(), path).toBe(200);
  await expect(page.locator("#demos")).toHaveCount(1);
}

for (const c of CASES) {
  test.describe(`landing ${c.lang}`, () => {
    test("language, meta, hreflang and canonical", async ({ page }) => {
      await open(page, c.path);
      await expect(page.locator("html")).toHaveAttribute("lang", c.html);
      await expect(page).toHaveTitle(c.title);
      await expect(page.locator("h1")).toContainText(c.h1);
      await expect(page.locator("a.u-skip")).toHaveText(c.skip);
      await expect(page.locator('link[rel="canonical"]')).toHaveAttribute("href", `${base}${c.path}`);
      await expect(page.locator('meta[name="description"]')).toHaveAttribute("content", /.+/);
      await expect(page.locator('meta[name="robots"]')).toHaveAttribute("content", "index, follow, max-image-preview:large");
      await expect(page.locator('meta[property="og:url"]')).toHaveAttribute("content", `${base}${c.path}`);
      await expect(page.locator('meta[property="og:image:type"]')).toHaveAttribute("content", "image/png");
      await expect(page.locator('meta[name="twitter:title"]')).toHaveAttribute("content", await page.title());
      await expect(page.locator('meta[name="twitter:description"]')).toHaveAttribute("content", /.+/);
      const jsonLd = JSON.parse((await page.locator('head script[type="application/ld+json"]').textContent())!);
      expect(jsonLd).toEqual(expect.arrayContaining([expect.objectContaining({ "@type": "Organization" }), expect.objectContaining({ "@type": "WebSite" }), expect.objectContaining({ "@type": "SoftwareApplication" })]));
      const alternates = await page.locator('link[rel="alternate"][hreflang]').evaluateAll((els) => els.map((e) => [e.getAttribute("hreflang"), e.getAttribute("href")]));
      expect(alternates).toEqual([
        ["en", `${base}/`],
        ["pl", `${base}/pl/`],
        ["zh-Hans", `${base}/zh/`],
        ["x-default", `${base}/`],
      ]);
    });

    test("header: four links and a separate language switcher with the current language marked", async ({ page }) => {
      await open(page, c.path);
      await expect(page.locator("header nav.u-nav li")).toHaveCount(4);
      const switcher = page.locator("header nav.u-lang");
      await expect(switcher).toBeVisible();
      await expect(switcher.locator("a")).toHaveCount(3);
      await expect(switcher.locator('a[aria-current="true"]')).toHaveText(c.label);
      await expect(switcher.locator("a")).toHaveText(["EN", "PL", "中文"]);
      // the brand stays in the language
      await expect(page.locator("a.u-brand")).toHaveAttribute("href", c.path);
    });

    test("the switcher keeps the section anchor", async ({ page }) => {
      await page.goto(`${base}${c.path}#demos`);
      await expect(page.locator("#demos")).toHaveCount(1);
      const other = c.lang === "pl" ? "zh" : "pl";
      const link = page.locator(`header nav.u-lang a[hreflang="${other === "zh" ? "zh-Hans" : "pl"}"]`);
      await expect(link).toHaveAttribute("href", `/${other}/#demos`);
      await link.click();
      await expect(page).toHaveURL(`${base}/${other}/#demos`);
      await expect(page.locator("html")).toHaveAttribute("lang", other === "zh" ? "zh-Hans" : "pl");
      // and back to English
      await page.locator('header nav.u-lang a[hreflang="en"]').click();
      await expect(page).toHaveURL(`${base}/#demos`);
      await expect(page.locator("html")).toHaveAttribute("lang", "en");
    });

    test("translated sections: demos, separate comparison route and honest product status", async ({ page }) => {
      await open(page, c.path);
      await expect(page.locator("#demos li")).toHaveCount(6);
      await expect(page.locator("#demos")).not.toContainText("Open as learner");
      await expect(page.locator("#compare table")).toHaveCount(0);
      const comparisonHref = c.lang === "pl" ? "/pl/comparison/" : "/zh/comparison/";
      if (await page.locator("header details.u-menu").isVisible()) {
        await page.locator("header details.u-menu > summary").click();
        await expect(page.locator(`header details.u-menu a[href="${comparisonHref}"]`)).toBeVisible();
      } else {
        await expect(page.locator(`header nav.u-nav a[href="${comparisonHref}"]`)).toBeVisible();
      }
      const coming = await page.locator(".u-status--coming").count();
      expect(coming, "public pages expose roadmap badges").toBeGreaterThan(0);
      await expect(page.locator("main")).toContainText(c.coming);
      // none of the interface strings of the components stayed English
      for (const english of ["Pause", "Replay", "Skip to content", "Scroll sideways"]) await expect(page.locator("body")).not.toContainText(english);
    });

    test("adds no script beyond the switcher", async ({ request }) => {
      const scripts = async (path: string) => {
        const html = await (await request.get(`${base}${path}`)).text();
        return [...html.matchAll(/<script\b[^>]*>/g)].length;
      };
      expect(await scripts(c.path)).toBe(await scripts("/"));
    });

    test("no horizontal scrolling and axe finds no violations", async ({ page }, info) => {
      await page.emulateMedia({ reducedMotion: "reduce" });
      await open(page, c.path);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      expect(overflow, `${info.project.name}: page scrolls sideways`).toBeLessThanOrEqual(1);
      // cards and the comparison table scroll inside their own box; nothing else pokes out of the viewport
      const wide = await page.evaluate(() => {
        const limit = document.documentElement.clientWidth + 1;
        return [...document.querySelectorAll("main *")]
          .filter((el) => !el.closest(".u-compare__scroll, .u-stage, .u-wf__panels, .u-wf__tabs, pre, .u-orb__rings") && el.getBoundingClientRect().right > limit && getComputedStyle(el).position !== "fixed")
          .slice(0, 5)
          .map((el) => `${el.tagName.toLowerCase()}.${el.className}`);
      });
      expect(wide).toEqual([]);
      const results = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"]).analyze();
      expect(results.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(" ")).slice(0, 3).join(", ")}`)).toEqual([]);
    });

    test("screenshot", async ({ page }, info) => {
      await page.emulateMedia({ reducedMotion: "reduce" });
      await open(page, c.path);
      await page.waitForTimeout(500);
      await page.screenshot({ path: `tests/screens/landing-${c.lang}-${info.project.name}.png`, fullPage: true, scale: "css" });
    });
  });
}

for (const c of [
  { lang: "en", path: "/comparison/", html: "en", expected: /^ulams — Compare learning platforms/ },
  { lang: "pl", path: "/pl/comparison/", html: "pl", expected: /^ulams — Porównaj platformy edukacyjne/ },
  { lang: "zh", path: "/zh/comparison/", html: "zh-Hans", expected: /^ulams — 对比学习平台/ },
]) {
  test(`comparison ${c.lang}: canonical metadata and accessible sourced matrix`, async ({ page }) => {
    const response = await page.goto(`${base}${c.path}`);
    expect(response?.status()).toBe(200);
    await expect(page).toHaveTitle(c.expected);
    await expect(page.locator("html")).toHaveAttribute("lang", c.html);
    await expect(page.locator("h1")).toHaveCount(1);
    await expect(page.locator("main table caption")).toHaveCount(2);
    await expect(page.locator('main [role="region"][tabindex="0"]')).toHaveCount(2);
    await expect(page.locator('link[rel="canonical"]')).toHaveAttribute("href", `${base}${c.path}`);
    await expect(page.locator('meta[name="description"]')).toHaveAttribute("content", /.+/);
    await expect(page.locator('meta[name="robots"]')).toHaveAttribute("content", "index, follow, max-image-preview:large");
    await expect(page.locator('meta[property="og:url"]')).toHaveAttribute("content", `${base}${c.path}`);
    await expect(page.locator('meta[property="og:image:type"]')).toHaveAttribute("content", "image/png");
    const alternates = await page.locator('link[rel="alternate"][hreflang]').evaluateAll((els) => els.map((e) => [e.getAttribute("hreflang"), e.getAttribute("href")]));
    expect(alternates).toEqual([["en", `${base}/comparison/`], ["pl", `${base}/pl/comparison/`], ["zh-Hans", `${base}/zh/comparison/`], ["x-default", `${base}/comparison/`]]);
    await expect(page.locator('meta[name="twitter:title"]')).toHaveAttribute("content", c.expected);
    const jsonLd = await page.locator('head script[type="application/ld+json"]').textContent();
    expect(JSON.parse(jsonLd!)).toEqual(expect.arrayContaining([expect.objectContaining({ "@type": "Organization" }), expect.objectContaining({ "@type": "WebSite" }), expect.objectContaining({ "@type": "CollectionPage" }), expect.objectContaining({ "@type": "BreadcrumbList" })]));
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBeLessThanOrEqual(1);
    await page.emulateMedia({ reducedMotion: "reduce" });
    const axe = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"]).analyze();
    expect(axe.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(" ")).slice(0, 3).join(", ")}`)).toEqual([]);
  });
}

test("the contact address is assembled in the browser and is absent from the HTML", async ({ page, request }) => {
  const html = await (await request.get(`${base}/pl/`)).text();
  expect(html).not.toContain("hi@ulams.app");
  expect(html).not.toContain("mailto:");
  await page.goto(`${base}/pl/`);
  const links = page.locator("a.u-mail");
  await expect(links).toHaveCount(2);
  await expect(links.first()).toHaveAttribute("href", "mailto:hi@ulams.app");
  await expect(links.first()).toHaveText("hi@ulams.app");
});

test("platform robots and sitemap list only the localized public page URLs", async ({ request }) => {
  const robots = await (await request.get(`${base}/robots.txt`)).text();
  expect(robots).toContain(`Sitemap: ${base}/sitemap.xml`);
  const xml = await (await request.get(`${base}/sitemap.xml`)).text();
  const urls = [...xml.matchAll(/<loc>(.*?)<\/loc>/g)].map((match) => match[1]);
  expect(urls).toEqual([
    `${base}/`, `${base}/pl/`, `${base}/zh/`,
    `${base}/comparison/`, `${base}/pl/comparison/`, `${base}/zh/comparison/`,
    `${base}/faq/`, `${base}/pl/faq/`, `${base}/zh/faq/`,
    `${base}/course-generation/`, `${base}/pl/course-generation/`, `${base}/zh/course-generation/`,
    `${base}/course-generation/microdosing-study/`, `${base}/pl/course-generation/microdosing-study/`, `${base}/zh/course-generation/microdosing-study/`,
    `${base}/course-generation/stablecoin-report/`, `${base}/pl/course-generation/stablecoin-report/`, `${base}/zh/course-generation/stablecoin-report/`,
  ]);
  expect(xml.match(/hreflang="x-default"/g)).toHaveLength(18);
});

test("the FAQ page lists every question once, in every language, with one FAQPage", async ({ page }) => {
  for (const path of ["/faq/", "/pl/faq/", "/zh/faq/"]) {
    await page.goto(`${base}${path}`);
    await expect(page.locator("h1")).toHaveCount(1);
    await expect(page.locator(".u-faq__item")).toHaveCount(32);
    await expect(page.locator(".u-faq details")).toHaveCount(0);
    await expect(page.locator(".u-faq h3")).toHaveCount(32);
    const ld = await page.locator('script[type="application/ld+json"]').allTextContents();
    expect(ld.filter((json) => json.includes('"FAQPage"'))).toHaveLength(1);
    await expect(page.locator('footer a[aria-current="page"], footer a').filter({ hasText: "FAQ" })).toHaveAttribute("href", path);
  }
  await page.emulateMedia({ reducedMotion: "reduce" });
  const axe = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"]).analyze();
  expect(axe.violations.map((v) => v.id)).toEqual([]);
});

test("zh uses the system CJK font stack and no synthetic italics; English keeps its fonts", async ({ page }) => {
  await open(page, "/zh/");
  const stack = await page.evaluate(() => getComputedStyle(document.body).fontFamily);
  for (const font of ["PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", "Noto Sans SC"]) expect(stack).toContain(font);
  expect(await page.evaluate(() => getComputedStyle(document.querySelector(".u-hero__accent")!).fontStyle)).toBe("normal");
  await open(page, "/");
  expect(await page.evaluate(() => getComputedStyle(document.body).fontFamily)).not.toContain("PingFang SC");
});

test("a tenant host has no language pages", async ({ request }) => {
  expect((await request.get(`http://coffee.app.localhost:${port}/pl/`)).status()).toBe(404);
  expect((await request.get(`http://coffee.app.localhost:${port}/zh/`)).status()).toBe(404);
  expect((await request.get(`http://coffee.app.localhost:${port}/comparison/`)).status()).toBe(404);
  expect((await request.get(`http://coffee.app.localhost:${port}/course-generation/`)).status()).toBe(404);
  expect((await request.get(`http://coffee.app.localhost:${port}/robots.txt`)).status()).toBe(404);
  expect((await request.get(`http://coffee.app.localhost:${port}/sitemap.xml`)).status()).toBe(404);
});
