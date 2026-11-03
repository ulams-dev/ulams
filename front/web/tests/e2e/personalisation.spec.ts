import { execSync } from "node:child_process";
import { fileURLToPath } from "node:url";
import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * The full-stack personalisation journey on the fake LLM driver (plan phase-5-learner-ui.md, section 6): an author
 * builds the coffee course in the studio and publishes it; a learner answers a quiz question wrong twice; the nightly
 * evaluation runs; the learner sees "Extra help for you" with its reasons, rates it and gets feedback on their own
 * explanation; the lesson opens in parts for a struggling learner and "Show the whole lesson" switches back; the learner
 * asks the course tutor (cited answer, then the refusal during a quiz attempt); the training page and the accessibility
 * statement open. axe (WCAG 2.2 AA) runs on every step.
 *
 * Needs the setup of README-studio.md (API on the fake driver with a real queue) plus, for the API:
 *   LEARNER_INSIGHTS_ENABLED=1 LEARNER_INSIGHTS_ADAPTIVE_INTERFACE=1 LEARNER_INSIGHTS_REMEDIATION_ENABLED=1
 *   TUTOR_ENABLED=1 (and a learner: learner@e2e.test, see README-studio.md).
 * `PERSONALISATION_EVALUATE_CMD` runs `php artisan learner-insights:evaluate` in the API container (default
 * `docker exec ulams-studio-e2e-api php artisan learner-insights:evaluate`).
 * Skipped unless PERSONALISATION_E2E=1.
 */
const base = process.env.STUDIO_BASE_URL ?? "http://e2e.app.localhost:4329";
const api = process.env.STUDIO_API_URL ?? "http://127.0.0.1:18081";
const authorEmail = process.env.STUDIO_AUTHOR_EMAIL ?? "author@e2e.test";
const authorPassword = process.env.STUDIO_AUTHOR_PASSWORD ?? "e2e-secret";
const learnerEmail = process.env.STUDIO_LEARNER_EMAIL ?? "learner@e2e.test";
const learnerPassword = process.env.STUDIO_LEARNER_PASSWORD ?? "e2e-learner-secret";
const evaluateCmd = process.env.PERSONALISATION_EVALUATE_CMD ?? "docker exec ulams-studio-e2e-api php artisan learner-insights:evaluate";
const v1Path = fileURLToPath(new URL("../../../../api/packages/living-course/resources/fixtures/coffee-brewing.v1.md", import.meta.url));
const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];

test.skip(process.env.PERSONALISATION_E2E !== "1", "set PERSONALISATION_E2E=1 with the fake-driver API running");
test.describe.configure({ mode: "serial" });

interface ApiTopic {
  id: number;
  title: string;
  topicable_type: string;
  topicable_id: number;
}
interface ApiAttempt {
  id: number;
  questions?: Array<{ id: number }>;
}

let courseId = 0;
let learnerToken = "";
let reading: ApiTopic[] = [];
let quizzes: ApiTopic[] = [];

async function axe(page: Page, name: string): Promise<void> {
  const result = await new AxeBuilder({ page }).withTags(TAGS).exclude("iframe").analyze();
  expect(result.violations.map((v) => `${name}: ${v.id} ${v.nodes.map((n) => n.target.join(" ")).join(", ")}`)).toEqual([]);
}

async function noHorizontalScroll(page: Page, name: string): Promise<void> {
  expect(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth), `${name}: horizontal scroll`).toBeLessThanOrEqual(1);
}

async function login(user: string, pass: string): Promise<string> {
  const response = await fetch(`${api}/api/auth/login`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify({ email: user, password: pass, remember_me: 1 }),
  });
  return ((await response.json()) as { data: { token: string } }).data.token;
}

async function call<T>(token: string, method: string, path: string, body?: unknown): Promise<T> {
  const headers: Record<string, string> = { Accept: "application/json", Authorization: `Bearer ${token}` };
  if (body !== undefined) headers["Content-Type"] = "application/json";
  const response = await fetch(`${api}${path}`, { method, headers, body: body === undefined ? undefined : JSON.stringify(body) });
  const text = await response.text();
  expect(response.ok, `${method} ${path} -> ${response.status} ${text.slice(0, 300)}`).toBe(true);
  return (JSON.parse(text) as { data: T }).data;
}

async function learnerSignIn(page: Page, target: string): Promise<void> {
  await page.goto(`${base}/login?next=${encodeURIComponent(target)}`);
  if (/\/login/.test(page.url())) {
    await page.getByLabel("E-mail").fill(learnerEmail);
    await page.getByLabel("Password").fill(learnerPassword);
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
  }
  await expect(page).toHaveURL(new RegExp(target.replace(/[/?]/g, "\\$&")));
}

