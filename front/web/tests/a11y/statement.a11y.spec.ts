import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * The accessibility statement (`/accessibility`, European Accessibility Act) against the mock tenant API: axe on
 * desktop and a 360 px phone in a light and a dark theme, the tenant's name and contact filled into the template,
 * the notice when the academy has not published a contact, the Polish interface, the link from the site footer,
 * and a page that needs no session.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const host = (slug: string) => `http://${slug}.app.localhost:${web}`;
const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];
const THEMES: Array<[string, string]> = [
  ["light", "coffee"],
  ["dark", "nightsky"],
];

test.use({ contextOptions: { reducedMotion: "reduce" } });

async function scan(page: Page) {
  const results = await new AxeBuilder({ page }).withTags(TAGS).exclude("iframe").analyze();
  return results.violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.slice(0, 3).map((n) => `${n.target.join(" ")} - ${n.any[0]?.message ?? n.failureSummary ?? ""}`).join(" | ")}`);
}

for (const [theme, slug] of THEMES) {
  test(`axe ${theme}: the statement, with no contact published`, async ({ page }) => {
    const response = await page.goto(`${host(slug)}/accessibility`, { waitUntil: "load" });
    expect(response?.status()).toBe(200);
    await expect(page.getByRole("heading", { level: 1, name: "Accessibility statement" })).toBeVisible();
    await expect(page.getByText("has not published a contact address for accessibility yet")).toBeVisible();
    const violations = await scan(page);
    expect(violations, violations.join("\n")).toEqual([]);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), "reflow (1.4.10)").toBe(true);
  });
}

test("the template names the academy, the target and the limits, and says the operator owns the facts", async ({ page }) => {
  await page.goto(`${host("coffee")}/accessibility`);
  await expect(page.locator("html")).toHaveAttribute("lang", "en");
  await expect(page.getByText(/the service provider of this academy under the European Accessibility Act/)).toBeVisible();
  await expect(page.getByText(/The Coffee Atlas owns the facts on this page/)).toBeVisible();
  await expect(page.getByText(/WCAG 2.2 level AA/)).toBeVisible();
  await expect(page.getByText(/EN 301 549 version 3.2.1/)).toBeVisible();
  const headings = await page.locator("main h2").allTextContents();
  expect(headings).toEqual(["Who is responsible", "What we aim for", "How it is tested", "What helps you", "Known limitations", "Tell us about a barrier", "If you are not satisfied with our reply"]);
  await expect(page).toHaveTitle("Accessibility statement — The Coffee Atlas");
});

test("the operator's contact, notes and date come from the public settings, in the tenant's language", async ({ page }) => {
  await page.goto(`${host("polski")}/accessibility`);
  await expect(page.locator("html")).toHaveAttribute("lang", "pl");
  await expect(page.getByRole("heading", { level: 1, name: "Deklaracja dostępności" })).toBeVisible();
  await expect(page.getByRole("link", { name: "dostepnosc@coffee.test" })).toHaveAttribute("href", "mailto:dostepnosc@coffee.test");
  await expect(page.getByRole("heading", { name: "Uwagi od akademii" })).toBeVisible();
  await expect(page.getByText("Nagrania z kursu o kawie nie mają jeszcze napisów.")).toBeVisible();
  await expect(page.getByText("Ostatnia aktualizacja: 1 października 2026.")).toBeVisible();
  const violations = await scan(page);
  expect(violations, violations.join("\n")).toEqual([]);
});

test("the site footer links to the statement and the link works", async ({ page }) => {
  await page.goto(`${host("coffee")}/`);
  const link = page.locator("footer").getByRole("link", { name: "Accessibility" });
  await expect(link).toHaveAttribute("href", "/accessibility");
  await link.click();
  await expect(page).toHaveURL(/\/accessibility$/);
  await expect(page.getByRole("heading", { level: 1, name: "Accessibility statement" })).toBeVisible();
});

test("the statement is public: no session cookie is set", async ({ page, context }) => {
  await page.goto(`${host("coffee")}/accessibility`);
  expect((await context.cookies()).map((c) => c.name)).toEqual([]);
});

test("the platform host has no statement of its own", async ({ page }) => {
  const response = await page.goto(`http://app.localhost:${web}/accessibility`);
  expect(response?.status()).toBe(404);
});
