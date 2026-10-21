import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * The adaptive lesson view (WCAG 2.2 AA, axe-core) against the mock tenant API: the "Lesson view" control, the
 * form without JavaScript, a reading shown in parts (keyboard, announced position), the suggestion notice with its
 * reason and "Show the whole lesson", hints shown at the start of a practice, compact density, linear navigation.
 * Light (coffee, adaptive presentation off) and dark (nightsky, derives parts and two hints) at desktop and 360 px,
 * reduced motion on. Each project works on its own tenant so their choices never meet.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const mock = process.env.MOCK_API_PORT ?? "4390";
const host = (slug: string) => `http://${slug}.app.localhost:${web}`;
const tenantOf = (project: string) => (project === "desktop" ? "coffee" : "nightsky");
const READING = "/learn/1/3";
const PRACTICE = "/learn/1/901";
const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];

test.use({ contextOptions: { reducedMotion: "reduce" } });

async function scan(page: Page) {
  const results = await new AxeBuilder({ page }).withTags(TAGS).exclude("iframe").analyze();
  return results.violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.slice(0, 3).map((n) => `${n.target.join(" ")} - ${n.any[0]?.message ?? n.failureSummary ?? ""}`).join(" | ")}`);
}

async function reset(page: Page, slug: string) {
  const response = await page.request.post(`http://127.0.0.1:${mock}/__mock/reset?tenant=${slug}`);
  expect(response.ok()).toBe(true);
}

interface View {
  density?: "Comfortable" | "Compact";
  chunking?: "Whole lesson" | "One part at a time";
  navigation?: "Course overview" | "Step by step";
  hints?: "None" | "1" | "2" | "3";
}

/** Fills the Lesson view form and saves it. */
async function saveView(page: Page, view: View) {
  if (!(await page.locator("[data-lesson-view]").evaluate((el) => (el as HTMLDetailsElement).open))) await page.locator("[data-lesson-view] summary").click();
  const panel = page.locator("[data-lesson-view] form");
  for (const [legend, value] of [
    ["Text spacing", view.density],
    ["Lesson parts", view.chunking],
    ["Navigation", view.navigation],
    ["Hints shown at the start of an exercise", view.hints],
  ] as const) {
    if (value) await panel.getByRole("group", { name: legend }).getByLabel(value, { exact: true }).check();
  }
  await panel.getByRole("button", { name: "Save lesson view" }).click();
}

const parts = (page: Page) => page.locator("ulams-parts [data-part]");

test.describe("lesson view control", () => {
  test("is a closed disclosure with four labelled groups, and passes axe closed, open and on the course page", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await reset(page, slug);
    await page.goto(`${host(slug)}${READING}`, { waitUntil: "load" });
    const summary = page.locator("[data-lesson-view] summary");
    await expect(summary).toHaveAccessibleName("Lesson view");
    await expect(page.locator("[data-lesson-view]")).not.toHaveAttribute("open", "");
    const closed = await scan(page);
    expect(closed, closed.join("\n")).toEqual([]);
    await summary.click();
    const form = page.locator("[data-lesson-view] form");
    for (const legend of ["Text spacing", "Lesson parts", "Navigation", "Hints shown at the start of an exercise"]) {
      await expect(form.getByRole("group", { name: legend })).toBeVisible();
    }
    await expect(form.getByRole("button", { name: "Reset to suggested" })).toBeVisible();
    const box = await summary.boundingBox();
    expect(box!.width).toBeGreaterThanOrEqual(24);
    expect(box!.height).toBeGreaterThanOrEqual(24);
    const open = await scan(page);
    expect(open, open.join("\n")).toEqual([]);

    await page.goto(`${host(slug)}/courses/1`, { waitUntil: "load" });
    await expect(page.locator("[data-lesson-view] summary")).toBeVisible();
    await page.locator("[data-lesson-view] summary").click();
    const course = await scan(page);
    expect(course, course.join("\n")).toEqual([]);
  });

  test("Escape closes the panel and focus returns to its button", async ({ page }, info) => {
    await page.goto(`${host(tenantOf(info.project.name))}${READING}`, { waitUntil: "load" });
    const summary = page.locator("[data-lesson-view] summary");
    await summary.focus();
    await page.keyboard.press("Enter");
    await expect(page.locator("[data-lesson-view]")).toHaveAttribute("open", "");
    await page.keyboard.press("Tab");
    await page.keyboard.press("Escape");
    await expect(page.locator("[data-lesson-view]")).not.toHaveAttribute("open", "");
    await expect(summary).toBeFocused();
  });

  test("the radios are reached with Tab and arrow keys and Save is reachable by keyboard", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await reset(page, slug);
    await page.goto(`${host(slug)}${READING}`, { waitUntil: "load" });
    await page.locator("[data-lesson-view] summary").focus();
    await page.keyboard.press("Space");
    await page.keyboard.press("Tab");
    await expect(page.locator("[data-lesson-view] form input[name=density]:checked")).toBeFocused();
    await page.keyboard.press("ArrowRight");
    await expect(page.locator("[data-lesson-view] form input[name=density][value=compact]")).toBeChecked();
    await page.locator("[data-lesson-view] form").getByRole("button", { name: "Save lesson view" }).focus();
    await page.keyboard.press("Enter");
    await expect(page.locator(".u-player")).toHaveAttribute("data-density", "compact");
    await expect(page.getByRole("status").filter({ hasText: "Your lesson view is saved." })).toBeVisible();
  });
});