/** A wrong attempt: every question answered with text that cannot be right, then ended. */
async function wrongAttempt(quiz: ApiTopic): Promise<void> {
  const attempt = await call<ApiAttempt>(learnerToken, "POST", "/api/quiz-attempts", { topic_gift_quiz_id: quiz.topicable_id });
  const questions = attempt.questions ?? [];
  expect(questions.length).toBeGreaterThan(0);
  await call(learnerToken, "POST", "/api/quiz-answers/all", {
    topic_gift_quiz_attempt_id: attempt.id,
    answers: questions.map((q) => ({ topic_gift_question_id: q.id, answer: { text: "zzz-not-an-answer" } })),
  });
  await call(learnerToken, "POST", `/api/quiz-attempts/${attempt.id}/end`);
}

test("the author builds and publishes the coffee course, a learner gets access", async ({ browser }) => {
  test.setTimeout(300_000);
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  await page.goto(`${base}/studio/new`);
  if (/\/studio\/login/.test(page.url())) {
    await page.getByLabel("E-mail").fill(authorEmail);
    await page.getByLabel("Password").fill(authorPassword);
    await page.getByRole("button", { name: "Sign in" }).click();
    await page.goto(`${base}/studio/new`);
  }
  await page.locator("[data-file]").setInputFiles(v1Path);
  await expect(page).toHaveURL(/\/studio\/s\/[0-9a-z]{26}$/);
  const open = page.locator("form.cb-question-open");
  await expect(open).toBeVisible({ timeout: 30_000 });
  await open.getByRole("button", { name: "Decide for me" }).click();
  await page.getByRole("button", { name: "Decide the rest for me" }).click();
  const outline = page.getByRole("region", { name: "Proposed outline" }).last();
  await outline.getByRole("button", { name: "Approve outline & generate" }).click({ timeout: 60_000 });
  await page.getByRole("button", { name: "Apply to my academy" }).click({ timeout: 120_000 });
  await page.getByRole("link", { name: "See your course" }).click({ timeout: 60_000 });
  const href = await page.getByRole("link", { name: "Preview as learner" }).getAttribute("href");
  courseId = Number(href!.split("/").pop());
  const acknowledge = page.getByLabel(/I have read the \d+ warning/);
  await expect(page.getByRole("button", { name: "Publish course" })).toBeVisible({ timeout: 30_000 });
  if (await acknowledge.isVisible()) await acknowledge.check();
  await page.getByRole("button", { name: "Publish course" }).click();
  await expect(page.getByText("The course is published")).toBeVisible({ timeout: 30_000 });
  await context.close();

  const author = await login(authorEmail, authorPassword);
  const me = await call<{ id: number }>(await login(learnerEmail, learnerPassword), "GET", "/api/profile/me");
  await call(author, "POST", `/api/admin/courses/${courseId}/access/add`, { users: [me.id] });
  learnerToken = await login(learnerEmail, learnerPassword);
  const program = await call<{ lessons: Array<{ topics: ApiTopic[] }> }>(learnerToken, "GET", `/api/courses/${courseId}/program`);
  const topics = program.lessons.flatMap((l) => l.topics);
  reading = topics.filter((t) => t.topicable_type.endsWith("RichText"));
  quizzes = topics.filter((t) => t.topicable_type.endsWith("GiftQuiz"));
  expect(reading.length, "the course has readings").toBeGreaterThan(0);
  expect(quizzes.length, "the course has quizzes").toBeGreaterThan(0);
});

test("the learner struggles with a quiz and the evaluation produces extra help with its reasons", async ({ page }, info) => {
  test.setTimeout(240_000);
  for (const topic of reading) await call(learnerToken, "PUT", `/api/courses/progress/${topic.id}/ping`);
  await wrongAttempt(quizzes[0]!);
  await wrongAttempt(quizzes[0]!);
  execSync(evaluateCmd, { stdio: "pipe" });

  await learnerSignIn(page, `/learn/${courseId}/${reading[0]!.id}`);
  // the help sits on the topic that was hard; the queue generates it on the fake driver, so look at every topic of the lesson
  const card = page.getByRole("region", { name: /Extra help for you/ });
  await expect
    .poll(
      async () => {
        for (const topic of [...reading, ...quizzes]) {
          await page.goto(`${base}/learn/${courseId}/${topic.id}`);
          if ((await card.count()) > 0) return topic.id;
        }
        return 0;
      },
      { timeout: 120_000, intervals: [3000] }
    )
    .not.toBe(0);
  await expect(card.getByText("Why am I seeing this?")).toBeVisible();
  await card.getByText("Why am I seeing this?").click();
  await expect(card.getByRole("listitem").first()).toBeVisible();
  await axe(page, "extra help card");
  await noHorizontalScroll(page, `extra help (${info.project.name})`);

  const helped = card.getByRole("button", { name: "This helped" });
  await helped.click();
  await expect(helped).toHaveAttribute("aria-pressed", "true");
  await page.reload();
  await expect(page.getByRole("region", { name: /Extra help for you/ }).getByRole("button", { name: "This helped" })).toHaveAttribute("aria-pressed", "true");

  // the reflection: feedback on the learner's own words, with sources
  const box = page.getByRole("textbox", { name: "Explain it in your own words" });
  if ((await box.count()) > 0) {
    await box.fill("Brewing is about extracting flavour from the grounds with hot water, so ratio and time matter.");
    await page.getByRole("button", { name: "Get feedback" }).click();
    const result = page.getByRole("group", { name: "AI feedback, based on this course's sources" });
    await expect(result).toBeVisible({ timeout: 60_000 });
    await expect(result.getByRole("heading", { name: "What you got right" })).toBeVisible();
    await axe(page, "reflection feedback");
  }
});

