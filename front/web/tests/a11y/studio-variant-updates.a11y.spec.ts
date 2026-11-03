import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * Update proposals of a course with a variant (ADR 0192, CV-5; WCAG 2.2 AA, axe-core) against the mock tenant API, at
 * desktop and 360 px phone width (the Playwright projects) with reduced motion on: the course badge in the list and on the
 * review screen, the "Versions of this update" navigation, "Accept in all versions" with its status line and the note on a
 * reused change, all with the keyboard, in English, Polish and Simplified Chinese.
 *
 * Mock data (tests/mock-api/server.mjs): a proposal group with the base proposal (1) and the variant "Expert" (2). Their
 * paragraph 1 is the same change, so accepting it in all versions accepts the twin in the other proposal.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const host = (slug: string) => `http://${slug}.app.localhost:${web}`;
const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];
const SESSION = `01s${"0".repeat(22)}2`;
const BASE = `01p${"0".repeat(22)}2`;
const VARIANT = `01p${"0".repeat(22)}3`;

test.use({ contextOptions: { reducedMotion: "reduce" } });

/** The mock keeps the group per tenant: each project and each repeat of a test gets a tenant of its own. */
const TENANTS: Record<string, string[]> = { desktop: ["coffee", "zhongwen", "nocpl"], phone: ["nightsky", "noczh", "nocpl"] };
const slugOf = (info: { project: { name: string }; repeatEachIndex: number }) => {
  const list = TENANTS[info.project.name] ?? TENANTS.desktop!;
  return list[info.repeatEachIndex % list.length]!;
};

async function scan(page: Page) {
  const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();
  return results.violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.slice(0, 3).map((n) => `${n.target.join(" ")} - ${n.any[0]?.message ?? n.failureSummary ?? ""}`).join(" | ")}`);
}

const sideways = (page: Page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

async function reset(slug: string) {
  const response = await fetch(`http://127.0.0.1:${process.env.MOCK_API_PORT ?? "4390"}/__mock/groups/reset`, { method: "POST", headers: { "x-ulams-api-host": `${slug}.localhost` } });
  expect(response.ok).toBe(true);
}

async function review(page: Page, slug: string, proposal: string, lang = "en") {
  await reset(slug);
  await page.goto(`${host(slug)}/studio/s/${SESSION}/updates/${proposal}${lang === "en" ? "" : `?lang=${lang}`}`, { waitUntil: "load" });
  await expect(page.locator("[data-update-item]").first()).toBeVisible();
}

const item = (page: Page, n: number, proposal = VARIANT) => page.locator(`[data-update-item="01${proposal === BASE ? "i" : "j"}${"0".repeat(22)}${n}"]`);

/** Presses Tab until the focus is on the element, so the test follows the real keyboard order; gives up after `max` presses. */
async function tabTo(page: Page, selector: string, max = 60) {
  for (let i = 0; i < max; i++) {
    await page.keyboard.press("Tab");
    if (await page.evaluate((sel) => document.activeElement?.matches(sel) ?? false, selector)) return;
  }
  throw new Error(`Tab never reached ${selector}`);
}

test("the update list names the course of each proposal in words", async ({ page }, info) => {
  const slug = slugOf(info);
  await reset(slug);
  await page.goto(`${host(slug)}/studio/s/${SESSION}/updates`, { waitUntil: "load" });
  const badges = page.locator("[data-updates-table] [data-course-badge]");
  await expect(badges).toHaveText(["Expert", "Base course"]);
  expect(await scan(page), "list").toEqual([]);
  expect(await sideways(page), "list scrolls sideways").toBeLessThanOrEqual(1);
});

test("the review screen shows the course, the versions of the update and the reused note", async ({ page }, info) => {
  await review(page, slugOf(info), VARIANT);
  await expect(page.locator("[data-header] [data-course-badge]")).toHaveText("Expert");
  const nav = page.getByRole("navigation", { name: "Versions of this update" });
  await expect(nav.getByRole("link")).toHaveCount(2);
  await expect(nav.locator('[aria-current="page"]')).toContainText("Expert");
  await expect(nav.locator('[aria-current="page"]')).toContainText("Ready to review");
  await expect(nav.locator('[aria-current="page"]')).toContainText("0 decided, 2 undecided");
  await expect(nav.getByRole("link").first()).toContainText("Base course");
  await expect(item(page, 1)).toContainText("Same change as in the base course; no extra analysis was needed.");
  await expect(item(page, 3)).not.toContainText("Same change as in the base course");
  await expect(page.getByRole("button", { name: "Accept in all versions" })).toHaveCount(2);
  expect(await scan(page), "variant review").toEqual([]);
  expect(await sideways(page), "review scrolls sideways").toBeLessThanOrEqual(1);
});

