import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * The studio Style page (/studio/style, WCAG 2.2 AA, axe-core) against the mock tenant API, at desktop and 360 px phone
 * width (the Playwright projects) with reduced motion on: the profile list, a suggestion with its citations, dropped values
 * and changes against the approved profile, the edit form, approval (allowed and refused), the revision of an approved
 * profile, the two-step delete, an upload by keyboard, and the page in English, Polish and Simplified Chinese. The studio
 * signs in as the demo admin of the mock tenant. Mock profiles: tests/mock-api/server.mjs (styleFresh).
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const host = (slug: string) => `http://${slug}.app.localhost:${web}`;
const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];
const SUGGESTED = "01styleprofile000000000001";
const APPROVED = "01styleprofile000000000002";

test.use({ contextOptions: { reducedMotion: "reduce" } });

async function scan(page: Page) {
  const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();
  return results.violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.slice(0, 3).map((n) => `${n.target.join(" ")} - ${n.any[0]?.message ?? n.failureSummary ?? ""}`).join(" | ")}`);
}

const noSidewaysScroll = (page: Page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
const slugOf = (project: string) => (project === "desktop" ? "coffee" : "nightsky");

test.beforeEach(async ({}, info) => {
  const reset = await fetch(`http://127.0.0.1:${process.env.MOCK_API_PORT ?? "4390"}/__mock/style/reset`, { method: "POST", headers: { "x-ulams-api-host": `${slugOf(info.project.name)}.localhost` } });
  expect(reset.ok).toBe(true);
});

async function open(page: Page, project: string, lang = "en") {
  await page.goto(`${host(slugOf(project))}/studio/style?lang=${lang}`, { waitUntil: "load" });
}

const review = (page: Page, name: string) => page.locator(`[data-review="${name}"]`);

test("the list: the status is text, it passes axe and reflows", async ({ page }, info) => {
  await open(page, info.project.name);
  await expect(page.locator("h1")).toHaveText("Style profiles");
  await expect(page.locator("table caption")).toHaveText("Style profiles of this academy, newest first");
  await expect(page.locator("tbody tr")).toHaveCount(3);
  await expect(page.locator("tbody tr").first()).toContainText("Suggested, not approved");
  await expect(page.locator("tbody tr").nth(1)).toContainText("Approved");
  await expect(page.locator("tbody tr").nth(2)).toContainText("Replaced by a newer profile");
  expect(await scan(page), "list").toEqual([]);
  expect(await noSidewaysScroll(page), "page scrolls sideways").toBeLessThanOrEqual(1);
});

test("a suggestion shows values, passages, dropped values and changes, and passes axe", async ({ page }, info) => {
  await open(page, info.project.name);
  await review(page, SUGGESTED).click();
  await expect(page.getByRole("heading", { name: "Style profile: Northwind" })).toBeFocused();
  await expect(page.locator("[data-warnings]")).toContainText("Marquee");
  await expect(page.locator("[data-change='voice.person']")).toContainText("Changed");
  await expect(page.locator("[data-change='terminology.learner']")).toContainText("Added");
  await expect(page.locator("[data-change='terminology.student']")).toContainText("Removed");
  await expect(page.locator("[data-value='terminology.lesson']")).toContainText("Your own value, no passage cited");
  await expect(page.locator("[data-theme] a")).toHaveAttribute("href", /configuration\/settings\/theme_settings\?styleProfile=01styleprofile000000000001$/);
  expect(await scan(page), "suggestion").toEqual([]);
  expect(await noSidewaysScroll(page), "page scrolls sideways").toBeLessThanOrEqual(1);
});

test("a citation opens its passage with the keyboard and says so", async ({ page }, info) => {
  await open(page, info.project.name);
  await review(page, SUGGESTED).focus();
  await page.keyboard.press("Enter");
  const cite = page.locator("[data-value='voice.person'] [data-cite]").first();
  await cite.focus();
  await expect(cite).toHaveAttribute("aria-expanded", "false");
  await page.keyboard.press("Enter");
  await expect(cite).toHaveAttribute("aria-expanded", "true");
  await expect(page.locator("[data-value='voice.person'] blockquote")).toContainText("Write to the learner");
  await expect(cite).toHaveAccessibleName("Hide the passage §1 Voice");
  expect(await scan(page), "passage open").toEqual([]);
  await page.keyboard.press("Space");
  await expect(page.locator("[data-value='voice.person'] blockquote")).toBeHidden();
});

test("the controls are reachable by keyboard, show focus and are large enough", async ({ page }, info) => {
  await open(page, info.project.name);
  await review(page, SUGGESTED).focus();
  await page.keyboard.press("Enter");
  await expect(page.locator("[data-edit]")).toBeVisible();
  await page.locator("#main").focus();
  const seen = new Set<string>();
  for (let i = 0; i < 80; i++) {
    await page.keyboard.press("Tab");
    const stop = await page.evaluate(() => {
      const el = document.activeElement as HTMLElement | null;
      if (!el || el === document.body) return null;
      const style = getComputedStyle(el);
      const rect = el.getBoundingClientRect();
      return { tag: el.tagName, outline: style.outlineStyle !== "none" && parseFloat(style.outlineWidth) >= 2, width: rect.width, height: rect.height, label: (el.getAttribute("aria-label") ?? el.textContent ?? "").trim().slice(0, 40) };
    });
    if (!stop) break;
    seen.add(stop.label);
    expect(stop.outline, `focus indicator on ${stop.tag} ${stop.label}`).toBe(true);
    if (stop.tag === "BUTTON") expect(Math.min(stop.width, stop.height), `target size of ${stop.label}`).toBeGreaterThanOrEqual(24);
  }
  expect([...seen].some((l) => l.startsWith("Remove "))).toBe(true);
  expect([...seen]).toContain("Approve this profile");
});

