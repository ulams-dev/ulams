import { expect, test, type Page } from "@playwright/test";

/**
 * The cross-cutting accessibility gate for every learner screen of Phase 5 (M-UI1 to M-UI6, plan section 3.9),
 * against the mock tenant API: from the skip link to the last control the keyboard reaches each screen in order,
 * every stop shows a visible focus indicator, is at least 24 x 24 CSS px (2.5.8) and is never covered by a sticky
 * bar, the demo badge or the tutor sheet (2.4.11); with reduced motion on nothing animates; the page reflows at
 * 320 px without sideways scrolling (1.4.10). The per-screen axe scans live in the other specs of this folder.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const host = (slug: string) => `http://${slug}.app.localhost:${web}`;
const slugOf = (project: string) => (project === "desktop" ? "coffee" : "nightsky");

/** [name, path]: the screens the learner reaches, in the order of the milestones that built them. */
const SCREENS: Array<[string, string]> = [
  ["lesson with extra help, AI labels and the tutor launcher (M-UI1, M-UI2, M-UI3)", "/learn/1/3"],
  ["lesson with a practice activity and its hints (M-UI4)", "/learn/1/901"],
  ["quiz lesson (tutor says it explains concepts only)", "/learn/1/900"],
  ["account summary (M-UI5)", "/account"],
  ["my training and certificates (M-UI5)", "/account/training"],
  ["my skills (M-UI5)", "/account/skills"],
  ["my team (M-UI5)", "/account/team"],
  ["personalisation settings", "/account/personalisation"],
  ["finish page with the issued certificate (M-UI5)", "/learn/2/finish"],
  ["accessibility statement (M-UI8)", "/accessibility"],
];

test.use({ contextOptions: { reducedMotion: "reduce" } });

/** The browser would ask the tenant API itself for the certificate badge; the mock API is only reachable from the server. */
const mockVerify = (page: Page) =>
  page.route("**/api/certificates/verify/**", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      headers: { "Access-Control-Allow-Origin": "*" },
      body: JSON.stringify({ data: { certificate_id: "b3f1c2d4-1111-4222-8333-444455556666", status: "valid", title: "Espresso basics", course_title: "Espresso basics", holder_name: "Demo Student", issued_at: "2026-08-01T00:00:00+00:00", expires_at: "2027-08-01T00:00:00+00:00", revoked_at: null, issuer: "Coffee Atlas" } }),
    })
  );

interface Stop {
  label: string;
  visible: boolean;
  indicator: boolean;
  width: number;
  height: number;
  covered: string;
}

/** Presses Tab up to `max` times and describes each stop; looks through the tutor's shadow root. */
async function tabStops(page: Page, max: number): Promise<Stop[]> {
  const stops: Stop[] = [];
  for (let i = 0; i < max; i++) {
    await page.keyboard.press("Tab");
    const stop = await page.evaluate(async () => {
      // let a transition that reveals the control (the skip link) finish before measuring
      await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
      await new Promise((resolve) => setTimeout(resolve, 60));
      let el = document.activeElement as HTMLElement | null;
      while (el?.shadowRoot?.activeElement) el = el.shadowRoot.activeElement as HTMLElement;
      if (!el || el === document.body) return null;
      const box = el.getBoundingClientRect();
      const style = getComputedStyle(el);
      const label = el.closest("label")?.getBoundingClientRect();
      // the point to test is the middle of the part that is on screen
      const x = Math.min(Math.max(box.left + box.width / 2, 0), window.innerWidth - 1);
      const y = Math.min(Math.max(box.top + Math.min(box.height / 2, 12), 0), window.innerHeight - 1);
      let top = document.elementFromPoint(x, y);
      while (top?.shadowRoot) {
        const inner = top.shadowRoot.elementFromPoint(x, y);
        if (!inner || inner === top) break;
        top = inner;
      }
      const reaches = (node: Element | null) => Boolean(node) && (node === el || el!.contains(node) || Boolean(node!.closest("label")?.contains(el)));
      return {
        label: `${el.tagName.toLowerCase()}${el.id ? `#${el.id}` : ""} ${(el.getAttribute("aria-label") ?? el.textContent ?? "").trim().slice(0, 30)}`,
        visible: box.width > 0 && box.height > 0 && style.visibility !== "hidden",
        indicator: (style.outlineStyle !== "none" && parseFloat(style.outlineWidth) >= 2) || style.boxShadow !== "none",
        // a bare radio or checkbox may be small when its label is the target
        width: Math.max(box.width, label?.width ?? 0),
        height: Math.max(box.height, label?.height ?? 0),
        covered: reaches(top) ? "" : `${top?.tagName.toLowerCase() ?? "nothing"}${top?.className ? `.${String(top.className).split(" ")[0]}` : ""}`,
      };
    });
    if (!stop) break;
    stops.push(stop);
  }
  return stops;
}

