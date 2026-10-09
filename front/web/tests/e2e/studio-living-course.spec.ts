import { fileURLToPath } from "node:url";
import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * The quality-bar end-to-end test of the Living Course on the fake LLM driver: a course is built
 * from coffee-brewing.v1.md and published, a learner completes the lesson that teaches the brew
 * ratio and its quiz through the learner API, the author uploads coffee-brewing.v2.md on the
 * Sources page, reviews the proposal (accepts everything except one item, asks for changes on
 * another), applies it, and the learner then sees the update notice and the re-attempt notice while
 * completion and score are exactly what they were. The audit page shows detection, decisions and
 * apply with the source revision and the version numbers, and the chain verifies.
 *
 * A second test tries to connect a web source that the API refuses (a private address) and checks
 * the readable refusal.
 *
 * Needs the setup of README-studio.md plus a learner (STUDIO_LEARNER_EMAIL / STUDIO_LEARNER_PASSWORD).
 * Skipped unless STUDIO_E2E=1.
 */
const base = process.env.STUDIO_BASE_URL ?? "http://e2e.app.localhost:4329";
const api = process.env.STUDIO_API_URL ?? "http://127.0.0.1:18081";
const email = process.env.STUDIO_AUTHOR_EMAIL ?? "author@e2e.test";
const password = process.env.STUDIO_AUTHOR_PASSWORD ?? "e2e-secret";
const learnerEmail = process.env.STUDIO_LEARNER_EMAIL ?? "learner@e2e.test";
const learnerPassword = process.env.STUDIO_LEARNER_PASSWORD ?? "e2e-learner-secret";
const fixtureDir = fileURLToPath(new URL("../../../../api/packages/living-course/resources/fixtures/", import.meta.url));
const v1Path = `${fixtureDir}coffee-brewing.v1.md`;
const v2Path = `${fixtureDir}coffee-brewing.v2.md`;

test.skip(process.env.STUDIO_E2E !== "1", "set STUDIO_E2E=1 with the fake-driver API running");
test.describe.configure({ mode: "serial" });

async function axe(page: Page, name: string): Promise<void> {
  const result = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"]).analyze();
  expect(result.violations.map((v) => `${name}: ${v.id} ${v.nodes.map((n) => n.target.join(" ")).join(", ")}`)).toEqual([]);
}

/** Lets the entrance animations of the learner pages finish: axe reads colours mid-fade otherwise. */
async function settle(page: Page): Promise<void> {
  await page.waitForTimeout(1200);
}

async function noHorizontalScroll(page: Page, name: string): Promise<void> {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow, `${name}: horizontal scroll`).toBeLessThanOrEqual(1);
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
  expect(response.ok, `${method} ${path} → ${response.status} ${text.slice(0, 300)}`).toBe(true);
  return (JSON.parse(text) as { data: T }).data;
}

async function signIn(page: Page, target: string): Promise<void> {
  await page.goto(`${base}${target}`);
  if (/\/studio\/login/.test(page.url())) {
    await page.getByLabel("E-mail").fill(email);
    await page.getByLabel("Password").fill(password);
    await page.getByRole("button", { name: "Sign in" }).click();
    await page.goto(`${base}${target}`);
  }
}

interface ApiTopic {
  id: number;
  title: string;
  topicable_type: string;
  topicable_id: number;
  topicable?: { id?: number; value?: string } | null;
}
interface ApiQuestion {
  id: number;
  type: string;
  options?: { answers?: string[] };
}
interface ApiAttempt {
  id: number;
  questions?: ApiQuestion[];
  result_score?: unknown;
  result_percent?: unknown;
  is_ended?: boolean;
}
interface ApiProposal {
  id: string;
  status: string;
  appliedAt: string | null;
}
interface ApiProgress {
  topic_id: number;
  status: number;
}

let sessionId = "";
let courseId = 0;
let proposalId = "";
let learnerToken = "";
let lessonTopics: ApiTopic[] = [];
let quizTopics: ApiTopic[] = [];
const attempts: Array<{ id: number; topicId: number }> = [];
let scoresBefore: unknown[] = [];
let progressBefore: ApiProgress[] = [];

