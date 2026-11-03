import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * The studio Compare page (/studio/courses/:id/compare, WCAG 2.2 AA, axe-core) against the mock tenant API, at desktop and
 * 360 px phone width (the Playwright projects) with reduced motion on: the summary, what learners would notice, the quiz
 * impact and the tree of changes with its detail panel (inline and side by side, citations, quiz), the keyboard path
 * through the tree, the pair picker and the variant picker, and every state (same version, no change, a pair of another
 * course, another author's course, a course without versions, an error with retry), in English, Polish and Chinese. The
 * Versions page controls that lead here (ticking two versions, comparing with the previous release) are in
 * studio-versions.a11y.spec.ts.
 *
 * Mock courses (tests/mock-api/server.mjs): 7 three releases and the rich comparison, 6 the small ones (releases 1 and 2
 * are alike), 9 a base with a variant, 12 fails its first comparison, 2 no versions, 3 not made with the builder, 4 another author's.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const host = (slug: string) => `http://${slug}.app.localhost:${web}`;
const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];

/** The mock's ids: 01<kind><course 2 digits><number 21 digits>; kind v is a version, r a release, x a rejected version. */
const id = (kind: "v" | "r" | "x", course: number, n: number) => `01${kind}${String(course).padStart(2, "0")}${String(n).padStart(21, "0")}`;
const compare = (course: number, from: string, to: string, lang = "en") => `/studio/courses/${course}/compare?from=${from}&to=${to}&lang=${lang}`;
const RICH = compare(7, id("v", 7, 1), id("v", 7, 3));

test.use({ contextOptions: { reducedMotion: "reduce" } });

