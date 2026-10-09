import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * WCAG 2.2 AA scan (axe-core) of every page type. Third-party frames (the H5P service,
 * YouTube, PDF viewer, SCORM player) are excluded: they are not rendered by this app.
 */
const port = process.env.WEB_BASE_PORT ?? "4321";
const t = (slug: string, path = "/") => `http://${slug}.app.localhost:${port}${path}`;

const PAGES: Array<[string, string]> = [
  ["platform landing", `http://app.localhost:${port}/`],
  ["coffee landing", t("coffee")],
  ["oncall landing", t("oncall")],
  ["nightsky landing", t("nightsky")],
  ["coffee course", t("coffee", "/courses/1")],
  ["oncall course", t("oncall", "/courses/1")],
  ["nightsky course", t("nightsky", "/courses/2")],
  ["lesson: reading + math", t("coffee", "/learn/1/11")],
  ["lesson: video", t("coffee", "/learn/1/1")],
  ["lesson: H5P", t("coffee", "/learn/1/5")],
  ["lesson: quiz", t("coffee", "/learn/1/16")],
  ["lesson: audio", t("coffee", "/learn/1/4")],
  ["lesson: image", t("coffee", "/learn/1/2")],
  ["lesson: PDF", t("coffee", "/learn/1/9")],
  ["lesson: embed", t("coffee", "/learn/1/7")],
  ["lesson: SCORM", t("coffee", "/learn/1/10")],
  ["lesson: cmi5", t("coffee", "/learn/1/15")],
  ["lesson: project", t("coffee", "/learn/1/17")],
  ["lesson: oncall table", t("oncall", "/learn/1/4")],
  ["lesson: nightsky preview", t("nightsky", "/learn/2/16")],
  ["finish", t("coffee", "/learn/1/finish")],
  ["account", t("coffee", "/account")],
  ["events", t("oncall", "/events")],
  ["webinar", t("oncall", "/events/webinar/1")],
  ["in-person event", t("coffee", "/events/in-person/1")],
  ["consultation", t("oncall", "/events/consultation/1")],
  ["login", t("nightsky", "/login")],
  ["not found", t("coffee", "/nope")],
];

test.use({ contextOptions: { reducedMotion: "reduce" } });

/** Scroll through the page so scroll-revealed sections reach their final state before the scan. */
async function revealAll(page: Page) {
  await page.evaluate(async () => {
    for (let y = 0; y < document.body.scrollHeight; y += 600) {
      window.scrollTo({ top: y, behavior: "instant" });
      await new Promise((r) => setTimeout(r, 40));
    }
    window.scrollTo({ top: 0, behavior: "instant" });
  });
  await page.waitForTimeout(1000);
}

for (const [name, url] of PAGES) {
  test(`axe: ${name}`, async ({ page }) => {
    // "load", not "networkidle": embedded players (YouTube) keep the network busy
    await page.goto(url, { waitUntil: "load" });
    await revealAll(page);
    const results = await new AxeBuilder({ page })
      .withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"])
      .exclude("iframe")
      .analyze();
    const summary = results.violations.map(
      (v) => `${v.id} (${v.impact}): ${v.nodes.slice(0, 3).map((n) => `${n.target.join(" ")} — ${n.any[0]?.message ?? ""}`).join(" | ")}`
    );
    expect(summary, summary.join("\n")).toEqual([]);
  });
}

test("axe: quiz question screen", async ({ page }) => {
  await page.goto(t("coffee", "/learn/1/16"));
  await page.click("[data-start]");
  const question = page.locator(".u-quiz__q legend");
  const noAttempts = page.locator(".u-quiz__error");
  await expect(question.or(noAttempts)).toBeVisible({ timeout: 20_000 });
  const results = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21aa", "wcag22aa"]).exclude("iframe").analyze();
  expect(results.violations.map((v) => `${v.id}: ${v.nodes[0]?.target.join(" ")}`)).toEqual([]);
});
