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

/**
 * "Extra help for you" (M-UI1). The mock API offers two cards on topic 3, one ready item with a feedback journey on
 * topic 6, one to hide on topic 11 and one still being generated on topic 18. The desktop project works on the
 * `coffee` tenant and the phone project on `nightsky`, so their changes never meet.
 */
const HELP = { ready: 3, feedback: 6, hide: 11, generating: 18 };
const tenantOf = (project: string) => (project === "desktop" ? "coffee" : "nightsky");

async function resetHelp(page: Page, slug: string) {
  const reset = await page.request.post(`http://127.0.0.1:${process.env.MOCK_API_PORT ?? "4390"}/__mock/reset?tenant=${slug}`);
  expect(reset.ok()).toBe(true);
}

test.describe("extra help for you", () => {
  test("two ready cards: named apart, with blocks, sources, AI label and reasons; axe clean", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await resetHelp(page, slug);
    await page.goto(`${host(slug)}/learn/${COURSE}/${HELP.ready}`, { waitUntil: "load" });
    const cards = page.getByRole("region", { name: /^Extra help for you/ });
    await expect(cards).toHaveCount(2);
    await expect(page.getByRole("region", { name: "Extra help for you (1 of 2)" })).toBeVisible();
    await expect(page.getByRole("region", { name: "Extra help for you (2 of 2)" })).toBeVisible();
    await expect(page.getByRole("heading", { level: 3, name: "Another way to see it" })).toBeVisible();
    await expect(page.getByRole("heading", { level: 3, name: "Choosing a species for a farm" })).toBeVisible();
    await expect(page.getByRole("heading", { level: 3, name: "Try it yourself" })).toBeVisible();
    await expect(page.getByRole("heading", { level: 3, name: "Say it in your own words" })).toBeVisible();
    await expect(page.getByText("AI-generated, based on this course's sources").first()).toBeVisible();
    await expect(page.getByRole("heading", { level: 4, name: "Sources" }).first()).toBeVisible();
    await expect(page.getByText("Arabica vs Robusta: the two species").first()).toBeVisible();
    const why = page.locator("details", { hasText: "Why am I seeing this?" }).first();
    await expect(why).not.toHaveAttribute("open", "");
    const violations = await scan(page);
    expect(violations, violations.join("\n")).toEqual([]);
    await why.locator("summary").click();
    await expect(why.getByText("You answered this question wrongly 2 times")).toBeVisible();
    const open = await scan(page);
    expect(open, open.join("\n")).toEqual([]);
  });

  test("the card comes before the lesson, and a long card is linked from the header", async ({ page }, info) => {
    await page.goto(`${host(tenantOf(info.project.name))}/learn/${COURSE}/${HELP.ready}`, { waitUntil: "load" });
    const link = page.getByRole("link", { name: "Extra help is available below" });
    await expect(link).toBeVisible();
    await link.click();
    await expect(page).toHaveURL(/#support-/);
    const order = await page.evaluate(() => {
      const card = document.querySelector("[data-status='ready']")!;
      const body = document.querySelector(".u-player__content")!;
      const lessonText = [...body.children].find((c) => c !== card && !c.matches(".u-player__lead, [data-status], .u-notice, .u-lv-notice"));
      return lessonText ? Boolean(card.compareDocumentPosition(lessonText) & Node.DOCUMENT_POSITION_FOLLOWING) : true;
    });
    expect(order, "the card is before the lesson content").toBe(true);
  });

  test("a practice question: nothing is revealed before Check, the result is in words, axe clean", async ({ page }, info) => {
    await page.goto(`${host(tenantOf(info.project.name))}/learn/${COURSE}/${HELP.ready}`, { waitUntil: "load" });
    const question = page.locator("ulams-practice-question");
    await expect(question.getByRole("group", { name: "Which species is usually grown at higher altitude?" })).toBeVisible();
    await expect(page.getByText("Arabica prefers cool, high ground")).toHaveCount(0);
    await question.getByRole("button", { name: "Check" }).click();
    await expect(question.getByRole("status")).toHaveText("Choose an answer first.");
    await question.getByLabel("Coffea canephora").check();
    await question.getByRole("button", { name: "Check" }).click();
    await expect(question.getByRole("status")).toContainText("Not quite.");
    await expect(question.getByText("Not quite").first()).toBeVisible();
    const wrong = await scan(page);
    expect(wrong, wrong.join("\n")).toEqual([]);
    await question.getByLabel("Coffea arabica").check();
    await question.getByRole("button", { name: "Check" }).click();
    await expect(question.getByRole("status")).toContainText("Correct. Arabica prefers cool, high ground");
    const right = await scan(page);
    expect(right, right.join("\n")).toEqual([]);
  });

  test("a generating item says it is being prepared; axe clean", async ({ page }, info) => {
    await page.goto(`${host(tenantOf(info.project.name))}/learn/${COURSE}/${HELP.generating}`, { waitUntil: "load" });
    const card = page.getByRole("region", { name: "Extra help for you" });
    await expect(card.getByText("Preparing an explanation…")).toBeVisible();
    await expect(card.getByRole("button")).toHaveCount(0);
    const violations = await scan(page);
    expect(violations, violations.join("\n")).toEqual([]);
  });

  test("'This helped' is saved, announced, stays after a reload, and can be changed; axe clean", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await resetHelp(page, slug);
    await page.goto(`${host(slug)}/learn/${COURSE}/${HELP.feedback}`, { waitUntil: "load" });
    const helped = page.getByRole("button", { name: "This helped" });
    const notHelpful = page.getByRole("button", { name: "Not helpful" });
    await expect(helped).toHaveAttribute("aria-pressed", "false");
    await helped.click();
    await expect(helped).toHaveAttribute("aria-pressed", "true");
    await expect(page.locator("ulams-support [role=status]")).toHaveText("Thanks. Your answer is saved.");
    const saved = await scan(page);
    expect(saved, saved.join("\n")).toEqual([]);
    await page.reload({ waitUntil: "load" });
    await expect(page.getByRole("button", { name: "This helped" })).toHaveAttribute("aria-pressed", "true");
    await expect(page.getByRole("button", { name: "Not helpful" })).toHaveAttribute("aria-pressed", "false");
    await notHelpful.click();
    await expect(notHelpful).toHaveAttribute("aria-pressed", "true");
    await expect(helped).toHaveAttribute("aria-pressed", "false");
  });

  test("hiding asks first, then removes the card and puts focus on the lesson heading; axe clean in both states", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await resetHelp(page, slug);
    await page.goto(`${host(slug)}/learn/${COURSE}/${HELP.hide}`, { waitUntil: "load" });
    await page.getByRole("button", { name: "Hide extra help for this lesson" }).click();
    await expect(page.getByText("Hide this help? It will not come back for this lesson.")).toBeVisible();
    const asking = await scan(page);
    expect(asking, asking.join("\n")).toEqual([]);
    await page.getByRole("button", { name: "Keep it" }).click();
    await expect(page.getByRole("button", { name: "Hide extra help for this lesson" })).toBeFocused();
    await page.getByRole("button", { name: "Hide extra help for this lesson" }).click();
    await page.getByRole("button", { name: "Yes, hide it" }).click();
    await expect(page.getByRole("region", { name: /Extra help for you/ })).toHaveCount(0);
    await expect(page.locator("h1.u-player__title")).toBeFocused();
    await expect(page.locator("#u-support-live")).toHaveText("Extra help hidden for this lesson.");
    const done = await scan(page);
    expect(done, done.join("\n")).toEqual([]);
    await page.reload({ waitUntil: "load" });
    await expect(page.getByRole("region", { name: /Extra help for you/ })).toHaveCount(0);
  });

  test("a topic with nothing on offer shows no card", async ({ page }, info) => {
    await page.goto(`${host(tenantOf(info.project.name))}/learn/${COURSE}/2`, { waitUntil: "load" });
    await expect(page.getByRole("region", { name: /Extra help for you/ })).toHaveCount(0);
  });

  test("the cards reflow without sideways scrolling (1.4.10)", async ({ page }, info) => {
    await page.setViewportSize({ width: 320, height: 700 });
    await page.goto(`${host(tenantOf(info.project.name))}/learn/${COURSE}/${HELP.ready}`, { waitUntil: "load" });
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow, "no horizontal scroll at 320 CSS px").toBeLessThanOrEqual(0);
  });
});

test("axe: platform landing", async ({ page }) => {
  await page.goto(host(""), { waitUntil: "load" });
  await revealAll(page);
  const violations = await scan(page);
  expect(violations, violations.join("\n")).toEqual([]);
});