async function scan(page: Page) {
  const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();
  return results.violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.slice(0, 3).map((n) => `${n.target.join(" ")} - ${n.any[0]?.message ?? n.failureSummary ?? ""}`).join(" | ")}`);
}

const noSidewaysScroll = (page: Page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);

/** Desktop and phone run in parallel against one mock API: each project has its own tenant for the test that changes mock state. */
const slugOf = (project: string) => (project === "desktop" ? "coffee" : "nightsky");

async function open(page: Page, path: string, slug = "coffee") {
  await page.goto(`${host(slug)}${path}`, { waitUntil: "load" });
}

const node = (page: Page, nodeId: string) => page.locator(`[data-node="${nodeId}"]`);
const detail = (page: Page) => page.locator("[data-detail]");

async function ready(page: Page, path = RICH, slug = "coffee") {
  await open(page, path, slug);
  await expect(page.locator('[role="tree"]')).toBeVisible();
}

test("the comparison shows a summary, what learners would notice, the quiz impact and the tree, and passes axe and reflows", async ({ page }) => {
  await ready(page);
  await expect(page.locator("h1")).toHaveText("Compare versions");
  await expect(page.locator(".st-cmp-sides")).toContainText("From: Version 2 (release #1)");
  await expect(page.locator(".st-cmp-sides")).toContainText("To: Version 6 (release #3)");
  await expect(page.getByRole("heading", { name: "What changed" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "What learners would notice" })).toBeVisible();
  await expect(page.getByRole("heading", { name: "Quiz impact", level: 2 })).toBeVisible();
  await expect(page.locator(".st-cmp-quiz-list li")).toHaveCount(2);
  await expect(page.locator("[data-count]")).toHaveText("Showing 10 of 10 changed elements");
  expect(await scan(page), "loaded").toEqual([]);
  expect(await noSidewaysScroll(page), "page scrolls sideways").toBeLessThanOrEqual(1);
});

test("added, removed, changed, moved and reordered are words with a symbol", async ({ page }) => {
  await ready(page);
  const badge = (nodeId: string) => node(page, nodeId).locator(":scope > .st-cmp-row .cb-badge");
  await expect(badge("les_3")).toHaveText("+ Added");
  await expect(badge("blk_old")).toHaveText("− Removed");
  await expect(badge("les_1")).toHaveText("~ Changed");
  await expect(badge("les_2")).toHaveText("→ Moved");
  await expect(badge("les_5")).toHaveText("↕ Reordered");
});

test("the tree is one tab stop with arrow keys; Enter opens the details and moves the focus there", async ({ page }) => {
  await ready(page);
  await expect(page.locator('[role="treeitem"][tabindex="0"]')).toHaveCount(1);

  await page.locator('[role="treeitem"][tabindex="0"]').focus();
  await page.keyboard.press("ArrowDown");
  await expect(node(page, "les_1")).toBeFocused();
  await page.keyboard.press("ArrowDown");
  await expect(node(page, "blk_1")).toBeFocused();
  await page.keyboard.press("Home");
  await expect(node(page, "mod_1")).toBeFocused();
  await page.keyboard.press("ArrowLeft");
  await expect(node(page, "mod_1")).toHaveAttribute("aria-expanded", "false");
  await page.keyboard.press("ArrowRight");
  await expect(node(page, "mod_1")).toHaveAttribute("aria-expanded", "true");
  await page.keyboard.press("End");
  await expect(node(page, "les_5")).toBeFocused();

  await node(page, "blk_1").focus();
  await page.keyboard.press("Enter");
  await expect(detail(page).locator("h3")).toBeFocused();
  await expect(detail(page).locator("h3")).toContainText("Block 1");
  await expect(node(page, "blk_1")).toHaveAttribute("aria-selected", "true");
  await expect(detail(page).locator("del").first()).toContainText("Heat");
  await expect(detail(page).locator("ins").first()).toContainText("Warm");
  expect(await scan(page), "selected").toEqual([]);

  await page.getByRole("button", { name: "Back to the list of changes" }).press("Enter");
  await expect(node(page, "blk_1")).toBeFocused();
});

test("inline and side by side show the same words, stack on a phone and pass axe", async ({ page }) => {
  await ready(page);
  await node(page, "blk_1").focus();
  await page.keyboard.press("Enter");
  await expect(detail(page).locator("ins").first()).toBeVisible();
  expect(await scan(page), "inline").toEqual([]);

  await page.getByRole("button", { name: "Side by side" }).click();
  await expect(page.getByRole("button", { name: "Side by side" })).toHaveAttribute("aria-pressed", "true");
  const sides = detail(page).locator(".st-cmp-side");
  await expect(sides).toHaveCount(2);
  await expect(sides.nth(0).locator("del").first()).toContainText("Heat");
  await expect(sides.nth(1).locator("ins").first()).toContainText("Warm");
  expect(await scan(page), "side by side").toEqual([]);
  expect(await noSidewaysScroll(page), "side by side scrolls sideways").toBeLessThanOrEqual(1);

  const [a, b] = await Promise.all([sides.nth(0).boundingBox(), sides.nth(1).boundingBox()]);
  if (page.viewportSize()!.width <= 520) expect(b!.y, "the columns stack on a phone").toBeGreaterThan(a!.y + a!.height - 1);
  else expect(Math.abs(a!.y - b!.y), "the columns sit side by side on desktop").toBeLessThan(2);
});

test("citations that changed show both passages, and the quiz panel shows the answer key and its consequence", async ({ page }) => {
  await ready(page);
  await node(page, "les_1").focus();
  await page.keyboard.press("Enter");
  const citations = detail(page).locator('[data-panel="citations"]');
  await expect(citations).toContainText("Citation repointed to a newer source revision");
  await expect(citations).toContainText("Use water at 90 degrees.");
  await expect(citations).toContainText("Use water at 95 degrees.");
  await expect(citations).toContainText("source revision 3");
  expect(await scan(page), "citations").toEqual([]);

  await page.locator('[data-quiz-jump="q_1"]').click();
  await expect(detail(page).locator("h3")).toBeFocused();
  const quiz = detail(page).locator('[data-panel="quiz"]');
  await expect(quiz).toContainText("Correct before");
  await expect(quiz).toContainText("Pressure");
  await expect(quiz).toContainText("Temperature");
  await expect(quiz.getByRole("note")).toContainText("re-attempt notice");
  expect(await scan(page), "quiz").toEqual([]);
});

test("filter chips narrow the tree, are pressed buttons, announce the count and clear", async ({ page }) => {
  await ready(page);
  const moved = page.locator('[data-filter-kind="moved"]');
  await moved.focus();
  await page.keyboard.press("Enter");
  await expect(moved).toHaveAttribute("aria-pressed", "true");
  await expect(moved).toBeFocused();
  await expect(page.locator("[data-count]")).toHaveText("Showing 1 of 10 changed elements");
  await expect(page.locator("[data-announcer]")).toHaveText("Showing 1 of 10 changed elements.");
  await expect(node(page, "les_3")).toHaveCount(0);
  expect(await scan(page), "filtered").toEqual([]);

  await page.locator('[data-filter-type="option"]').click();
  await expect(page.locator('[data-state="no-match"]')).toHaveText("No changed element matches these filters.");
  await page.getByRole("button", { name: "Show all changes" }).click();
  await expect(page.locator("[data-count]")).toHaveText("Showing 10 of 10 changed elements");
});

test("every control can be reached by keyboard, shows focus and is large enough", async ({ page }) => {
  await ready(page);
  await page.locator("#main").focus();
  for (let i = 0; i < 90; i++) {
    await page.keyboard.press("Tab");
    const stop = await page.evaluate(() => {
      const el = document.activeElement as HTMLElement | null;
      if (!el || el === document.body) return null;
      // a tree item shows its focus on its row
      const shown = el.getAttribute("role") === "treeitem" ? (el.firstElementChild as HTMLElement) : el;
      const style = getComputedStyle(shown);
      const rect = shown.getBoundingClientRect();
      const label = (el.textContent ?? "").trim().slice(0, 30);
      return { tag: el.tagName, role: el.getAttribute("role"), button: el.tagName === "BUTTON" || el.classList.contains("cb-btn"), outline: style.outlineStyle !== "none" && parseFloat(style.outlineWidth) >= 2, width: rect.width, height: rect.height, label };
    });
    if (!stop) break;
    expect(stop.outline, `focus indicator on ${stop.tag} ${stop.label}`).toBe(true);
    if (stop.button || stop.role === "treeitem") expect(Math.min(stop.width, stop.height), `target size of ${stop.label}`).toBeGreaterThanOrEqual(24);
  }
});

test("nothing animates under reduced motion", async ({ page }) => {
  await ready(page);
  expect(await page.evaluate(() => matchMedia("(prefers-reduced-motion: reduce)").matches)).toBe(true);
  await node(page, "les_1").focus();
  await page.keyboard.press("Enter");
  expect(await page.evaluate(() => document.getAnimations().filter((a) => a.playState === "running" && !(a.effect as KeyframeEffect | null)?.target?.closest?.("[aria-hidden='true']")).length)).toBe(0);
});

test("the pair picker has labelled selects, swaps the sides and compares by a plain form", async ({ page }) => {
  await ready(page);
  const from = page.getByLabel("From (older)");
  const to = page.getByLabel("To (newer)");
  await expect(from).toHaveValue(id("v", 7, 1));
  await expect(to).toHaveValue(id("v", 7, 3));
  await expect(from.locator("option").first()).toContainText("release");
  await from.selectOption(id("v", 7, 2));
  await page.getByRole("button", { name: "Compare", exact: true }).click();
  await expect(page).toHaveURL(new RegExp(`from=${id("v", 7, 2)}&to=${id("v", 7, 3)}&lang=en`));
  await expect(page.locator('[role="tree"]')).toBeVisible();
  await expect(page.locator(".st-cmp-sides")).toContainText("From: Version 4 (release #2)");

  await page.getByRole("link", { name: "Swap the two sides" }).click();
  await expect(page).toHaveURL(new RegExp(`from=${id("v", 7, 3)}&to=${id("v", 7, 2)}`));
  await expect(page.locator(".st-cmp-sides")).toContainText("From: Version 6 (release #3)");
});

test("without a chosen pair the page asks for two versions", async ({ page }) => {
  await open(page, "/studio/courses/7/compare?lang=en");
  await expect(page.getByLabel("From (older)")).toBeVisible();
  await expect(page.getByText("Pick two versions and choose Compare.")).toBeVisible();
  await expect(page.getByLabel("To (newer)")).toHaveValue(id("v", 7, 3));
  expect(await scan(page)).toEqual([]);
});

test("a variant is compared with its base through the variant picker", async ({ page }) => {
  await open(page, "/studio/courses/9/compare?lang=en");
  await expect(page.locator("[data-variant-form]")).toBeVisible();
  await expect(page.getByLabel("Compare the base course with a variant")).toContainText("Expert");
  expect(await scan(page), "variant picker").toEqual([]);

  await page.getByRole("button", { name: "Compare base with variant" }).click();
  await expect(page).toHaveURL(new RegExp(`from=${id("v", 9, 3)}&to=${id("v", 10, 2)}`));
  await expect(page.locator('[role="tree"]')).toBeVisible();
  await expect(page.getByLabel("To (newer)").locator("option:checked")).toContainText("Expert");
  expect(await scan(page), "variant against base").toEqual([]);
});

test("the same version on both sides and two versions without a change are said in words", async ({ page }) => {
  await open(page, compare(6, id("v", 6, 1), id("v", 6, 1)));
  await expect(page.getByText("The same version is on both sides")).toBeVisible();
  expect(await scan(page), "same").toEqual([]);

  await open(page, compare(6, id("v", 6, 1), id("v", 6, 2)));
  await expect(page.locator('[data-state="no-change"]')).toContainText("These two versions have the same content");
  expect(await scan(page), "no change").toEqual([]);

  await open(page, compare(6, id("v", 6, 1), id("v", 6, 3)));
  await expect(page.locator('[role="tree"]')).toBeVisible();
  await expect(page.locator("[data-count]")).toHaveText("Showing 1 of 1 changed elements");
});

test("a pair that is not part of the course, another author's course and a course without versions are explained", async ({ page }) => {
  await open(page, compare(7, id("v", 7, 1), id("v", 8, 1)));
  await expect(page.locator('[data-state="not-found"]')).toContainText("One of these versions or releases is not part of this course.");
  await expect(page.getByLabel("From (older)")).toBeVisible();
  expect(await scan(page), "not found").toEqual([]);

  await open(page, compare(4, id("v", 4, 1), id("v", 4, 2)));
  await expect(page.locator('[data-studio-compare] [role="alert"]')).toContainText("belongs to another author");
  expect(await scan(page), "forbidden").toEqual([]);

  await open(page, "/studio/courses/3/compare?lang=en");
  await expect(page.locator('[data-state="not-built"]')).toContainText("Adopt the course to track its versions.");

  await open(page, "/studio/courses/2/compare?lang=en");
  await expect(page.locator('[data-state="empty"]')).toContainText("This course has no versions yet.");
});

test("an error offers a retry that recovers", async ({ page }, info) => {
  const slug = slugOf(info.project.name);
  const reset = await fetch(`http://127.0.0.1:${process.env.MOCK_API_PORT ?? "4390"}/__mock/course-versions/reset`, { method: "POST", headers: { "x-ulams-api-host": `${slug}.localhost` } });
  expect(reset.ok).toBe(true);
  await open(page, compare(12, id("v", 12, 1), id("v", 12, 3)), slug);
  await expect(page.locator('[data-studio-compare] [role="alert"]')).toContainText("The comparison could not be loaded.");
  expect(await scan(page), "error").toEqual([]);
  await page.getByRole("button", { name: "Try again" }).click();
  await expect(page.locator('[role="tree"]')).toBeVisible();
});