function expectUsable(stops: Stop[], where: string) {
  expect(stops.length, `${where}: tab stops`).toBeGreaterThan(2);
  expect(stops.filter((s) => !s.visible).map((s) => s.label), `${where}: focus never lands on a hidden element`).toEqual([]);
  expect(stops.filter((s) => !s.indicator).map((s) => s.label), `${where}: every stop has a visible focus indicator`).toEqual([]);
  // links inside a sentence or a footer line are exempt from the minimum size (the inline exception, which axe's target-size rule applies); controls are not
  expect(stops.filter((s) => !s.label.startsWith("a ") && (s.width < 24 || s.height < 24)).map((s) => `${s.label} ${Math.round(s.width)}x${Math.round(s.height)}`), `${where}: controls are at least 24 x 24 (2.5.8)`).toEqual([]);
  expect(stops.filter((s) => s.covered).map((s) => `${s.label} is behind ${s.covered}`), `${where}: focus is not obscured (2.4.11)`).toEqual([]);
}

for (const [name, path] of SCREENS) {
  test(`keyboard and reflow: ${name}`, async ({ page }, info) => {
    const slug = path === "/learn/2/finish" ? "coffee" : slugOf(info.project.name);
    await page.request.post(`http://127.0.0.1:${process.env.MOCK_API_PORT ?? "4390"}/__mock/reset?tenant=${slug}`);
    await mockVerify(page);
    const response = await page.goto(`${host(slug)}${path}`, { waitUntil: "load" });
    expect(response?.status(), `HTTP status of ${path}`).toBe(200);
    if (path.endsWith("/finish")) await expect(page.locator("ulams-certificate-badge section")).toBeVisible();
    await page.keyboard.press("Tab");
    expect(await page.evaluate(() => document.activeElement?.getAttribute("href")), "first stop is the skip link").toBe("#main");
    await page.keyboard.press("Shift+Tab");
    const stops = await tabStops(page, 60);
    expectUsable(stops, path);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth), "no sideways scroll").toBe(true);
  });

  test(`reduced motion: ${name}`, async ({ page }, info) => {
    const slug = path === "/learn/2/finish" ? "coffee" : slugOf(info.project.name);
    await page.goto(`${host(slug)}${path}`, { waitUntil: "load" });
    await page.waitForTimeout(600);
    const moving = await page.evaluate(() =>
      document
        .getAnimations()
        .filter((a) => a.playState === "running" && Number(a.effect?.getComputedTiming().activeDuration ?? 0) > 50)
        .map((a) => (a instanceof CSSAnimation ? a.animationName : a instanceof CSSTransition ? `transition ${a.transitionProperty}` : "script animation"))
    );
    expect(moving, "nothing animates under prefers-reduced-motion").toEqual([]);
  });
}

test("keyboard: with the tutor open every control of the page and of the panel is reachable and not covered", async ({ page }, info) => {
  const slug = slugOf(info.project.name);
  await page.goto(`${host(slug)}/learn/1/3`, { waitUntil: "load" });
  await page.locator("ulams-tutor button.launcher").click();
  await expect(page.locator("[data-tutor-panel] dialog")).toBeVisible();
  // focus starts in the panel, Tab leaves it after the last control (the sheet is not modal) and goes on through the page:
  // no stop of either may sit behind the sheet
  const inPanel = await tabStops(page, 5);
  expect(inPanel.length, "Tab leaves the sheet after its last control").toBe(1);
  expect(inPanel.map((s) => s.label.split(" ")[0]), "focus starts on the question field; the next stop is the send button").toEqual(["button"]);
  const stops = [...inPanel, ...(await tabStops(page, 30))];
  expectUsable(stops, "tutor open");
  // Escape works from inside the sheet (it is not modal): go back to the question field first
  await page.locator("[data-tutor-panel] textarea").focus();
  await page.keyboard.press("Escape");
  await expect(page.locator("[data-tutor-panel] dialog")).toBeHidden();
  expect(await page.evaluate(() => {
    let el = document.activeElement as HTMLElement | null;
    while (el?.shadowRoot?.activeElement) el = el.shadowRoot.activeElement as HTMLElement;
    return el?.classList.contains("launcher") ?? false;
  }), "Escape returns focus to the launcher").toBe(true);
});

