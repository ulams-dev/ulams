import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";
import { parseLandingStatus } from "../../src/lib/landing-status.ts";

/**
 * The platform landing in Polish (/pl/) and Simplified Chinese (/zh/): language and meta, hreflang,
 * the header switcher (keeps the section anchor), no horizontal overflow on desktop and at 360 px,
 * axe (WCAG 2.2 AA), the display-status switch, no extra scripts, and a screenshot per language and
 * viewport under tests/screens/landing-<lang>-<desktop|phone>.png. Needs the API for the demo cards.
 */
const port = process.env.WEB_BASE_PORT ?? "4321";
const base = `http://app.localhost:${port}`;
const landingMode = parseLandingStatus(process.env.ULAMS_LANDING_STATUS);

const CASES = [
  { lang: "pl", html: "pl", path: "/pl/", h1: "wierne swoim źródłom", title: /^ulams — otwarty LMS/, label: "PL", skip: "Przejdź do treści", coming: "Wkrótce", demos: "Dema" },
  { lang: "zh", html: "zh-Hans", path: "/zh/", h1: "忠于来源", title: /^ulams — 开放的 AI 原生 LMS/, label: "中文", skip: "跳到正文", coming: "即将推出", demos: "演示" },
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
      const alternates = await page.locator('link[rel="alternate"][hreflang]').evaluateAll((els) => els.map((e) => [e.getAttribute("hreflang"), e.getAttribute("href")]));
      expect(alternates).toEqual([
        ["en", `${base}/`],
        ["pl", `${base}/pl/`],
        ["zh-Hans", `${base}/zh/`],
        ["x-default", `${base}/`],
      ]);
    });

    test("header: six links at most, a separate language switcher with the current language marked", async ({ page }) => {
      await open(page, c.path);
      await expect(page.locator("header nav.u-nav li")).toHaveCount(6);
      const switcher = page.locator("header nav.u-lang");
      await expect(switcher).toBeVisible();
      await expect(switcher.locator("a")).toHaveCount(3);
      await expect(switcher.locator('a[aria-current="true"]')).toHaveText(c.label);
      await expect(switcher.locator("a")).toHaveText(["EN", "PL", "中文"]);
      // the brand stays in the language
      await expect(page.locator("a.u-brand")).toHaveAttribute("href", c.path);
    });

    test("the switcher keeps the section anchor", async ({ page }) => {
      await page.goto(`${base}${c.path}#compare`);
      await expect(page.locator("#compare")).toHaveCount(1);
      const other = c.lang === "pl" ? "zh" : "pl";
      const link = page.locator(`header nav.u-lang a[hreflang="${other === "zh" ? "zh-Hans" : "pl"}"]`);
      await expect(link).toHaveAttribute("href", `/${other}/#compare`);
      await link.click();
      await expect(page).toHaveURL(`${base}/${other}/#compare`);
      await expect(page.locator("html")).toHaveAttribute("lang", other === "zh" ? "zh-Hans" : "pl");
      // and back to English
      await page.locator('header nav.u-lang a[hreflang="en"]').click();
      await expect(page).toHaveURL(`${base}/#compare`);
      await expect(page.locator("html")).toHaveAttribute("lang", "en");
    });

    test("translated sections: demos, comparison and the display status", async ({ page }) => {
      await open(page, c.path);
      await expect(page.locator("#demos li")).toHaveCount(6);
      await expect(page.locator("#demos")).not.toContainText("Open as learner");
      await expect(page.locator("#compare table").first()).toBeAttached();
      await expect(page.locator("#compare")).not.toContainText("Not documented");
      await expect(page.locator("#compare .u-compare__mark").first()).toBeAttached();
      const coming = await page.locator(".u-status--coming").count();
      if (landingMode === "actual") {
        expect(coming, "actual mode shows the roadmap badges").toBeGreaterThan(2);
        await expect(page.locator("main")).toContainText(c.coming);
      } else {
        expect(coming, "final mode shows no roadmap badge").toBe(0);
        await expect(page.locator("main")).not.toContainText(c.coming);
      }
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
          .filter((el) => !el.closest(".u-compare__scroll, .u-stage, .u-wf__panels, pre, .u-orb__rings") && el.getBoundingClientRect().right > limit && getComputedStyle(el).position !== "fixed")
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
});