test("editing: a value is removed and the voice saved; what the API dropped is listed", async ({ page }, info) => {
  await open(page, info.project.name);
  await review(page, SUGGESTED).click();
  await page.getByRole("button", { name: "Remove lesson" }).click();
  await expect(page.locator("[data-value='terminology.lesson']")).toHaveCount(0);
  await page.getByLabel("Person", { exact: true }).selectOption("we");
  await page.getByLabel("Formality", { exact: true }).fill("5");
  await page.getByRole("button", { name: "Save the profile" }).click();
  await expect(page.locator("[data-status]")).toHaveText("The profile was saved.");
  await expect(page.locator("[data-value='voice.person']")).toContainText("Person: we");
  await expect(page.locator("[data-value='terminology.lesson']")).toHaveCount(0);
  expect(await scan(page), "after save").toEqual([]);

  await page.locator("details summary").click();
  await page.getByLabel("Profile values (JSON)").fill("{ nope");
  await page.getByRole("button", { name: "Save the profile" }).click();
  await expect(page.locator("[data-alert]")).toHaveText("That is not valid JSON.");
  expect(await scan(page), "invalid json").toEqual([]);
});

test("approving replaces the approved profile, announces it and leaves the profile read-only; a revision starts a new suggestion", async ({ page }, info) => {
  await open(page, info.project.name);
  await review(page, SUGGESTED).click();
  await page.getByRole("button", { name: "Approve this profile" }).click();
  await expect(page.locator("[data-status]")).toHaveText("Approved. This is now the academy's style profile.");
  await expect(page.locator("[data-announcer]")).toHaveText("Approved. This is now the academy's style profile.");
  await expect(page.locator("[data-edit]")).toHaveCount(0);
  await expect(page.locator("tbody tr").first()).toContainText("Approved");
  await expect(page.locator("tbody tr").nth(1)).toContainText("Replaced by a newer profile");
  await expect(page.locator("tbody tr").nth(2)).toContainText("Replaced by a newer profile");
  expect(await scan(page), "approved").toEqual([]);

  await page.getByRole("button", { name: "Create a revision" }).click();
  await expect(page.locator("[data-status]")).toHaveText("A revision was created. Edit it, then approve it.");
  await expect(page.locator("[data-edit]")).toBeVisible();
  await expect(page.locator("tbody tr")).toHaveCount(4);
  expect(await scan(page), "revision").toEqual([]);
});

test("an approve the API refuses is explained", async ({ page }, info) => {
  await open(page, info.project.name);
  await page.locator("[data-upload] input[name=name]").fill("Forbidden");
  await page.locator("[data-upload] input[type=file]").setInputFiles({ name: "guide.md", mimeType: "text/markdown", buffer: Buffer.from("# Guide\n\nWrite to the learner as you.\n") });
  await page.getByRole("button", { name: "Read the style guide" }).click();
  await expect(page.getByRole("heading", { name: "Style profile: Forbidden" })).toBeVisible();
  await expect(page.locator("[data-warnings]")).toContainText("AI is off");
  await page.getByRole("button", { name: "Approve this profile" }).click();
  await expect(page.locator("[data-alert]")).toContainText("Only an admin approves a style profile");
  expect(await scan(page), "refused").toEqual([]);
});

test("an upload without a file is refused and a delete takes two steps", async ({ page }, info) => {
  await open(page, info.project.name);
  await page.getByRole("button", { name: "Read the style guide" }).click();
  await expect(page.locator("[data-alert]")).toHaveText("Choose a style guide file first.");
  expect(await scan(page), "no file").toEqual([]);

  await review(page, SUGGESTED).click();
  await page.getByRole("button", { name: "Delete this profile" }).click();
  await expect(page.getByRole("button", { name: "Yes, delete it" })).toBeFocused();
  await page.getByRole("button", { name: "Keep it" }).click();
  await expect(page.locator("tbody tr")).toHaveCount(3);
  await page.getByRole("button", { name: "Delete this profile" }).click();
  await page.getByRole("button", { name: "Yes, delete it" }).click();
  await expect(page.locator("tbody tr")).toHaveCount(2);
  await expect(page.locator("[data-status]")).toHaveText("The profile was deleted.");
  expect(await scan(page), "deleted").toEqual([]);
});

test("the approved profile has no editing controls", async ({ page }, info) => {
  await open(page, info.project.name);
  await review(page, APPROVED).click();
  await expect(page.locator("[data-detail]")).toContainText("create a revision");
  await expect(page.locator("[data-edit]")).toHaveCount(0);
  await expect(page.locator("[data-remove]")).toHaveCount(0);
  await expect(page.locator("[data-changes]")).toHaveCount(0);
  expect(await scan(page), "approved read-only").toEqual([]);
});

test("Polish and Chinese pages pass axe and say their language", async ({ page }, info) => {
  await open(page, info.project.name, "pl");
  await expect(page.locator("h1")).toHaveText("Profile stylu");
  await expect(page.locator("main")).toHaveAttribute("lang", "pl");
  await review(page, SUGGESTED).click();
  await expect(page.locator("[data-change='terminology.learner']")).toContainText("Dodano");
  expect(await scan(page), "pl").toEqual([]);
  await open(page, info.project.name, "zh");
  await expect(page.locator("h1")).toHaveText("样式配置");
  await expect(page.locator("main")).toHaveAttribute("lang", "zh-Hans");
  await review(page, SUGGESTED).click();
  await expect(page.locator("[data-change='terminology.learner']")).toContainText("新增");
  expect(await scan(page), "zh").toEqual([]);
});