test("reduced motion: the tutor panel opens and shows an answer without any animation", async ({ page }, info) => {
  const slug = slugOf(info.project.name);
  await page.goto(`${host(slug)}/learn/1/3`, { waitUntil: "load" });
  await page.locator("ulams-tutor button.launcher").click();
  await page.locator("[data-tutor-panel] textarea").fill("Which species is called Robusta?");
  await page.keyboard.press("Control+Enter");
  await expect(page.locator("[data-tutor-panel] .msg.tutor")).toBeVisible();
  const moving = await page.evaluate(() => {
    const roots: Array<Document | ShadowRoot> = [document];
    document.querySelectorAll("*").forEach((el) => el.shadowRoot && roots.push(el.shadowRoot));
    return roots.flatMap((r) => r.getAnimations()).filter((a) => a.playState === "running" && Number(a.effect?.getComputedTiming().activeDuration ?? 0) > 50).map((a) => (a instanceof CSSAnimation ? a.animationName : "other"));
  });
  expect(moving).toEqual([]);
});

/* Streamed answers (M-UI6): the draft and the failed-run states on the tenant that streams (coffee), at both widths. */
async function askStreaming(page: Page, text: string) {
  await page.goto(`${host("coffee")}/learn/1/3`, { waitUntil: "load" });
  await page.locator("ulams-tutor button.launcher").click();
  await expect(page.locator("[data-tutor-panel] [data-quota]")).toHaveText("29 of 30 questions left today");
  await page.locator("[data-tutor-panel] textarea").fill(text);
  await page.keyboard.press("Control+Enter");
}

test("keyboard: while a streamed draft is written every control stays reachable, visible and not covered", async ({ page }) => {
  await askStreaming(page, "Which species is called Robusta?");
  await expect(page.locator("[data-tutor-panel] [data-draft]")).toBeVisible();
  // the question field keeps focus while the answer is written; the stops after it (the send button is disabled,
  // so on a wide screen focus moves on into the page, on a phone it stays in the modal) must be usable and uncovered
  const stops = await tabStops(page, 12);
  expect(stops.filter((s) => !s.visible || !s.indicator || s.covered || (!s.label.startsWith("a ") && (s.width < 24 || s.height < 24))).map((s) => s.label), "tutor draft").toEqual([]);
  await expect(page.locator("[data-tutor-panel] .msg.tutor")).toBeVisible();
});

test("keyboard: after a failed run the retry button is reachable and not covered", async ({ page }) => {
  await askStreaming(page, "This will boom");
  const retry = page.locator("[data-tutor-panel] .error button", { hasText: "Try again" });
  await expect(retry).toBeVisible();
  await retry.focus();
  const stops = await tabStops(page, 4);
  expect(stops.filter((s) => !s.visible || !s.indicator || s.covered).map((s) => s.label)).toEqual([]);
});

test("reduced motion: a streamed draft appears and is replaced by the final answer without any animation", async ({ page }) => {
  await askStreaming(page, "Which species is called Robusta?");
  await expect(page.locator("[data-tutor-panel] [data-draft]")).toBeVisible();
  const moving = () =>
    page.evaluate(() => {
      const roots: Array<Document | ShadowRoot> = [document];
      document.querySelectorAll("*").forEach((el) => el.shadowRoot && roots.push(el.shadowRoot));
      return roots.flatMap((r) => r.getAnimations()).filter((a) => a.playState === "running" && Number(a.effect?.getComputedTiming().activeDuration ?? 0) > 50).map((a) => (a instanceof CSSAnimation ? a.animationName : "other"));
    });
  expect(await moving(), "while the draft is shown").toEqual([]);
  await expect(page.locator("[data-tutor-panel] .msg.tutor")).toBeVisible();
  expect(await moving(), "after the final answer").toEqual([]);
});