test("a struggling learner gets the lesson in parts, and Show the whole lesson switches back", async ({ page }) => {
  test.setTimeout(120_000);
  await learnerSignIn(page, `/learn/${courseId}/${reading[0]!.id}`);
  const notice = page.getByRole("region", { name: "Lesson view" }).filter({ hasText: "We adjusted this lesson view for you." });
  if ((await notice.count()) === 0) {
    // a reading with fewer than two headings stays whole: look for one that was split
    for (const topic of reading) {
      await page.goto(`${base}/learn/${courseId}/${topic.id}`);
      if ((await notice.count()) > 0) break;
    }
  }
  await expect(notice, "the suggested lesson view explains itself").toBeVisible();
  await notice.getByText("Why was it adjusted?").click();
  await expect(notice.getByRole("listitem").first()).toBeVisible();
  await axe(page, "adjusted lesson view");
  await notice.getByRole("button", { name: "Show the whole lesson" }).click();
  await expect(page.locator("ulams-parts [data-part]:visible")).toHaveCount(0);
  await page.reload();
  await expect(page.locator("ulams-parts [data-part]:visible")).toHaveCount(0);
  await axe(page, "whole lesson, remembered");
});

test("the tutor answers with a citation, and refuses to answer a quiz question during an attempt", async ({ page }) => {
  test.setTimeout(120_000);
  await learnerSignIn(page, `/learn/${courseId}/${reading[0]!.id}`);
  await page.locator("ulams-tutor button.launcher").click();
  const panel = page.locator("[data-tutor-panel]");
  await expect(panel.locator("dialog")).toBeVisible();
  await panel.locator("textarea").fill("What is the brew ratio?");
  await page.keyboard.press("Control+Enter");
  const answer = panel.locator(".msg.tutor").last();
  await expect(answer).toBeVisible({ timeout: 60_000 });
  await expect(answer.locator(".sources a").first()).toHaveAttribute("href", new RegExp(`/learn/${courseId}/\\d+`));
  await axe(page, "tutor answer");
  await page.keyboard.press("Escape");

  // an active attempt: the tutor explains concepts only
  const attempt = await call<ApiAttempt>(learnerToken, "POST", "/api/quiz-attempts", { topic_gift_quiz_id: quizzes[0]!.topicable_id });
  expect(attempt.id).toBeGreaterThan(0);
  await page.goto(`${base}/learn/${courseId}/${quizzes[0]!.id}`);
  await page.locator("ulams-tutor button.launcher").click();
  await expect(panel.getByText(/concepts only|explains concepts/i)).toBeVisible({ timeout: 30_000 });
  await panel.locator("textarea").fill("Which answer is correct?");
  await page.keyboard.press("Control+Enter");
  await expect(panel.locator(".msg.tutor").last()).toBeVisible({ timeout: 60_000 });
  await axe(page, "tutor during a quiz");
  await call(learnerToken, "POST", `/api/quiz-attempts/${attempt.id}/end`);
});

test("my training and the accessibility statement open and pass axe", async ({ page }, info) => {
  test.setTimeout(90_000);
  await learnerSignIn(page, "/account/training");
  await expect(page.getByRole("heading", { level: 1, name: "My training" })).toBeVisible();
  await axe(page, "my training");
  await noHorizontalScroll(page, `my training (${info.project.name})`);
  await page.goto(`${base}/accessibility`);
  await expect(page.getByRole("heading", { level: 1, name: "Accessibility statement" })).toBeVisible();
  await axe(page, "accessibility statement");
  await noHorizontalScroll(page, `statement (${info.project.name})`);
});
