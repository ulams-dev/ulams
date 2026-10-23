import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * Choosing and switching a course version (CV-5) against the mock tenant API (tests/mock-api/variants.mjs): the
 * picker of a visitor, of a learner who is not enrolled, of a learner who may change, and of a learner whose
 * organisation locked it; the hint to switch; the placement check and its result. WCAG 2.2 AA (axe-core) light
 * (coffee) at desktop and dark (nightsky) at 360 px, the keyboard path, no JavaScript, the three languages, reduced
 * motion on. Each project works on its own tenant so their choices never meet.
 *
 * Families (see the mock): A 31-33 may change, B 41-42 assigned and locked, C 51-52 not enrolled, D 61-63 with a
 * hint.
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

/** A learner session: the lesson route logs the demo student in (the course page only reads the cookie). */
async function signIn(page: Page, slug: string) {
  await page.goto(`${host(slug)}/learn/1/3`, { waitUntil: "load" });
}

const picker = (page: Page) => page.locator("#variants");
const noSideScroll = (page: Page) => page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth);

test.describe("visitor and not enrolled: a read-only list", () => {
  test("a visitor sees every version with its level, role, duration and who it is for, and a note, without radios; axe passes", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await page.goto(`${host(slug)}/courses/31`, { waitUntil: "load" });
    await expect(picker(page)).toHaveAttribute("data-variants", "visitor");
    await expect(picker(page).getByRole("heading", { level: 2, name: "Choose your version" })).toBeVisible();
    const cards = picker(page).getByRole("list", { name: "Versions of this course" }).getByRole("listitem", { includeHidden: false });
    await expect(picker(page).locator("li.u-variants__card")).toHaveCount(3);
    expect(await cards.count()).toBeGreaterThanOrEqual(3);
    const expert = picker(page).locator("li.u-variants__card", { hasText: "Expert" });
    await expect(expert).toContainText("Level: Expert");
    await expect(expert).toContainText("Role: Roaster");
    await expect(expert).toContainText("Duration: 8 hours");
    await expect(expert).toContainText("For roasters who run a production schedule.");
    await expect(picker(page).locator("li.u-variants__card", { hasText: "Beginner" })).toContainText("Standard version");
    await expect(picker(page).locator("[data-visitor-note]")).toHaveText("You can choose your version once you are enrolled.");
    await expect(picker(page).locator("input")).toHaveCount(0);
    await expect(picker(page).getByText("Your current version")).toHaveCount(0);
    // the other versions are links to their own pages, the one being read is not
    await expect(picker(page).getByRole("link", { name: "Expert" })).toHaveAttribute("href", "/courses/33");
    await expect(picker(page).getByRole("link", { name: "Beginner" })).toHaveCount(0);
    expect(await noSideScroll(page)).toBe(true);
    const violations = await scan(page);
    expect(violations, violations.join("\n")).toEqual([]);
  });

  test("the page of a variant shows the same family, and a learner who is not enrolled yet gets the list too", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await page.goto(`${host(slug)}/courses/33`, { waitUntil: "load" });
    await expect(picker(page).locator("li.u-variants__card")).toHaveCount(3);
    await expect(picker(page).getByRole("link", { name: "Expert" })).toHaveCount(0);
    await signIn(page, slug);
    await page.goto(`${host(slug)}/courses/51`, { waitUntil: "load" });
    await expect(picker(page)).toHaveAttribute("data-variants", "visitor");
    await expect(picker(page).locator("[data-visitor-note]")).toBeVisible();
    await expect(picker(page).locator("input")).toHaveCount(0);
    const violations = await scan(page);
    expect(violations, violations.join("\n")).toEqual([]);
  });

  test("a course without variants has no picker", async ({ page }, info) => {
    await page.goto(`${host(tenantOf(info.project.name))}/courses/1`, { waitUntil: "load" });
    await expect(picker(page)).toHaveCount(0);
  });
});

