import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * The placement check of a course family (CV-5) against the mock tenant API (tests/mock-api/variants.mjs, family E
 * courses 71-73 with six questions): the link from the picker, one fieldset per question, no timer, the keyboard
 * path, the advisory result with its reason and scores, "Use this version", the explanation when there is no check,
 * no JavaScript and the three languages. WCAG 2.2 AA (axe-core) light (coffee) at desktop and dark (nightsky) at
 * 360 px, reduced motion on. Each project works on its own tenant so their choices never meet.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const mock = process.env.MOCK_API_PORT ?? "4390";
const host = (slug: string) => `http://${slug}.app.localhost:${web}`;
const tenantOf = (project: string) => (project === "desktop" ? "coffee" : "nightsky");
const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];

test.use({ contextOptions: { reducedMotion: "reduce" } });

async function scan(page: Page) {
  const results = await new AxeBuilder({ page }).withTags(TAGS).exclude("iframe").analyze();
  return results.violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.slice(0, 3).map((n) => `${n.target.join(" ")} - ${n.any[0]?.message ?? n.failureSummary ?? ""}`).join(" | ")}`);
}

async function reset(page: Page, slug: string) {
  expect((await page.request.post(`http://127.0.0.1:${mock}/__mock/reset?tenant=${slug}`)).ok()).toBe(true);
}

/** A learner session: the lesson route logs the demo student in. */
async function signIn(page: Page, slug: string) {
  await page.goto(`${host(slug)}/learn/1/3`, { waitUntil: "load" });
}

const picker = (page: Page) => page.locator("#variants");
const noSideScroll = (page: Page) => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth);

test.describe("the placement check", () => {
  /** The key of the mock: both beginner and both intermediate questions right, the expert ones wrong. */
  async function answer(page: Page) {
    await page.locator("input[name=q-701][value=a]").check();
    await page.locator("input[name=q-702][value=t]").check();
    await page.locator("input[name=q-703][value=a]").check();
    await page.locator("input[name=q-704][value=a]").check();
    await page.locator("input[name=q-704][value=b]").check();
    await page.locator("input[name=q-705][value=b]").check();
    await page.locator("input[name=q-706][value=c]").check();
  }

  test("is linked from the picker, has one fieldset per question, no timer, and passes axe", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await reset(page, slug);
    await signIn(page, slug);
    await page.goto(`${host(slug)}/courses/71`, { waitUntil: "load" });
    await picker(page).getByRole("link", { name: "Not sure? Take the placement check" }).click();
    await expect(page).toHaveURL(/\/courses\/71\/placement$/);
    await expect(page.getByRole("heading", { level: 1, name: "Placement check" })).toBeVisible();
    await expect(page.getByText("This check is advisory. It only suggests a version: nothing decides your level for you, and you choose which version to use.")).toBeVisible();
    await expect(page.getByText("There is no timer.")).toBeVisible();
    await expect(page.locator("fieldset")).toHaveCount(6);
    await expect(page.getByRole("group", { name: /Question 1 of 6\s*What does the portafilter hold\?/ })).toBeVisible();
    await expect(page.locator("input[name=q-701]").first()).toHaveAttribute("type", "radio");
    await expect(page.locator("input[name=q-702]").first()).toHaveAttribute("type", "radio");
    await expect(page.locator("input[name=q-704]").first()).toHaveAttribute("type", "checkbox");
    await expect(page.getByRole("group", { name: /Which signs say the machine needs descaling/ })).toHaveAccessibleDescription("Choose every answer that applies.");
    await expect(page.getByRole("group", { name: /What does the portafilter hold/ })).toHaveAccessibleDescription("Choose one answer.");
    expect(await noSideScroll(page)).toBe(true);
    const violations = await scan(page);
    expect(violations, violations.join("\n")).toEqual([]);
  });

  test("the result suggests a version with its reason and scores, is advisory, and Use this version switches; the keyboard path works", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await reset(page, slug);
    await signIn(page, slug);
    await page.goto(`${host(slug)}/courses/71/placement`, { waitUntil: "load" });
    // keyboard: arrows inside a radio group, Space on a checkbox, Enter on the submit button
    await page.locator("input[name=q-701][value=b]").focus();
    await page.keyboard.press("ArrowUp");
    await expect(page.locator("input[name=q-701][value=a]")).toBeChecked();
    await answer(page);
    await page.getByRole("button", { name: "See my suggestion" }).focus();
    await page.keyboard.press("Enter");

    await page.waitForURL(/placement\?result=1/);
    const result = page.locator("[data-placement-result]");
    await expect(result.getByRole("heading", { level: 2, name: "Your result" })).toBeFocused();
    await expect(result).toContainText("We suggest Intermediate");
    await expect(result).toContainText("You answered 2 of 2 intermediate questions correctly, so we suggest Intermediate.");
    await expect(result.getByRole("list", { name: "Your score for each level" }).getByRole("listitem")).toHaveText(["Beginner: 2 of 2 correct", "Intermediate: 2 of 2 correct", "Expert: 0 of 2 correct"]);
    await expect(result).toContainText("This check is advisory.");
    await expect(result).toContainText("nothing decides your level for you");
    await expect(result.getByRole("link", { name: "Choose another version" })).toHaveAttribute("href", "/courses/71#variants");
    expect(await noSideScroll(page)).toBe(true);
    const violations = await scan(page);
    expect(violations, violations.join("\n")).toEqual([]);

    await result.getByRole("button", { name: "Use this version" }).click();
    await page.waitForURL(/\/courses\/71\?variant=switched/);
    await expect(page.locator("#variant-status")).toContainText("Your version is now “Intermediate”.");
    await expect(picker(page).locator("label[data-current] .u-variants__name")).toHaveText("Intermediate");
    await expect(picker(page)).toContainText("You chose this version after the placement check.");
  });

  test("a learner who is not enrolled, or a family without the check, gets an explanation", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await signIn(page, slug);
    await page.goto(`${host(slug)}/courses/51/placement`, { waitUntil: "load" });
    await expect(page.locator("[data-placement-problem]")).toHaveText("There is no placement check for this course.");
    await page.goto(`${host(slug)}/courses/31/placement`, { waitUntil: "load" });
    await expect(page.locator("[data-placement-problem]")).toHaveText("There is no placement check for this course.");
    await expect(page.getByRole("link", { name: "Back to the course" })).toHaveAttribute("href", "/courses/31#variants");
    const violations = await scan(page);
    expect(violations, violations.join("\n")).toEqual([]);
  });
});