test.describe("update with learner progress intact", () => {
  test.beforeAll(async ({ browser }) => {
    test.setTimeout(300_000);
    const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await context.newPage();
    await signIn(page, "/studio/new");
    await page.locator("[data-file]").setInputFiles(v1Path);
    await expect(page).toHaveURL(/\/studio\/s\/[0-9a-z]{26}$/);
    sessionId = page.url().split("/").pop()!;
    const open = page.locator("form.cb-question-open");
    await expect(open).toBeVisible({ timeout: 30_000 });
    await open.getByRole("button", { name: "Decide for me" }).click();
    await page.getByRole("button", { name: "Decide the rest for me" }).click();
    const outline = page.getByRole("region", { name: "Proposed outline" }).last();
    await outline.getByRole("button", { name: "Approve outline & generate" }).click({ timeout: 60_000 });
    await page.getByRole("button", { name: "Apply to my academy" }).click({ timeout: 120_000 });
    await page.getByRole("link", { name: "See your course" }).click({ timeout: 60_000 });
    await expect(page.getByRole("heading", { name: "Your course is ready." })).toBeVisible();
    const href = await page.getByRole("link", { name: "Preview as learner" }).getAttribute("href");
    courseId = Number(href!.split("/").pop());
    const acknowledge = page.getByLabel(/I have read the \d+ warning/);
    await expect(page.getByRole("button", { name: "Publish course" })).toBeVisible({ timeout: 30_000 });
    if (await acknowledge.isVisible()) await acknowledge.check();
    await page.getByRole("button", { name: "Publish course" }).click();
    await expect(page.getByText("The course is published")).toBeVisible({ timeout: 30_000 });
    await context.close();
  });

  test("a learner completes the lesson on the brew ratio and its quiz", async () => {
    test.setTimeout(120_000);
    const author = await login(email, password);
    const me = await call<{ id: number }>(await login(learnerEmail, learnerPassword), "GET", "/api/profile/me");
    await call(author, "POST", `/api/admin/courses/${courseId}/access/add`, { users: [me.id] });
    learnerToken = await login(learnerEmail, learnerPassword);

    // the lesson that teaches the ratio (the one the new source revision corrects): its texts and quizzes
    const program = await call<{ lessons: Array<{ title: string; topics: ApiTopic[] }> }>(learnerToken, "GET", `/api/courses/${courseId}/program`);
    const lesson = program.lessons.find((l) => l.topics.some((t) => JSON.stringify(t.topicable ?? {}).includes("1:16")));
    expect(lesson, "a lesson that mentions the ratio 1:16").toBeTruthy();
    lessonTopics = lesson!.topics.filter((t) => !t.topicable_type.endsWith("GiftQuiz"));
    quizTopics = lesson!.topics.filter((t) => t.topicable_type.endsWith("GiftQuiz"));
    expect(lessonTopics.length).toBeGreaterThan(0);
    expect(quizTopics.length).toBeGreaterThan(0);

    // progress ping and completion of every text of the lesson
    for (const topic of lessonTopics) {
      await call(learnerToken, "PUT", `/api/courses/progress/${topic.id}/ping`);
    }
    await call(learnerToken, "PATCH", `/api/courses/progress/${courseId}`, { progress: lessonTopics.map((t) => ({ topic_id: t.id, status: 1 })) });

    // each quiz of the lesson: one attempt, every question answered, attempt ended, quiz topic completed
    for (const quiz of quizTopics) {
      const attempt = await call<ApiAttempt>(learnerToken, "POST", "/api/quiz-attempts", { topic_gift_quiz_id: quiz.topicable_id });
      const questions = attempt.questions ?? [];
      expect(questions.length, `questions of ${quiz.title}`).toBeGreaterThan(0);
      await call(learnerToken, "POST", "/api/quiz-answers/all", {
        topic_gift_quiz_attempt_id: attempt.id,
        answers: questions.map((q) => ({ topic_gift_question_id: q.id, answer: { text: q.options?.answers?.[1] ?? "" } })),
      });
      await call(learnerToken, "POST", `/api/quiz-attempts/${attempt.id}/end`);
      attempts.push({ id: attempt.id, topicId: quiz.id });
    }
    await call(learnerToken, "PATCH", `/api/courses/progress/${courseId}`, { progress: quizTopics.map((t) => ({ topic_id: t.id, status: 1 })) });

    scoresBefore = await scoresOf();
    progressBefore = await progressOf();
    const completed = progressBefore.filter((p) => p.status === 1).map((p) => p.topic_id);
    expect(completed).toEqual(expect.arrayContaining(lessonTopics.map((t) => t.id)));
    expect((scoresBefore as Array<{ percent: unknown }>).every((s) => s.percent !== null && s.percent !== undefined), JSON.stringify(scoresBefore)).toBe(true);
  });

  test("the author uploads version 2 on the Sources page and the proposal is ready", async ({ page }) => {
    test.setTimeout(180_000);
    await signIn(page, `/studio/s/${sessionId}/sources`);
    await expect(page.getByRole("heading", { name: "Sources", level: 1 })).toBeVisible();
    await expect(page.locator("[data-source-block]").first()).toBeVisible({ timeout: 30_000 });
    await axe(page, "sources: before the upload");
    await noHorizontalScroll(page, "sources: before the upload");
    await page.locator("[data-file]").setInputFiles(v2Path);
    await expect(page.locator(".st-upload-status").getByText(/Revision 2 added/)).toBeVisible({ timeout: 60_000 });
    await expect(page.locator("[data-source-block]").getByText("New version available")).toBeVisible();
    await axe(page, "sources: revision 2");
    await noHorizontalScroll(page, "sources: revision 2");

    const author = await login(email, password);
    await expect
      .poll(async () => {
        const proposals = await call<ApiProposal[]>(author, "GET", `/api/admin/living-course/sessions/${sessionId}/proposals`);
        if (proposals[0] && ["ready", "awaiting_analysis"].includes(proposals[0].status)) proposalId = proposals[0].id;
        return proposalId;
      }, { timeout: 90_000 })
      .not.toBe("");
  });

  test("review: accept all but one item, ask for changes on another, apply", async ({ page }) => {
    test.setTimeout(300_000);
    await signIn(page, `/studio/s/${sessionId}/updates/${proposalId}`);
    await expect(page.getByRole("heading", { name: /Source update: revision 1 → 2/ })).toBeVisible({ timeout: 30_000 });
    const analyse = page.getByRole("button", { name: /^Analyse/ });
    if (await analyse.isVisible()) {
      await analyse.click();
      await page.getByRole("button", { name: "Start the analysis" }).click();
    }
    await expect(page.locator("[data-update-item]").first()).toBeVisible({ timeout: 90_000 });
    await expect(page.locator("[data-analysing]")).toHaveCount(0, { timeout: 90_000 });
    await expect(page.getByRole("region", { name: "Impact of this update" })).toBeVisible();
    await axe(page, "review: ready");
    await noHorizontalScroll(page, "review: ready");

    await page.getByRole("button", { name: "Accept all" }).click();
    const items = page.locator("[data-update-item]");
    await expect(items.first()).toHaveAttribute("data-status", "accepted", { timeout: 30_000 });

    // one item the author does not want yet: it goes back to undecided and stays out of the apply
    const decided = items.filter({ hasNot: page.getByText("Answer changed") }).filter({ has: page.locator('[data-decision="accepted"][aria-pressed="true"]') });
    expect(await decided.count(), "several accepted items without a corrected answer").toBeGreaterThan(1);
    const heldId = (await decided.last().getAttribute("data-update-item"))!;
    const askedId = (await decided.first().getAttribute("data-update-item"))!;
    expect(askedId).not.toBe(heldId);
    const held = page.locator(`[data-update-item="${heldId}"]`);
    const heldLabel = (await held.locator("h4").first().textContent()) ?? "";
    await held.getByRole("button", { name: "Undo my decision" }).click({ timeout: 15_000 });
    await expect(held).toHaveAttribute("data-status", "pending");

    // another item: ask for a new version
    const asked = page.locator(`[data-update-item="${askedId}"]`);
    await asked.getByRole("button", { name: "Ask for changes" }).click({ timeout: 15_000 });
    await asked.getByLabel("What should change?").fill("Keep it short and keep the example.");
    await asked.getByRole("button", { name: "Ask for a new version" }).click();
    await expect(asked.getByText(/Asked for changes 1 of 3 times/)).toBeVisible({ timeout: 60_000 });
    await axe(page, "review: decisions");
    await noHorizontalScroll(page, "review: decisions");

    // the regenerated item may be undecided again; the rest is accepted
    const apply = page.getByRole("button", { name: /^Apply \d+ accepted changes?$/ });
    await expect(apply).toBeEnabled({ timeout: 30_000 });
    expect(heldLabel.length).toBeGreaterThan(0);
    await apply.click();
    // publishing the course touched it outside the builder: the apply asks before it overwrites those changes
    const overwrite = page.getByRole("button", { name: "Overwrite and apply" });
    const outcome = await Promise.race([
      overwrite.waitFor({ timeout: 30_000 }).then(() => "overwrite"),
      page.locator("[data-done]").waitFor({ timeout: 30_000 }).then(() => "done"),
    ]).catch(() => "none");
    if (outcome === "overwrite") {
      await expect(page.getByText(/edited in the admin after the last apply/)).toBeVisible();
      await axe(page, "review: overwrite confirmation");
      await overwrite.click();
    }
    await expect
      .poll(async () => ((await page.locator("[data-done]").count()) > 0 ? "done" : `waiting; alerts: ${(await page.getByRole("alert").allTextContents()).join(" | ")}`), { timeout: 120_000 })
      .toBe("done");
    await expect(page.locator("[data-done]")).toContainText("The update is applied");
    await axe(page, "review: applied");

    const author = await login(email, password);
    const proposal = await call<ApiProposal>(author, "GET", `/api/admin/living-course/proposals/${proposalId}`);
    expect(proposal.status).toBe("applied");
  });

  test("the learner sees the update and the re-attempt notice; completion and score are unchanged", async ({ page }, info) => {
    test.setTimeout(120_000);
    // sign in as the learner in the browser
    await page.goto(`${base}/login?next=${encodeURIComponent(`/learn/${courseId}/${lessonTopics[0]!.id}`)}`);
    await page.getByLabel("E-mail").fill(learnerEmail);
    await page.getByLabel("Password").fill(learnerPassword);
    await page.getByRole("button", { name: "Sign in", exact: true }).click();
    await expect(page).toHaveURL(new RegExp(`/learn/${courseId}/\\d+`));

    // the update notice sits on the lessons whose text changed in a way that matters
    const updated: number[] = [];
    for (const topic of lessonTopics) {
      await page.goto(`${base}/learn/${courseId}/${topic.id}`);
      await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
      if (await page.getByRole("heading", { name: /Updated since you completed it/ }).isVisible()) {
        updated.push(topic.id);
        await expect(page.getByRole("button", { name: "Mark as reviewed" })).toBeVisible();
        await expect(page.locator(".u-player__done")).toBeVisible();
        await settle(page);
        await axe(page, `learner: lesson ${topic.id} with the update notice`);
        await noHorizontalScroll(page, `learner: lesson (${info.project.name})`);
      }
    }
    expect(updated.length, "a completed lesson shows the update notice").toBeGreaterThan(0);

    // the corrected question: the quiz says so, the previous score stays on record
    const reattempt: number[] = [];
    for (const quiz of quizTopics) {
      await page.goto(`${base}/learn/${courseId}/${quiz.id}`);
      await expect(page.getByRole("heading", { level: 1 })).toBeVisible();
      if (await page.getByText("One question was corrected.").isVisible()) {
        reattempt.push(quiz.id);
        await expect(page.getByText(/Your previous score stays on record/)).toBeVisible();
        await settle(page);
        await axe(page, `learner: quiz ${quiz.id} with the re-attempt notice`);
        await noHorizontalScroll(page, `learner: quiz (${info.project.name})`);
      }
    }
    expect(reattempt.length, "a quiz with a corrected question shows the re-attempt notice").toBeGreaterThan(0);

    // nothing the learner earned changed
    // completion is exactly what it was (rows of topics the update retired, which were never completed, are no longer listed)
    expect((await progressOf()).filter((p) => p.status === 1)).toEqual(progressBefore.filter((p) => p.status === 1));
    expect(await scoresOf()).toEqual(scoresBefore);
  });

  test("the audit page shows detection, decisions and apply, and the chain verifies", async ({ page }) => {
    test.setTimeout(120_000);
    await signIn(page, `/studio/s/${sessionId}/audit`);
    await expect(page.getByRole("heading", { name: "Audit trail", level: 1 })).toBeVisible();
    await expect(page.locator("[data-chain]")).toContainText(/Chain verified: \d+ entries? checked/, { timeout: 30_000 });
    const table = page.getByRole("table", { name: /Audit trail, page 1 of/ });
    await expect(table).toBeVisible();
    const codes = await table.locator("tr.cb-audit-row .cb-audit-code").allTextContents();
    // newest first: the apply, the decisions (accepted, reset, a new version asked for) and the notices come first
    expect(codes).toEqual(expect.arrayContaining(["proposal.applied", "item.accepted", "item.reset", "item.regenerated", "notice.created"]));
    expect(codes.indexOf("proposal.applied")).toBeLessThan(codes.indexOf("item.accepted"));
    await axe(page, "audit");
    await noHorizontalScroll(page, "audit");

    // the apply entry names the source revision and the course versions it moved between
    const applied = table.locator("tr.cb-audit-row", { hasText: "proposal.applied" }).first();
    const toggle = applied.getByRole("button", { name: /^Details for entry/ });
    await toggle.click();
    const detail = page.locator(`#${await toggle.getAttribute("aria-controls")}`);
    await expect(detail).toBeVisible();
    await expect(detail).toContainText(/Source revision|revision/i);
    await expect(detail).toContainText(/v\d+ to v\d+/);
    await axe(page, "audit: apply record");

    // detection and the connection are on the filtered views
    const pick = async (label: string, code: RegExp): Promise<void> => {
      await page.getByLabel("Action", { exact: true }).selectOption({ label });
      await page.getByRole("button", { name: "Apply filters" }).click();
      await expect
        .poll(async () => {
          const found = await table.locator("tr.cb-audit-row .cb-audit-code").allTextContents();
          return found.length > 0 && found.every((c) => code.test(c));
        })
        .toBe(true);
    };
    await pick("Source revisions", /^revision\./);
    await expect(table.locator(".cb-audit-code", { hasText: "revision.detected" })).toBeVisible();
    await pick("Connections", /^connection\./);
    await expect(table.locator(".cb-audit-code", { hasText: "connection.created" })).toBeVisible();
    await axe(page, "audit: connection");
    await noHorizontalScroll(page, "audit: filtered");
  });
});