test.describe("an enrolled learner who may change", () => {
  test("is a radio group in a fieldset, the version they are in is checked and marked, and axe passes", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await reset(page, slug);
    await signIn(page, slug);
    await page.goto(`${host(slug)}/courses/31`, { waitUntil: "load" });
    await expect(picker(page)).toHaveAttribute("data-variants", "choose");
    const group = picker(page).getByRole("group", { name: "Versions of this course" });
    await expect(group.getByRole("radio")).toHaveCount(3);
    await expect(group.getByRole("radio", { name: "Beginner" })).toBeChecked();
    await expect(group.getByRole("radio", { name: "Beginner" })).toHaveAccessibleDescription(/Your current version/);
    await expect(group.getByRole("radio", { name: "Expert" })).toHaveAccessibleDescription(/Level: Expert · Role: Roaster · Duration: 8 hours/);
    await expect(picker(page).getByText("Your current version")).toHaveCount(1);
    await expect(picker(page)).toContainText("Your progress is kept. Completed lessons that are the same in the new version carry over, and quizzes are taken again.");
    await expect(picker(page).getByRole("button", { name: "Switch to this version" })).toBeEnabled();
    // targets are large
    const radio = await group.getByRole("radio", { name: "Beginner" }).boundingBox();
    expect(radio!.width).toBeGreaterThanOrEqual(24);
    expect(radio!.height).toBeGreaterThanOrEqual(24);
    const label = await picker(page).locator("label.u-variants__option").first().boundingBox();
    expect(label!.height).toBeGreaterThanOrEqual(44);
    expect(await noSideScroll(page)).toBe(true);
    const violations = await scan(page);
    expect(violations, violations.join("\n")).toEqual([]);
  });

  test("the arrow keys move the choice, Enter on the button switches, focus lands on the status, Continue follows the version", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await reset(page, slug);
    await signIn(page, slug);
    await page.goto(`${host(slug)}/courses/31`, { waitUntil: "load" });
    const checked = picker(page).locator("input[name=course_id]:checked");
    await checked.focus();
    // a visible focus indicator on the option that holds focus
    const outline = await picker(page).locator("label.u-variants__option").first().evaluate((el) => getComputedStyle(el).outlineWidth);
    expect(parseFloat(outline)).toBeGreaterThanOrEqual(2);
    await page.keyboard.press("ArrowDown");
    await expect(picker(page).locator("input[value='32']")).toBeChecked();
    await expect(picker(page).locator("input[value='32']")).toBeFocused();
    await page.keyboard.press("ArrowDown");
    await expect(picker(page).locator("input[value='33']")).toBeChecked();
    await page.keyboard.press("ArrowUp");
    await expect(picker(page).locator("input[value='32']")).toBeChecked();
    await page.keyboard.press("Tab");
    await expect(picker(page).getByRole("button", { name: "Switch to this version" })).toBeFocused();
    await page.keyboard.press("Enter");

    await page.waitForURL(/variant=switched/);
    const status = page.locator("#variant-status");
    await expect(status).toBeFocused();
    await expect(status).toHaveAttribute("role", "status");
    await expect(status).toHaveText("Your version is now “Intermediate”. 2 completed lessons were carried over from your previous version.");
    await expect(picker(page).locator("input[value='32']")).toBeChecked();
    await expect(picker(page).locator("label[data-current] .u-variants__name")).toHaveText("Intermediate");
    await expect(picker(page).getByText("Your current version")).toHaveCount(1);
    await expect(picker(page)).toContainText("You chose this version.");
    // "Continue" belongs to the course of the version the learner is in, whichever course page this is
    const cta = page.getByRole("link", { name: /Continue learning|Start learning/ }).first();
    await expect(cta).toHaveAttribute("href", /^\/learn\/32\/\d+$/);
    const violations = await scan(page);
    expect(violations, violations.join("\n")).toEqual([]);

    // the other course page of the family leads to the same version
    await page.goto(`${host(slug)}/courses/33`, { waitUntil: "load" });
    await expect(page.getByRole("link", { name: /Continue learning|Start learning/ }).first()).toHaveAttribute("href", /^\/learn\/32\/\d+$/);
    await expect(picker(page).locator("label[data-current] .u-variants__name")).toHaveText("Intermediate");
  });

  test("choosing the version already in use changes nothing and says so", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await reset(page, slug);
    await signIn(page, slug);
    await page.goto(`${host(slug)}/courses/31`, { waitUntil: "load" });
    await picker(page).getByRole("button", { name: "Switch to this version" }).click();
    await page.waitForURL(/variant=unchanged/);
    await expect(page.locator("#variant-status")).toHaveText("You are already using this version. Nothing was changed.");
    await expect(page.locator("#variant-status")).toBeFocused();
  });

  test("says how many lessons carried over for another version, and none when nothing did", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await reset(page, slug);
    await signIn(page, slug);
    await page.goto(`${host(slug)}/courses/31`, { waitUntil: "load" });
    await picker(page).locator("input[value='33']").check();
    await picker(page).getByRole("button", { name: "Switch to this version" }).click();
    await expect(page.locator("#variant-status")).toHaveText("Your version is now “Expert”. 4 completed lessons were carried over from your previous version.");
    await page.goto(`${host(slug)}/courses/31?variant=switched&carried=0`, { waitUntil: "load" });
    await expect(page.locator("#variant-status")).toContainText("No completed lessons needed to be carried over.");
  });

  test("a refused switch is reported as an alert and changes nothing", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await reset(page, slug);
    await signIn(page, slug);
    // the API refuses a course that is not a version of the family (422): the page explains and stays put
    await page.goto(`${host(slug)}/courses/31`, { waitUntil: "load" });
    await picker(page).locator("input[value='32']").evaluate((el) => ((el as HTMLInputElement).value = "99"));
    await picker(page).locator("input[value='99']").check();
    await picker(page).getByRole("button", { name: "Switch to this version" }).click();
    await page.waitForURL(/variant=failed/);
    const status = page.locator("#variant-status");
    await expect(status).toHaveAttribute("role", "alert");
    await expect(status).toHaveText("We could not switch your version. Nothing was changed. Please try again.");
    await expect(picker(page).locator("label[data-current] .u-variants__name")).toHaveText("Beginner");
    const violations = await scan(page);
    expect(violations, violations.join("\n")).toEqual([]);
  });

  test("a status query that is not ours shows nothing", async ({ page }, info) => {
    await page.goto(`${host(tenantOf(info.project.name))}/courses/31?variant=%3Cscript%3E`, { waitUntil: "load" });
    await expect(page.locator("#variant-status")).toHaveCount(0);
  });
});