test("the keyboard moves through the versions: Tab to the base entry, Enter opens it", async ({ page }, info) => {
  await review(page, slugOf(info), VARIANT);
  await tabTo(page, '[data-group-nav] a:not([aria-current])');
  await expect(page.getByRole("link", { name: /Base course/ })).toBeFocused();
  const outline = await page.evaluate(() => {
    const style = getComputedStyle(document.activeElement as HTMLElement);
    return { style: style.outlineStyle, width: parseFloat(style.outlineWidth) };
  });
  expect(outline.style).not.toBe("none");
  expect(outline.width).toBeGreaterThanOrEqual(2);
  await page.keyboard.press("Enter");
  await expect(page).toHaveURL(new RegExp(`/updates/${BASE}$`));
  await expect(page.locator("[data-header] [data-course-badge]")).toHaveText("Base course");
  await expect(page.locator('[data-group-nav] [aria-current="page"]')).toContainText("Base course");
  await expect(item(page, 2, BASE)).toBeVisible();
  expect(await scan(page), "base review").toEqual([]);
});

test("Accept in all versions works with the keyboard, says where it accepted and updates the other version", async ({ page }, info) => {
  await review(page, slugOf(info), VARIANT);
  await tabTo(page, `[data-update-item="01j${"0".repeat(22)}1"] [data-accept-in-group]`);
  await expect(page.getByRole("button", { name: "Accept in all versions" }).first()).toBeFocused();
  await page.keyboard.press("Enter");

  const status = page.locator("[data-notices] [role='status']");
  await expect(status).toHaveText("Accepted here and in 1 other version");
  await expect(item(page, 1)).toHaveAttribute("data-status", "accepted");
  await expect(item(page, 1).locator("[data-focus-target]")).toBeFocused();
  // the twin in the base proposal is decided too, so the navigation shows it
  await expect(page.locator('[data-group-nav] li').filter({ hasText: "Base course" })).toContainText("1 decided, 1 undecided");
  await expect(item(page, 1).getByRole("button", { name: "Accept in all versions" })).toBeHidden();
  expect(await scan(page), "after accepting in all versions").toEqual([]);
  expect(await sideways(page), "scrolls sideways").toBeLessThanOrEqual(1);

  // a second item that has no twin: only this proposal is accepted, and the status says so
  await item(page, 3).getByRole("button", { name: "Accept in all versions" }).click();
  await expect(status).toContainText("Accepted here");
  await expect(item(page, 3)).toHaveAttribute("data-status", "accepted");
});

test("the base proposal offers the action on its shared change too", async ({ page }, info) => {
  await review(page, slugOf(info), BASE);
  await expect(item(page, 1, BASE).getByRole("button", { name: "Accept in all versions" })).toBeVisible();
  await expect(item(page, 1, BASE)).not.toContainText("Same change as in the base course");
  await item(page, 1, BASE).getByRole("button", { name: "Accept in all versions" }).click();
  await expect(page.locator("[data-notices] [role='status']")).toHaveText("Accepted here and in 1 other version");
  await expect(page.locator('[data-group-nav] li').filter({ hasText: "Expert" })).toContainText("1 decided, 1 undecided");
});

test("the BFF forwards accept-in-group and refuses look-alikes", async ({ request }, info) => {
  const slug = slugOf(info);
  await reset(slug);
  const headers = { Origin: host(slug) };
  const item1 = `01i${"0".repeat(22)}1`;
  const ok = await request.post(`${host(slug)}/studio/api/living-course/proposals/${BASE}/items/${item1}/accept-in-group`, { headers, data: {} });
  expect(ok.status()).toBe(200);
  expect((await ok.json()).data.accepted).toBe(1);
  const wrongLevel = await request.post(`${host(slug)}/studio/api/living-course/proposals/${BASE}/accept-in-group`, { headers, data: {} });
  expect(wrongLevel.status()).toBe(404);
  const get = await request.get(`${host(slug)}/studio/api/living-course/proposals/${BASE}/items/${item1}/accept-in-group`);
  expect(get.status()).toBeGreaterThanOrEqual(400);
});

for (const [lang, name, base, action, status] of [
  ["pl", "Wersje tej aktualizacji", "Kurs bazowy", "Zaakceptuj we wszystkich wersjach", "Zaakceptowano tutaj i w 1 innej wersji"],
  ["zh", "此更新的各个版本", "基础课程", "在所有版本中接受", "已在此处及另外 1 个版本中接受"],
] as const) {
  test(`${lang}: the navigation, the group action and its status are translated, marked with their language and pass axe`, async ({ page }, info) => {
    await review(page, slugOf(info), VARIANT, lang);
    const nav = page.getByRole("navigation", { name });
    await expect(nav).toBeVisible();
    await expect(nav.getByRole("link").first()).toContainText(base);
    await expect(nav).toHaveAttribute("lang", lang === "zh" ? "zh-Hans" : "pl");
    expect(await scan(page), "review").toEqual([]);
    expect(await sideways(page), "scrolls sideways").toBeLessThanOrEqual(1);
    await page.getByRole("button", { name: action }).first().click();
    await expect(page.locator("[data-notices] [role='status']")).toHaveText(status);
    expect(await scan(page), "after accepting").toEqual([]);
  });
}