test.describe("without JavaScript", () => {
  test.use({ javaScriptEnabled: false });

  test("the form saves and the lesson comes back compact and linear with every part on the page", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await reset(page, slug);
    await page.goto(`${host(slug)}${READING}`, { waitUntil: "load" });
    const gapBefore = await page.locator(".u-player__content").evaluate((el) => getComputedStyle(el).rowGap);
    await saveView(page, { density: "Compact", chunking: "One part at a time", navigation: "Step by step", hints: "1" });
    await expect(page).toHaveURL(/\/learn\/1\/3\?lesson-view=ok$/);
    await expect(page.getByRole("status").filter({ hasText: "Your lesson view is saved." })).toBeVisible();
    await expect(page.locator(".u-player")).toHaveAttribute("data-density", "compact");
    const gapAfter = await page.locator(".u-player__content").evaluate((el) => getComputedStyle(el).rowGap);
    expect(parseFloat(gapAfter)).toBeLessThan(parseFloat(gapBefore));
    await expect(page.locator(".u-player--linear")).toHaveCount(1);
    await expect(page.locator("[data-tree]")).not.toHaveAttribute("open", "");
    expect(await parts(page).count()).toBeGreaterThan(1);
    for (const part of await parts(page).all()) await expect(part).toBeVisible();
    await expect(page.locator("ulams-parts [data-nav]")).toBeHidden();
    // axe needs scripts: the same markup is scanned with JavaScript on in the other tests
  });

  test("Reset to suggested removes the learner's choices", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await reset(page, slug);
    await page.goto(`${host(slug)}${READING}`, { waitUntil: "load" });
    await saveView(page, { density: "Compact", navigation: "Step by step" });
    await expect(page.locator(".u-player")).toHaveAttribute("data-density", "compact");
    await expect(page.locator("[data-lesson-view]")).not.toHaveAttribute("open", "");
    await page.locator("[data-lesson-view] summary").click();
    await page.locator("[data-lesson-view] form").getByRole("button", { name: "Reset to suggested" }).click();
    await expect(page.locator(".u-player")).not.toHaveAttribute("data-density", "compact");
    await expect(page.locator(".u-player--linear")).toHaveCount(0);
  });
});

test.describe("a reading in parts", () => {
  test.beforeEach(async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await reset(page, slug);
    await page.goto(`${host(slug)}${READING}`, { waitUntil: "load" });
    if (slug === "coffee") await saveView(page, { chunking: "One part at a time" });
    await expect(page.locator("ulams-parts [data-nav]")).toBeVisible();
  });

  test("shows one part at a time, announces the position and moves focus to the heading; axe clean", async ({ page }) => {
    const total = await parts(page).count();
    expect(total).toBeGreaterThan(1);
    await expect(parts(page).filter({ visible: true })).toHaveCount(1);
    const status = page.locator("ulams-parts [data-status]");
    await expect(status).toHaveText(`Part 1 of ${total}`);
    await expect(page.getByRole("button", { name: "Previous part" })).toBeDisabled();
    const first = await scan(page);
    expect(first, first.join("\n")).toEqual([]);

    const next = page.getByRole("button", { name: "Next part" });
    await next.focus();
    await page.keyboard.press("Enter");
    await expect(status).toHaveText(`Part 2 of ${total}`);
    await expect(parts(page).filter({ visible: true })).toHaveCount(1);
    expect(await page.evaluate(() => document.activeElement?.tagName)).toMatch(/^H[23]$/);
    await expect(page.getByRole("button", { name: "Previous part" })).toBeEnabled();
    const second = await scan(page);
    expect(second, second.join("\n")).toEqual([]);

    for (let i = 2; i < total; i++) await next.click();
    await expect(status).toHaveText(`Part ${total} of ${total}`);
    await expect(next).toBeDisabled();
    expect(await page.evaluate(() => document.activeElement?.tagName)).toMatch(/^H[23]$/);
    await page.getByRole("button", { name: "Previous part" }).click();
    await expect(status).toHaveText(`Part ${total - 1} of ${total}`);
  });

  test("the end of the lesson is only reachable on the last part, and nothing animates under reduced motion", async ({ page }) => {
    const total = await parts(page).count();
    await expect(page.locator("[data-end-of-content]")).toBeHidden();
    for (let i = 1; i < total; i++) await page.getByRole("button", { name: "Next part" }).click();
    await expect(page.locator("[data-end-of-content]")).toBeAttached();
    expect(await page.locator("[data-end-of-content]").evaluate((el) => (el as HTMLElement).hidden)).toBe(false);
    // Reduced motion collapses durations to 0.01 ms; it does not remove the Animation objects, which stay in
    // getAnimations() until the next frame. Under load that frame arrives late, so sampling them is a race.
    // Assert the contract instead: no element has a perceptible duration, and anything still listed finishes at once.
    const slow = await page.evaluate(async () => {
      const scope = [...document.querySelectorAll("ulams-parts, ulams-parts *, [data-lesson-view], [data-lesson-view] *")];
      const ms = (value: string) => value.split(",").map((part) => (part.trim().endsWith("ms") ? parseFloat(part) : parseFloat(part) * 1000));
      const offenders = scope.flatMap((el) => {
        const style = getComputedStyle(el);
        const longest = Math.max(...ms(style.animationDuration), ...ms(style.transitionDuration));
        return longest > 1 ? [`${el.tagName} ${longest}ms`] : [];
      });
      const pending = scope.flatMap((el) => el.getAnimations());
      await Promise.all(pending.map((animation) => animation.finished.catch(() => undefined)));
      return { offenders, remaining: scope.flatMap((el) => el.getAnimations().filter((a) => a.playState === "running" && Number(a.effect?.getComputedTiming().duration) > 1).map(() => el.tagName)) };
    });
    expect(slow.offenders, "no perceptible animation or transition duration under prefers-reduced-motion").toEqual([]);
    expect(slow.remaining, "no animation keeps running under prefers-reduced-motion").toEqual([]);
  });

  test("the program starts closed and the footer's Next takes the free space once the navigation is linear", async ({ page }, info) => {
    await saveView(page, { navigation: "Step by step" });
    await expect(page.locator("[data-tree]")).not.toHaveAttribute("open", "");
    const wide = await page.locator("[data-key-next]").evaluate((el) => el.getBoundingClientRect().width);
    const prev = await page.locator("[data-key-prev]").evaluate((el) => el.getBoundingClientRect().width);
    // on a 360 px footer the three controls already fill the row; Next never gets smaller than Previous
    if (info.project.name === "desktop") expect(wide).toBeGreaterThan(prev);
    else expect(wide).toBeGreaterThanOrEqual(prev);
    await page.locator("[data-tree] summary").click();
    await expect(page.getByRole("navigation", { name: "Course program" })).toBeVisible();
    const violations = await scan(page);
    expect(violations, violations.join("\n")).toEqual([]);
  });
});