test("Polish and Chinese pages pass axe, say their language and keep the words of every change", async ({ page }) => {
  await ready(page, compare(7, id("v", 7, 1), id("v", 7, 3), "pl"));
  await expect(page.locator("h1")).toHaveText("Porównanie wersji");
  await expect(page.locator("main")).toHaveAttribute("lang", "pl");
  await expect(node(page, "les_3").locator(":scope > .st-cmp-row .cb-badge")).toHaveText("+ Dodano");
  await node(page, "blk_1").focus();
  await page.keyboard.press("Enter");
  await expect(detail(page).locator("del").first()).toContainText("usunięto");
  expect(await scan(page), "pl").toEqual([]);

  await ready(page, compare(7, id("v", 7, 1), id("v", 7, 3), "zh"));
  await expect(page.locator("h1")).toHaveText("对比版本");
  await expect(page.locator("main")).toHaveAttribute("lang", "zh-Hans");
  await expect(node(page, "les_3").locator(":scope > .st-cmp-row .cb-badge")).toHaveText("+ 新增");
  await page.getByRole("button", { name: "并排" }).click();
  await node(page, "blk_1").focus();
  await page.keyboard.press("Enter");
  await expect(detail(page).locator(".st-cmp-side")).toHaveCount(2);
  expect(await scan(page), "zh").toEqual([]);
});

test("the studio navigation keeps Versions current on the compare page", async ({ page }) => {
  await ready(page);
  await expect(page.getByRole("link", { name: "Versions", exact: true })).toHaveAttribute("aria-current", "page");
});