test("connecting a source the API refuses shows the reason and creates nothing", async ({ page }) => {
  test.setTimeout(90_000);
  await signIn(page, "/studio/new");
  const form = page.locator("[data-url-form]");
  await expect(form.getByRole("heading", { name: "Add web pages" })).toBeVisible();
  await axe(page, "new: connect forms");
  await noHorizontalScroll(page, "new: connect forms");
  await form.getByLabel("Page addresses").fill("https://127.0.0.1/handbook");
  await form.getByRole("button", { name: "Add the pages" }).click();
  const refusal = form.getByRole("alert");
  await expect(refusal).toContainText(/private address|127\.0\.0\.1/i, { timeout: 60_000 });
  await expect(page).toHaveURL(/\/studio\/new$/);
  await axe(page, "new: refusal");

  // the form is usable again, and the repository form refuses a malformed name before asking the API
  await expect(form.getByRole("button", { name: "Add the pages" })).toBeEnabled();
  const git = page.locator("[data-git-form]");
  await git.getByLabel("Repository").fill("docs");
  await git.getByRole("button", { name: "Connect the repository" }).click();
  await expect(git.getByRole("alert")).toContainText("owner/name");
});

/** Score and percent of every attempt the learner made before the update. */
async function scoresOf(): Promise<unknown[]> {
  const out: unknown[] = [];
  for (const attempt of attempts) {
    const row = await call<ApiAttempt>(learnerToken, "GET", `/api/quiz-attempts/${attempt.id}`);
    out.push({ id: attempt.id, score: row.result_score, percent: row.result_percent });
  }
  return out;
}

async function progressOf(): Promise<ApiProgress[]> {
  const rows = await call<Array<{ topic_id: number; status: number }>>(learnerToken, "GET", `/api/courses/progress/${courseId}`);
  return rows.map((r) => ({ topic_id: r.topic_id, status: r.status })).sort((a, b) => a.topic_id - b.topic_id);
}
