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
      // a stop inside a shadow root (the tutor launcher) is judged on the element itself, not on its host
      let el = document.activeElement as HTMLElement | null;
      while (el?.shadowRoot?.activeElement) el = el.shadowRoot.activeElement as HTMLElement;
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

/**
 * "Extra help for you" by keyboard: every control is reached with Tab, toggles with Space or Enter, is at least
 * 24 x 24 CSS px (2.5.8), and is never hidden behind the sticky player footer or header (2.4.11).
 */
const MOCK = process.env.MOCK_API_PORT ?? "4390";
const helpHost = (project: string) => base(project === "desktop" ? "coffee" : "nightsky");

test("keyboard: extra help feedback and hide", async ({ page }, info) => {
  const slug = info.project.name === "desktop" ? "coffee" : "nightsky";
  await page.request.post(`http://127.0.0.1:${MOCK}/__mock/reset?tenant=${slug}`);
  await page.goto(`${helpHost(info.project.name)}/learn/1/11`, { waitUntil: "load" });
  const helped = page.getByRole("button", { name: "This helped" });
  await helped.focus();
  await page.keyboard.press("Space");
  await expect(helped).toHaveAttribute("aria-pressed", "true");
  await page.keyboard.press("Tab");
  await expect(page.getByRole("button", { name: "Not helpful" })).toBeFocused();
  await page.keyboard.press("Tab");
  const hide = page.getByRole("button", { name: "Hide extra help for this lesson" });
  await expect(hide).toBeFocused();
  await page.keyboard.press("Enter");
  await expect(page.getByRole("button", { name: "Keep it" })).toBeFocused();
  await page.keyboard.press("Tab");
  await expect(page.getByRole("button", { name: "Yes, hide it" })).toBeFocused();
  await page.keyboard.press("Enter");
  await expect(page.locator("h1.u-player__title")).toBeFocused();
  await expect(page.getByRole("region", { name: /Extra help for you/ })).toHaveCount(0);
});

test("keyboard: the practice question is answered with the arrow keys and Check", async ({ page }, info) => {
  await page.goto(`${helpHost(info.project.name)}/learn/1/3`, { waitUntil: "load" });
  const first = page.getByLabel("Coffea arabica");
  await first.focus();
  await page.keyboard.press("ArrowDown");
  await expect(page.getByLabel("Coffea canephora")).toBeChecked();
  await page.getByRole("button", { name: "Check" }).focus();
  await page.keyboard.press("Enter");
  await expect(page.locator("ulams-practice-question [data-result]")).toContainText("Not quite.");
});

test("keyboard: every control of the cards is big enough and never behind the sticky footer or header", async ({ page }, info) => {
  await page.goto(`${helpHost(info.project.name)}/learn/1/3`, { waitUntil: "load" });
  const controls = page.locator("[data-status='ready'] button:visible, [data-status='ready'] input:visible, [data-status='ready'] textarea:visible, [data-status='ready'] summary:visible");
  const count = await controls.count();
  expect(count).toBeGreaterThan(5);
  for (let i = 0; i < count; i++) {
    const control = controls.nth(i);
    await control.focus();
    const report = await control.evaluate((el) => {
      const box = el.getBoundingClientRect();
      const label = el.closest("label")?.getBoundingClientRect();
      const bar = document.querySelector(".u-player__bar")?.getBoundingClientRect();
      const top = document.querySelector(".u-player__top")?.getBoundingClientRect();
      const overlaps = (r?: DOMRect) => Boolean(r) && box.bottom > r!.top && box.top < r!.bottom && box.right > r!.left && box.left < r!.right;
      return {
        name: `${el.tagName.toLowerCase()} ${(el.textContent ?? "").trim().slice(0, 30)}`,
        width: Math.max(box.width, label?.width ?? 0),
        height: Math.max(box.height, label?.height ?? 0),
        hiddenBy: overlaps(bar) ? "footer" : overlaps(top) ? "header" : "",
      };
    });
    expect(report.width, `${report.name} width`).toBeGreaterThanOrEqual(24);
    expect(report.height, `${report.name} height`).toBeGreaterThanOrEqual(24);
    expect(report.hiddenBy, `${report.name} is covered by the ${report.hiddenBy}`).toBe("");
  }
});

test("reduced motion: the extra help cards do not animate", async ({ page }, info) => {
  await page.goto(`${helpHost(info.project.name)}/learn/1/3`, { waitUntil: "load" });
  const animated = await page.evaluate(() => [...document.querySelectorAll("[data-status='ready'], [data-status='ready'] *")].flatMap((el) => el.getAnimations().map(() => el.tagName)));
  expect(animated, "no animation under prefers-reduced-motion").toEqual([]);
});
