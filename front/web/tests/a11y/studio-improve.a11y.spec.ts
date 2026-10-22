import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * "Propose an improvement" (ADR 0181, WCAG 2.2 AA, axe-core) against the mock tenant API, at desktop and 360 px
 * phone width (the Playwright projects) with reduced motion on: the button and side panel of the Insights page with
 * the evidence and the estimate, the confirmation, the refusals, the existing-proposal state, keyboard operation, the
 * review screen of the proposal with its evidence card, English, Polish and Simplified Chinese, and a dark colour
 * scheme (the studio has one theme and must not break under it).
 *
 * Mock element keys (tests/mock-api/server.mjs): ...fav succeeds, ...fa2 is refused at the confirmation by the daily
 * limit, ...fa3 is refused at once because AI is off. Desktop and phone share one mock API, each with its own tenant.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const host = (slug: string) => `http://${slug}.app.localhost:${web}`;
const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];
const QUESTION = "01arz3ndektsv4rrffq69g5fav";
const LIMIT = "01arz3ndektsv4rrffq69g5fa2";
const OFF = "01arz3ndektsv4rrffq69g5fa3";

test.use({ contextOptions: { reducedMotion: "reduce" } });

/**
 * Each project, and each repeat of a test (`--repeat-each`), gets a tenant of its own: the mock keeps the proposal per
 * tenant, so tests that create one never meet another run's.
 */
const TENANTS: Record<string, string[]> = { desktop: ["coffee", "zhongwen", "nocpl"], phone: ["nightsky", "noczh", "nocpl"] };
const slugOf = (info: { project: { name: string }; repeatEachIndex: number }) => {
  const list = TENANTS[info.project.name] ?? TENANTS.desktop!;
  return list[info.repeatEachIndex % list.length]!;
};

async function scan(page: Page) {
  const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();
  return results.violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.slice(0, 3).map((n) => `${n.target.join(" ")} - ${n.any[0]?.message ?? n.failureSummary ?? ""}`).join(" | ")}`);
}

const noSidewaysScroll = (page: Page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

async function reset(slug: string) {
  const response = await fetch(`http://127.0.0.1:${process.env.MOCK_API_PORT ?? "4390"}/__mock/improve/reset`, { method: "POST", headers: { "x-ulams-api-host": `${slug}.localhost` } });
  expect(response.ok).toBe(true);
}

async function open(page: Page, slug: string, lang = "en") {
  await reset(slug);
  await page.goto(`${host(slug)}/studio/courses/1/insights?lang=${lang}`, { waitUntil: "load" });
  await expect(page.locator(`[data-row="${QUESTION}"]`)).toBeVisible();
}

const buttonOf = (page: Page, key: string) => page.locator(`[data-row="${key}"] [data-improve-button]`);
const panel = (page: Page) => page.locator("[data-improve]");

test("the button opens a panel with the evidence and the estimate; nothing is created yet", async ({ page }, info) => {
  await open(page, slugOf(info));
  await expect(page.locator("[data-improve-button]")).toHaveCount(3);
  await expect(page.locator('[data-row="question:3"] [data-improve-button]')).toHaveCount(0);
  await expect(buttonOf(page, QUESTION)).toHaveAccessibleName(/Propose an improvement – 2\.1 Question 1/);

  await buttonOf(page, QUESTION).click();

  const card = panel(page).locator("[data-evidence]");
  await expect(card).toBeVisible();
  await expect(panel(page).getByRole("heading", { level: 3 })).toHaveText("Improve “2.1 Question 1: Which statement matches the source?”");
  await expect(panel(page).getByRole("heading", { level: 3 })).toBeFocused();
  await expect(card).toContainText("Results of 42 learners in the last 30 days");
  await expect(card).toContainText("38%");
  await expect(card).toContainText("Brew with boiling water.");
  await expect(panel(page)).toContainText("Estimated cost: about $0.12");
  await expect(buttonOf(page, QUESTION)).toHaveAttribute("aria-expanded", "true");
  expect(await scan(page), "estimate").toEqual([]);
  expect(await noSidewaysScroll(page), "page scrolls sideways").toBeLessThanOrEqual(1);
});