test.describe("assigned by the organisation", () => {
  test("is locked with the reason as text, the rule name, and a disabled button; axe passes", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await reset(page, slug);
    await signIn(page, slug);
    await page.goto(`${host(slug)}/courses/41`, { waitUntil: "load" });
    await expect(picker(page)).toHaveAttribute("data-variants", "locked");
    await expect(picker(page).locator("[data-assigned]")).toHaveText("Assigned by your organisation (rule: Sales team)");
    await expect(picker(page)).toContainText("Your organisation chose this version for you, so you cannot change it here.");
    const button = picker(page).getByRole("button", { name: "Switch to this version" });
    await expect(button).toBeDisabled();
    await expect(button).toHaveAccessibleDescription(/Your organisation chose this version for you/);
    await expect(picker(page).locator("input[name=course_id]").first()).toBeDisabled();
    await expect(picker(page).locator("input[value='42']")).toBeChecked();
    await expect(picker(page).locator("label[data-current] .u-variants__name")).toHaveText("Sales team");
    await expect(picker(page).locator("[data-placement-link]")).toHaveCount(0);
    expect(await noSideScroll(page)).toBe(true);
    const violations = await scan(page);
    expect(violations, violations.join("\n")).toEqual([]);
  });
});

test.describe("the hint to switch", () => {
  test("is a status with the reasons, two buttons, and never switches by itself; axe passes", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await reset(page, slug);
    await signIn(page, slug);
    await page.goto(`${host(slug)}/courses/61`, { waitUntil: "load" });
    const hint = page.locator("[data-variant-hint]");
    await expect(hint).toHaveAttribute("role", "status");
    await expect(hint.getByRole("heading", { level: 2 })).toHaveText("An easier version may suit you better");
    await expect(hint).toContainText("You are finding the first 2 modules hard; an easier version of this course may suit you better.");
    await expect(hint).toContainText("Your version never changes unless you choose to switch.");
    await expect(hint.getByRole("button", { name: "Switch to Intermediate" })).toBeVisible();
    await expect(hint.getByRole("button", { name: "No thanks" })).toHaveAccessibleDescription("If you choose “No thanks”, we stop making suggestions for this course.");
    // nothing changed: the learner is still in the default version
    await expect(picker(page).locator("label[data-current] .u-variants__name")).toHaveText("Expert");
    expect(await noSideScroll(page)).toBe(true);
    const violations = await scan(page);
    expect(violations, violations.join("\n")).toEqual([]);
  });

  test("No thanks dismisses it and hints stop; the status is announced and focused", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await reset(page, slug);
    await signIn(page, slug);
    await page.goto(`${host(slug)}/courses/61`, { waitUntil: "load" });
    const dismiss = page.locator("[data-variant-hint]").getByRole("button", { name: "No thanks" });
    await dismiss.focus();
    await page.keyboard.press("Enter");
    await page.waitForURL(/variant=dismissed/);
    await expect(page.locator("#variant-status")).toHaveText("Suggestions are turned off for this course.");
    await expect(page.locator("#variant-status")).toBeFocused();
    await expect(page.locator("[data-variant-hint]")).toHaveCount(0);
    await page.goto(`${host(slug)}/courses/61`, { waitUntil: "load" });
    await expect(page.locator("[data-variant-hint]")).toHaveCount(0);
    await expect(picker(page).locator("label[data-current] .u-variants__name")).toHaveText("Expert");
  });

  test("Switch to the suggested version moves the learner there with the hint as the reason", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await reset(page, slug);
    await signIn(page, slug);
    await page.goto(`${host(slug)}/courses/61`, { waitUntil: "load" });
    await page.locator("[data-variant-hint]").getByRole("button", { name: "Switch to Intermediate" }).click();
    await page.waitForURL(/variant=switched/);
    await expect(page.locator("#variant-status")).toContainText("Your version is now “Intermediate”.");
    await expect(picker(page).locator("label[data-current] .u-variants__name")).toHaveText("Intermediate");
    await expect(picker(page)).toContainText("You chose this version after a suggestion that an easier version might suit you better.");
    await expect(page.locator("[data-variant-hint]")).toHaveCount(0);
  });
});

