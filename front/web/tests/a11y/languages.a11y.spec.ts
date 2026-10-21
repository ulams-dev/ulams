import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * The learner pages in English, Polish and Chinese (P5R-1), light and dark, desktop and 360 px, against the
 * mock tenant API. The tenant default language is a public setting; the mock academies have no course language
 * of their own, so the pages follow it (ADR 0106, amended):
 *   en  coffee (light) and nightsky (dark)   pl  polski and nocpl   zh  zhongwen and noczh
 * Each screen is checked for `<html lang>`, a sample of the translated chrome, and axe (WCAG 2.2 AA).
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const host = (slug: string) => `http://${slug}.app.localhost:${web}`;

const COURSE = 1;
const READING = 3;
const QUIZ = 900;
const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];

test.use({ contextOptions: { reducedMotion: "reduce" } });

interface Language {
  code: "en" | "pl" | "zh";
  tenants: Array<[string, string]>;
  /** What the chrome of each screen says in the language. */
  text: {
    next: string;
    start: RegExp;
    quizStart: string;
    quizOf: RegExp;
    finishBack: string;
    myCourses: string;
    signIn: string;
    password: string;
    program: string;
    openCourse: string;
    markComplete: string;
  };
}

const LANGUAGES: Language[] = [
  {
    code: "en",
    tenants: [["light", "coffee"], ["dark", "nightsky"]],
    text: { next: "Next topic", start: /Start learning|Continue learning/, quizStart: "Start the quiz", quizOf: /^Question 1 of \d+$/, finishBack: "Back to the course", myCourses: "My courses", signIn: "Sign in", password: "Password", program: "Course program", openCourse: "Open the course", markComplete: "Mark as complete" },
  },
  {
    code: "pl",
    tenants: [["light", "polski"], ["dark", "nocpl"]],
    text: { next: "Następny temat", start: /Zacznij naukę|Kontynuuj naukę/, quizStart: "Rozpocznij quiz", quizOf: /^Pytanie 1 z \d+$/, finishBack: "Wróć do kursu", myCourses: "Moje kursy", signIn: "Zaloguj się", password: "Hasło", program: "Program kursu", openCourse: "Otwórz kurs", markComplete: "Oznacz jako ukończone" },
  },
  {
    code: "zh",
    tenants: [["light", "zhongwen"], ["dark", "noczh"]],
    text: { next: "下一个主题", start: /开始学习|继续学习/, quizStart: "开始测验", quizOf: /^第 1 题，共 \d+ 题$/, finishBack: "返回课程", myCourses: "我的课程", signIn: "登录", password: "密码", program: "课程大纲", openCourse: "打开课程", markComplete: "标记为已完成" },
  },
];

async function revealAll(page: Page) {
  await page.evaluate(async () => {
    for (let y = 0; y < document.body.scrollHeight; y += 600) {
      window.scrollTo({ top: y, behavior: "instant" });
      await new Promise((r) => setTimeout(r, 40));
    }
    window.scrollTo({ top: 0, behavior: "instant" });
  });
  await page.waitForTimeout(500);
}

async function scan(page: Page) {
  const results = await new AxeBuilder({ page }).withTags(TAGS).exclude("iframe").analyze();
  return results.violations.map(
    (v) => `${v.id} (${v.impact}): ${v.nodes.slice(0, 3).map((n) => `${n.target.join(" ")} - ${n.any[0]?.message ?? n.failureSummary ?? ""}`).join(" | ")}`
  );
}

async function open(page: Page, slug: string, path: string, lang: string) {
  const response = await page.goto(`${host(slug)}${path}`, { waitUntil: "load" });
  expect(response?.status(), `HTTP status of ${path}`).toBe(200);
  await expect(page.locator("html")).toHaveAttribute("lang", lang);
  await revealAll(page);
}

async function clean(page: Page) {
  const violations = await scan(page);
  expect(violations, violations.join("\n")).toEqual([]);
}

for (const language of LANGUAGES) {
  for (const [mode, slug] of language.tenants) {
    const label = `${language.code} ${mode}`;
    const t = language.text;

    test(`${label}: course page`, async ({ page }) => {
      await open(page, slug, `/courses/${COURSE}`, language.code);
      await expect(page.getByRole("link", { name: t.start }).first()).toBeVisible();
      // the purchase card is a catalogue component: it takes the page language from the app, not from the document
      await expect(page.locator(".u-buy__cta").first()).toContainText(t.openCourse);
      await clean(page);
    });

    test(`${label}: lesson player`, async ({ page }) => {
      await open(page, slug, `/learn/${COURSE}/${READING}`, language.code);
      await expect(page.getByRole("link", { name: t.next })).toBeVisible();
      await expect(page.getByRole("button", { name: t.markComplete })).toBeVisible();
      // the program sits in a closed <details> on a phone: look for it in the markup, not in the accessibility tree
      await expect(page.locator(`nav[aria-label="${t.program}"]`)).toHaveCount(1);
      await clean(page);
    });

    test(`${label}: quiz intro and question`, async ({ page }) => {
      await open(page, slug, `/learn/${COURSE}/${QUIZ}`, language.code);
      await expect(page.getByRole("button", { name: t.quizStart })).toBeVisible();
      await clean(page);
      await page.click("[data-start]");
      await expect(page.locator(".u-quiz__q legend")).toBeVisible();
      await expect(page.locator(".u-quiz__progress span").first()).toHaveText(t.quizOf);
      await clean(page);
    });

    test(`${label}: finish`, async ({ page }) => {
      await open(page, slug, `/learn/${COURSE}/finish`, language.code);
      await expect(page.getByRole("link", { name: t.finishBack })).toBeVisible();
      await clean(page);
    });

    test(`${label}: account`, async ({ page }) => {
      await open(page, slug, "/account", language.code);
      await expect(page.getByRole("heading", { name: t.myCourses })).toBeVisible();
      await clean(page);
    });

    test(`${label}: login`, async ({ page }) => {
      await open(page, slug, "/login", language.code);
      await expect(page.getByRole("heading", { level: 1, name: t.signIn })).toBeVisible();
      await expect(page.getByLabel(t.password)).toBeVisible();
      await clean(page);
    });
  }
}
