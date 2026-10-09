import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";
import { demoCourse } from "./demo-data.ts";

/**
 * WCAG 2.2 AA scan (axe-core) of every page type. Third-party frames (the H5P service,
 * YouTube, PDF viewer, SCORM player) are excluded: they are not rendered by this app.
 */
const port = process.env.WEB_BASE_PORT ?? "4321";
const t = (slug: string, path = "/") => `http://${slug}.app.localhost:${port}${path}`;

/** [name, tenant, path]; "{course}" and "{Kind}" are replaced with ids from the API. */
const PAGES: Array<[string, string, string]> = [
  ["platform landing", "", "/"],
  ["coffee landing", "coffee", "/"],
  ["oncall landing", "oncall", "/"],
  ["nightsky landing", "nightsky", "/"],
  ["gravity landing", "gravity", "/"],
  ["poland landing", "poland", "/"],
  ["ulam landing", "ulam", "/"],
  ["coffee course", "coffee", "/courses/{course}"],
  ["oncall course", "oncall", "/courses/{course}"],
  ["nightsky course", "nightsky", "/courses/{course}"],
  ["gravity course", "gravity", "/courses/{course}"],
  ["poland course", "poland", "/courses/{course}"],
  ["ulam course", "ulam", "/courses/{course}"],
  ["lesson: reading + math", "coffee", "/learn/{course}/{RichText}"],
  ["lesson: video", "coffee", "/learn/{course}/{Video}"],
  ["lesson: H5P", "coffee", "/learn/{course}/{H5P}"],
  ["lesson: quiz", "coffee", "/learn/{course}/{GiftQuiz}"],
  ["lesson: audio", "coffee", "/learn/{course}/{Audio}"],
  ["lesson: image", "coffee", "/learn/{course}/{Image}"],
  ["lesson: PDF", "coffee", "/learn/{course}/{PDF}"],
  ["lesson: embed", "coffee", "/learn/{course}/{OEmbed}"],
  ["lesson: SCORM", "coffee", "/learn/{course}/{ScormSco}"],
  ["lesson: cmi5", "coffee", "/learn/{course}/{Cmi5Au}"],
  ["lesson: project", "coffee", "/learn/{course}/{Project}"],
  ["lesson: oncall reading", "oncall", "/learn/{course}/{RichText}"],
  ["lesson: nightsky", "nightsky", "/learn/{course}/{Video}"],
  ["lesson: gravity", "gravity", "/learn/{course}/{RichText}"],
  ["lesson: poland", "poland", "/learn/{course}/{RichText}"],
  ["lesson: ulam", "ulam", "/learn/{course}/{RichText}"],
  ["lesson: gravity interactive", "gravity", "/learn/{course}/{InteractiveTopic}"],
  ["lesson: gravity layout", "gravity", "/learn/{course}/{LayoutTopic}"],
  ["lesson: gravity quiz", "gravity", "/learn/{course}/{GiftQuiz}"],
  ["finish", "coffee", "/learn/{course}/finish"],
  ["account", "coffee", "/account"],
  ["events", "oncall", "/events"],
  ["webinar", "oncall", "/events/webinar/1"],
  ["in-person event", "coffee", "/events/in-person/1"],
  ["consultation", "oncall", "/events/consultation/1"],
  ["login", "nightsky", "/login"],
  ["not found", "coffee", "/nope"],
];

async function resolve(slug: string, path: string): Promise<string | null> {
  if (!slug) return `http://app.localhost:${port}${path}`;
  if (!path.includes("{")) return t(slug, path);
  const demo = await demoCourse(slug);
  let missing = false;
  const filled = path.replace(/\{(\w+)\}/g, (_, key: string) => {
    if (key === "course") return String(demo.courseId);
    const id = demo.topics[key];
    if (!id) missing = true;
    return String(id);
  });
  return missing ? null : t(slug, filled);
}

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

for (const [name, slug, path] of PAGES) {
  test(`axe: ${name}`, async ({ page }) => {
    const url = await resolve(slug, path);
    test.skip(!url, `no such topic in the seeded course: ${path}`);
    // "load", not "networkidle": embedded players (YouTube) keep the network busy
    await page.goto(url!, { waitUntil: "load" });
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
  await page.goto((await resolve("coffee", "/learn/{course}/{GiftQuiz}"))!);
  await page.click("[data-start]");
  const question = page.locator(".u-quiz__q legend");
  const noAttempts = page.locator(".u-quiz__error");
  await expect(question.or(noAttempts)).toBeVisible({ timeout: 20_000 });
  const results = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21aa", "wcag22aa"]).exclude("iframe").analyze();
  expect(results.violations.map((v) => `${v.id}: ${v.nodes[0]?.target.join(" ")}`)).toEqual([]);
});