test("the whole flow works with the keyboard: open, confirm, open the review, decide", async ({ page }, info) => {
  await open(page, slugOf(info));
  await buttonOf(page, QUESTION).focus();
  await page.keyboard.press("Enter");
  await expect(panel(page).locator("[data-evidence]")).toBeVisible();

  // from the heading, Tab passes the scrollable table of wrong answers and reaches "Create the proposal"
  await page.keyboard.press("Tab");
  await expect(panel(page).locator('[role="region"][tabindex="0"]')).toBeFocused();
  await page.keyboard.press("Tab");
  await expect(page.getByRole("button", { name: "Create the proposal" })).toBeFocused();
  const outline = await page.evaluate(() => {
    const style = getComputedStyle(document.activeElement as HTMLElement);
    return { style: style.outlineStyle, width: parseFloat(style.outlineWidth) };
  });
  expect(outline.style).not.toBe("none");
  expect(outline.width).toBeGreaterThanOrEqual(2);
  await page.keyboard.press("Enter");

  const result = panel(page).locator("[data-improve-result]");
  await expect(result).toHaveText("Proposal 3 was created (ready for your review).");
  await expect(result).toBeFocused();
  await expect(page.locator("[data-announcer]")).toContainText("Proposal 3 was created");
  expect(await scan(page), "created").toEqual([]);

  await panel(page).getByRole("link", { name: "Review the proposal" }).click();
  await expect(page.locator("h1")).toHaveText("Review an update");
  await expect(page.locator("[data-header] h2")).toHaveText("Improvement from learner insights");
  const card = page.locator("[data-header] [data-evidence]");
  await expect(card).toBeVisible();
  await expect(card.getByRole("heading", { level: 3 })).toHaveText("Why this was proposed");
  await expect(card).toContainText("Results of 42 learners in the last 30 days");
  // no source changes on this screen, and no talk of a source revision
  await expect(page.locator("[data-source-changes]")).toBeHidden();
  await expect(page.locator("main")).not.toContainText("source revision");
  await expect(page.locator('[data-update-item]')).toHaveCount(1);
  expect(await scan(page), "review").toEqual([]);
  expect(await noSidewaysScroll(page), "review scrolls sideways").toBeLessThanOrEqual(1);

  await page.locator("[data-update-item] button", { hasText: "Accept" }).first().click();
  await expect(page.locator("[data-apply-summary]")).toHaveText("1 accepted · 0 undecided · 0 rejected");
  await page.getByRole("button", { name: "Reject all" }).click();
  await expect(page.locator(".st-confirm")).toContainText("Reject the whole improvement?");
  await expect(page.locator(".st-confirm")).not.toContainText("revision");
  expect(await scan(page), "reject confirmation").toEqual([]);
});

test("asking again for the same section opens the proposal that is already there", async ({ page }, info) => {
  const slug = slugOf(info);
  await open(page, slug);
  await buttonOf(page, QUESTION).click();
  await page.getByRole("button", { name: "Create the proposal" }).click();
  await expect(panel(page).locator("[data-improve-result]")).toBeVisible();

  await page.reload();
  await expect(page.locator(`[data-row="${QUESTION}"]`)).toBeVisible();
  await buttonOf(page, QUESTION).click();

  await expect(panel(page).locator("[data-improve-result]")).toHaveText("There is already an open proposal for this section (proposal 3).");
  await expect(panel(page).getByRole("link", { name: "Open it" })).toBeVisible();
  expect(await scan(page)).toEqual([]);
});

test("cancelling closes the panel and returns the focus to the button", async ({ page }, info) => {
  await open(page, slugOf(info));
  await buttonOf(page, QUESTION).click();
  await expect(panel(page).locator("[data-evidence]")).toBeVisible();

  await page.getByRole("button", { name: "Cancel" }).click();

  await expect(panel(page).locator("*")).toHaveCount(0);
  await expect(buttonOf(page, QUESTION)).toBeFocused();
  await expect(buttonOf(page, QUESTION)).toHaveAttribute("aria-expanded", "false");
});