test.describe("without JavaScript", () => {
  test.use({ javaScriptEnabled: false });

  test("the placement form scores without JavaScript", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await reset(page, slug);
    await signIn(page, slug);
    await page.goto(`${host(slug)}/courses/71/placement`, { waitUntil: "load" });
    await page.locator("input[name=q-701][value=a]").check();
    await page.getByRole("button", { name: "See my suggestion" }).click();
    await page.waitForURL(/result=1/);
    await expect(page.locator("[data-placement-result]")).toContainText("We suggest Beginner");
  });
});

test.describe("languages", () => {
  for (const [slug, lang, title, suggest] of [
    ["polski", "pl", "Test poziomujący", "Proponujemy wersję „Beginner”"],
    ["zhongwen", "zh", "分级测试", "我们建议“Beginner”"],
  ] as const) {
    test(`the check and its result follow the language (${lang})`, async ({ page }, info) => {
      test.skip(info.project.name !== "desktop", "one project is enough for the language checks");
      await reset(page, slug);
      await signIn(page, slug);
      await page.goto(`${host(slug)}/courses/71/placement`, { waitUntil: "load" });
      await expect(page.locator("html")).toHaveAttribute("lang", lang);
      await expect(page.getByRole("heading", { level: 1, name: title })).toBeVisible();
      const questions = await scan(page);
      expect(questions, questions.join("\n")).toEqual([]);
      await page.locator("input[name=q-701][value=a]").check();
      await page.locator("form button[type=submit]").click();
      await page.waitForURL(/result=1/);
      await expect(page.locator("[data-placement-result]")).toContainText(suggest);
      const result = await scan(page);
      expect(result, result.join("\n")).toEqual([]);
    });
  }
});
