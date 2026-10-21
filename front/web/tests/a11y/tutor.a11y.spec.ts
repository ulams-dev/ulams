import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * The course tutor panel on the lesson page (WCAG 2.2 AA, axe-core) against the mock tenant API: the
 * launcher, the empty panel, an answer with citations, the earlier conversations. Light and dark theme at
 * desktop and 360 px phone width (the Playwright projects), reduced motion on. On a wide screen the
 * panel is a non-modal side sheet, on a phone a full-screen dialog.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const host = (slug: string) => `http://${slug}.app.localhost:${web}`;
const LESSON = "/learn/1/3";
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

/** The element that has focus, looking through the tutor's shadow root. */
const deepFocus = (page: Page) =>
  page.evaluate(() => {
    let el = document.activeElement as HTMLElement | null;
    while (el?.shadowRoot?.activeElement) el = el.shadowRoot.activeElement as HTMLElement;
    return el ? `${el.tagName.toLowerCase()}${el.classList.contains("launcher") ? ".launcher" : ""}` : "";
  });

for (const [theme, slug] of THEMES) {
  test(`tutor ${theme}: launcher, panel, answer and earlier conversations pass axe`, async ({ page }) => {
    await page.goto(`${host(slug)}${LESSON}`, { waitUntil: "load" });
    const launcher = page.locator("ulams-tutor button.launcher");
    await expect(launcher).toBeVisible();
    await expect(launcher).toHaveAccessibleName("Ask the course tutor");
    expect(await scan(page), "closed").toEqual([]);

    await launcher.click();
    const dialog = page.locator("[data-tutor-panel] dialog");
    await expect(dialog).toBeVisible();
    await expect(dialog).toHaveAccessibleName("Course tutor");
    expect(await deepFocus(page), "focus goes to the question field").toBe("textarea");
    await expect(page.locator("[data-tutor-panel] [data-quota]")).toHaveText("29 of 30 questions left today");
    await expect(page.locator("[data-tutor-panel] [data-conversation]")).toHaveCount(1);
    expect(await scan(page), "open and empty").toEqual([]);

    await page.locator("[data-tutor-panel] textarea").fill("Which species is called Robusta?");
    await page.keyboard.press("Control+Enter");
    const answer = page.locator("[data-tutor-panel] .msg.tutor");
    await expect(answer).toBeVisible();
    await expect(answer.locator(".ai")).toHaveText("AI tutor · answers only from this course");
    await expect(answer.locator(".sources a")).toHaveAttribute("href", "/learn/1/3");
    await expect(page.locator("[data-tutor-panel] [data-quota]")).toHaveText("28 of 30 questions left today");
    expect(await scan(page), "with an answer").toEqual([]);

    await page.locator("[data-tutor-panel] summary").click();
    await expect(page.locator("[data-tutor-panel] [data-conversation]")).toBeVisible();
    await page.locator("[data-tutor-panel] [data-conversation] button", { hasText: "Delete" }).click();
    await expect(page.locator("[data-tutor-panel] [data-conversation]")).toContainText("Delete this conversation for good?");
    expect(await scan(page), "earlier conversations with a confirmation").toEqual([]);
  });
}

test("tutor: Escape closes the panel and focus returns to the launcher", async ({ page }) => {
  await page.goto(`${host("coffee")}${LESSON}`, { waitUntil: "load" });
  const launcher = page.locator("ulams-tutor button.launcher");
  await launcher.click();
  await expect(page.locator("[data-tutor-panel] dialog")).toBeVisible();
  await page.keyboard.press("Escape");
  await expect(page.locator("[data-tutor-panel] dialog")).toBeHidden();
  expect(await deepFocus(page)).toBe("button.launcher");
  await expect(launcher).toHaveAttribute("aria-expanded", "false");
});

test("tutor: a side sheet next to the lesson on a wide screen, a full-screen dialog on a phone", async ({ page }) => {
  await page.goto(`${host("coffee")}${LESSON}`, { waitUntil: "load" });
  await page.locator("ulams-tutor button.launcher").click();
  const box = await page.locator("[data-tutor-panel] dialog").boundingBox();
  const viewport = page.viewportSize()!;
  if (viewport.width >= 720) {
    expect(box!.width).toBeLessThanOrEqual(420);
    expect(box!.x + box!.width).toBeCloseTo(viewport.width, 0);
    // the lesson stays readable and reachable: the main column is pushed left of the sheet
    const main = await page.locator("main").boundingBox();
    expect(main!.x + main!.width).toBeLessThanOrEqual(box!.x + 1);
  } else {
    expect(box!.width).toBeCloseTo(viewport.width, 0);
    expect(box!.height).toBeCloseTo(viewport.height, 0);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
  }
});