test("refusals say why, as alerts, and the page stays usable", async ({ page }, info) => {
  await open(page, slugOf(info));

  await buttonOf(page, OFF).click();
  await expect(panel(page).locator('[role="alert"]')).toContainText("AI is switched off on this installation");
  expect(await scan(page), "ai off").toEqual([]);
  await panel(page).getByRole("button", { name: "Close" }).click();
  await expect(buttonOf(page, OFF)).toBeFocused();

  await buttonOf(page, LIMIT).click();
  await expect(panel(page).locator("[data-evidence]")).toBeVisible();
  await page.getByRole("button", { name: "Create the proposal" }).click();
  await expect(panel(page).locator('[role="alert"]')).toContainText("already has its limit of improvement proposals for today");
  // the estimate stays, the button is usable again
  await expect(page.getByRole("button", { name: "Create the proposal" })).toBeEnabled();
  expect(await scan(page), "daily limit").toEqual([]);
});

test("the BFF forwards the improve call and nothing else that writes", async ({ request }, info) => {
  const slug = slugOf(info);
  await reset(slug);
  const headers = { Origin: host(slug) };
  const estimate = await request.post(`${host(slug)}/studio/api/learner-insights/courses/1/elements/${QUESTION}/improve`, { headers, data: {} });
  expect(estimate.status()).toBe(200);
  expect((await estimate.json()).data.state).toBe("estimate");
  const encoded = await request.post(`${host(slug)}/studio/api/learner-insights/courses/1/elements/question%3A3/improve`, { headers, data: {} });
  expect(encoded.status()).toBe(404); // allowed through, and the API answers that it is not a Course Builder element
  expect((await encoded.json()).code).toBe("not_builder_element");
  const backfill = await request.post(`${host(slug)}/studio/api/learner-insights/backfill`, { headers, data: {} });
  expect(backfill.status()).toBe(404);
  const wrongShape = await request.post(`${host(slug)}/studio/api/learner-insights/courses/1/elements/short/improve`, { headers, data: {} });
  expect(wrongShape.status()).toBe(404);
});

for (const [lang, button, title, review, htmlLang] of [
  ["pl", "Zaproponuj poprawkę", "Dlaczego to zaproponowano", "Poprawka na podstawie wyników kursantów", "pl"],
  ["zh", "提出改进建议", "提出此建议的原因", "来自学员洞察的改进建议", "zh-Hans"],
] as const) {
  test(`${lang}: the panel and the review card are translated, marked with their language and pass axe`, async ({ page }, info) => {
    await open(page, slugOf(info), lang);
    await expect(buttonOf(page, QUESTION)).toContainText(button);
    await buttonOf(page, QUESTION).click();
    const card = panel(page).locator("[data-evidence]");
    await expect(card.getByRole("heading", { level: 4 }).first()).toHaveText(title);
    await expect(card).toHaveAttribute("lang", htmlLang);
    expect(await scan(page), "panel").toEqual([]);
    expect(await noSidewaysScroll(page), "page scrolls sideways").toBeLessThanOrEqual(1);

    await page.getByRole("button", { name: lang === "pl" ? "Utwórz propozycję" : "创建建议" }).click();
    await panel(page).getByRole("link").first().click();
    await expect(page.locator("[data-header] h2").first()).toHaveText(review);
    await expect(page.locator("[data-header] h2").first()).toHaveAttribute("lang", htmlLang);
    await expect(page.locator("[data-header] [data-evidence] h3").first()).toHaveText(title);
    expect(await scan(page), "review").toEqual([]);
  });
}

test("a dark colour scheme does not break the page", async ({ browser }, info) => {
  const context = await browser.newContext({ colorScheme: "dark", reducedMotion: "reduce", viewport: info.project.use.viewport ?? undefined });
  const page = await context.newPage();
  await open(page, slugOf(info));
  await buttonOf(page, QUESTION).click();
  await expect(panel(page).locator("[data-evidence]")).toBeVisible();
  expect(await scan(page)).toEqual([]);
  await context.close();
});
