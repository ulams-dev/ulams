import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * WCAG 2.2 AA (axe-core) over the learner pages of the reference frontend, against the mock
 * tenant API (see playwright.a11y.config.ts). Two themes (coffee is light, nightsky is dark) at
 * desktop and 360 px phone width (the Playwright projects), reduced motion on.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const host = (slug: string) => (slug ? `http://${slug}.app.localhost:${web}` : `http://app.localhost:${web}`);

const COURSE = 1;
const READING = 3;
const QUIZ = 900;

const PAGES: Array<[string, string]> = [
  ["home", "/"],
  ["course page", `/courses/${COURSE}`],
  ["lesson player", `/learn/${COURSE}/${READING}`],
  ["quiz intro", `/learn/${COURSE}/${QUIZ}`],
  ["finish", `/learn/${COURSE}/finish`],
  ["account", "/account"],
  ["login", "/login"],
  ["not found", "/nope"],
];
const THEMES: Array<[string, string]> = [
  ["light", "coffee"],
  ["dark", "nightsky"],
];

test.use({ contextOptions: { reducedMotion: "reduce" } });

const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];

async function revealAll(page: Page) {
  await page.evaluate(async () => {
    for (let y = 0; y < document.body.scrollHeight; y += 600) {
      window.scrollTo({ top: y, behavior: "instant" });
      await new Promise((r) => setTimeout(r, 40));
    }
    window.scrollTo({ top: 0, behavior: "instant" });
  });
  await page.waitForTimeout(500);
}

async function scan(page: Page) {
  const results = await new AxeBuilder({ page }).withTags(TAGS).exclude("iframe").analyze();
  return results.violations.map(
    (v) => `${v.id} (${v.impact}): ${v.nodes.slice(0, 3).map((n) => `${n.target.join(" ")} - ${n.any[0]?.message ?? n.failureSummary ?? ""}`).join(" | ")}`
  );
}

for (const [theme, slug] of THEMES) {
  for (const [name, path] of PAGES) {
    test(`axe ${theme}: ${name}`, async ({ page }) => {
      const response = await page.goto(`${host(slug)}${path}`, { waitUntil: "load" });
      expect(response?.status(), `HTTP status of ${path}`).toBe(path === "/nope" ? 404 : 200);
      await revealAll(page);
      const violations = await scan(page);
      expect(violations, violations.join("\n")).toEqual([]);
    });
  }

  test(`axe ${theme}: quiz question screen`, async ({ page }) => {
    await page.goto(`${host(slug)}/learn/${COURSE}/${QUIZ}`);
    await page.click("[data-start]");
    await expect(page.locator(".u-quiz__q legend")).toBeVisible();
    const violations = await scan(page);
    expect(violations, violations.join("\n")).toEqual([]);
  });
}

test("axe: platform landing", async ({ page }) => {
  await page.goto(host(""), { waitUntil: "load" });
  await revealAll(page);
  const violations = await scan(page);
  expect(violations, violations.join("\n")).toEqual([]);
});