test.describe("the suggestion", () => {
  test("says why the lesson was adjusted and Show the whole lesson is remembered (adaptive tenant), or stays silent (tenant without it)", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await reset(page, slug);
    await page.goto(`${host(slug)}${READING}`, { waitUntil: "load" });
    const notice = page.getByRole("region", { name: "Lesson view" }).filter({ hasText: /adjusted|changed/ });
    if (slug !== "nightsky") {
      await expect(notice).toHaveCount(0);
      await expect(page.locator("ulams-parts")).toHaveCount(0);
      return;
    }
    await expect(notice).toContainText("We adjusted this lesson view for you.");
    await expect(page.locator("ulams-parts")).toHaveCount(1);
    await notice.getByText("Why was it adjusted?").click();
    await expect(notice.getByText("Two lessons in this module were hard")).toBeVisible();
    const open = await scan(page);
    expect(open, open.join("\n")).toEqual([]);

    await notice.getByRole("button", { name: "Show the whole lesson" }).click();
    await expect(page.locator("ulams-parts")).toHaveCount(0);
    await expect(page.getByRole("region", { name: "Lesson view" }).filter({ hasText: "You changed the suggested lesson view." })).toBeVisible();
    await page.goto(`${host(slug)}${READING}`, { waitUntil: "load" });
    await expect(page.locator("ulams-parts")).toHaveCount(0);
    const changed = await scan(page);
    expect(changed, changed.join("\n")).toEqual([]);

    await page.getByRole("button", { name: "Reset to suggested" }).first().click();
    await expect(page.locator("ulams-parts")).toHaveCount(1);
    await expect(page.getByRole("region", { name: "Lesson view" })).toContainText("We adjusted this lesson view for you.");
  });
});

test.describe("hints shown at the start", () => {
  test("a practice opens with the learner's number of hints, one tier at a time after that; axe clean", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await reset(page, slug);
    await page.goto(`${host(slug)}${PRACTICE}`, { waitUntil: "load" });
    const hints = page.locator(".u-practice__hint");
    await expect(hints).toHaveCount(slug === "nightsky" ? 2 : 0);
    if (slug === "nightsky") {
      await expect(hints.nth(0)).toContainText("Nudge: Sour usually means under-extracted.");
      await expect(hints.nth(1)).toContainText("Pointer: Look at the grind size.");
      await expect(page.getByRole("button", { name: "Show a hint (Near the solution)" })).toBeVisible();
    } else {
      await saveView(page, { hints: "3" });
      await expect(hints).toHaveCount(3);
      await expect(page.locator(".u-practice__hint-btn")).toHaveCount(0);
    }
    const violations = await scan(page);
    expect(violations, violations.join("\n")).toEqual([]);
    // setting it back to none shows the button again and no hint
    await saveView(page, { hints: "None" });
    await expect(page.locator(".u-practice__hint")).toHaveCount(0);
    await expect(page.getByRole("button", { name: "Show a hint (Nudge)" })).toBeVisible();
  });
});
