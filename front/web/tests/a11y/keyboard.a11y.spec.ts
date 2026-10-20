import { expect, test, type Page } from "@playwright/test";

/**
 * Keyboard and motion checks that axe cannot make: the skip link is the first stop and works,
 * every tab stop shows a visible focus indicator, focus never lands on something hidden, and
 * reduced motion leaves no infinite animation running.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const base = (slug: string) => `http://${slug}.app.localhost:${web}`;
const PAGES = ["/", "/courses/1", "/learn/1/3", "/account", "/login"];

test.use({ contextOptions: { reducedMotion: "reduce" } });

/** Presses Tab up to `max` times and describes each stop. */
async function tabStops(page: Page, max: number) {
  const stops: Array<{ href: string; label: string; visible: boolean; outline: boolean }> = [];
  for (let i = 0; i < max; i++) {
    await page.keyboard.press("Tab");
    const stop = await page.evaluate(() => {
      const el = document.activeElement as HTMLElement | null;
      if (!el || el === document.body) return null;
      const box = el.getBoundingClientRect();
      const style = getComputedStyle(el);
      const outline = style.outlineStyle !== "none" && parseFloat(style.outlineWidth) >= 2;
      const shadow = style.boxShadow !== "none";
      return {
        href: el.getAttribute("href") ?? "",
        label: `${el.tagName.toLowerCase()}${el.id ? `#${el.id}` : ""} ${(el.getAttribute("aria-label") ?? el.textContent ?? "").trim().slice(0, 30)}`,
        visible: box.width > 0 && box.height > 0 && style.visibility !== "hidden",
        outline: outline || shadow,
      };
    });
    if (!stop) break;
    stops.push(stop);
  }
  return stops;
}

for (const path of PAGES) {
  test(`keyboard: ${path}`, async ({ page }) => {
    await page.goto(`${base("coffee")}${path}`, { waitUntil: "load" });
    const stops = await tabStops(page, 12);
    expect(stops.length, "the page has tab stops").toBeGreaterThan(2);
    expect(stops[0]!.href, "first stop is the skip link").toBe("#main");
    const hidden = stops.filter((s) => !s.visible).map((s) => s.label);
    expect(hidden, "focus never lands on a hidden element").toEqual([]);
    const unmarked = stops.filter((s) => !s.outline).map((s) => s.label);
    expect(unmarked, "every tab stop has a visible focus indicator").toEqual([]);
  });
}

test("keyboard: the skip link moves focus into main", async ({ page }) => {
  await page.goto(`${base("coffee")}/courses/1`, { waitUntil: "load" });
  await page.keyboard.press("Tab");
  await page.keyboard.press("Enter");
  const inMain = await page.evaluate(() => Boolean(document.activeElement?.closest("main") ?? document.activeElement?.id === "main"));
  expect(inMain).toBe(true);
});

for (const path of PAGES) {
  test(`reduced motion: ${path}`, async ({ page }) => {
    await page.goto(`${base("coffee")}${path}`, { waitUntil: "load" });
    await page.waitForTimeout(500);
    const looping = await page.evaluate(() =>
      document
        .getAnimations()
        .filter((a) => a.playState === "running" && a.effect?.getComputedTiming().iterations === Infinity)
        .map((a) => (a instanceof CSSAnimation ? a.animationName : "script animation"))
    );
    expect(looping, "no looping animation under prefers-reduced-motion").toEqual([]);
  });
}