test.describe("without JavaScript", () => {
  test.use({ javaScriptEnabled: false });

  test("the picker form switches the version and the page comes back with the status", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await reset(page, slug);
    await signIn(page, slug);
    await page.goto(`${host(slug)}/courses/31`, { waitUntil: "load" });
    await picker(page).locator("input[value='33']").check();
    await picker(page).getByRole("button", { name: "Switch to this version" }).click();
    await page.waitForURL(/variant=switched/);
    await expect(page.locator("#variant-status")).toContainText("Your version is now “Expert”.");
    await expect(picker(page).locator("label[data-current] .u-variants__name")).toHaveText("Expert");
  });
});

test.describe("languages", () => {
  for (const [slug, lang, heading, status, hintReason] of [
    ["polski", "pl", "Wybierz swoją wersję", "Sugestie są wyłączone dla tego kursu.", "Pierwsze 2 moduły sprawiają Ci trudność"],
    ["zhongwen", "zh", "选择你的版本", "已关闭本课程的建议。", "前 2 个模块对你来说有些吃力"],
  ] as const) {
    test(`the picker and the hint follow the language (${lang})`, async ({ page }, info) => {
      test.skip(info.project.name !== "desktop", "one project is enough for the language checks");
      await reset(page, slug);
      await signIn(page, slug);
      await page.goto(`${host(slug)}/courses/61`, { waitUntil: "load" });
      await expect(page.locator("html")).toHaveAttribute("lang", lang);
      await expect(picker(page).getByRole("heading", { level: 2, name: heading })).toBeVisible();
      await expect(page.locator("[data-variant-hint]")).toContainText(hintReason);
      const violations = await scan(page);
      expect(violations, violations.join("\n")).toEqual([]);
      await page.locator("[data-variant-hint]").locator("button[value=hint-dismiss]").click();
      await expect(page.locator("#variant-status")).toHaveText(status);
    });
  }
});

test.describe("motion", () => {
  test("reduced motion leaves no running animation or transition on the picker", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await reset(page, slug);
    await signIn(page, slug);
    await page.goto(`${host(slug)}/courses/31`, { waitUntil: "load" });
    expect(await page.evaluate(() => matchMedia("(prefers-reduced-motion: reduce)").matches)).toBe(true);
    const running = await picker(page).evaluate((root) =>
      [...root.querySelectorAll("*")].filter((el) => {
        const style = getComputedStyle(el);
        const seconds = (value: string) => Math.max(...value.split(",").map((v) => parseFloat(v)));
        return seconds(style.transitionDuration) > 0.05 || (style.animationName !== "none" && seconds(style.animationDuration) > 0.05);
      }).length
    );
    expect(running).toBe(0);
  });
});
