import { fileURLToPath } from "node:url";
import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * End-to-end Course Builder on the fake LLM driver (synthetic answers, no network):
 * sign in → upload Markdown → interview with "Decide for me" → edit an objective and approve the
 * outline → generation → approve the apply → the course exists in the API → chat edit of a quiz
 * question → approve → change visible → undo. axe (WCAG 2.2 AA) on every studio screen.
 *
 * Needs an API with AI_DRIVER=fake and an author account, and this app pointing at it; see
 * tests/e2e/README-studio.md. Skipped unless STUDIO_E2E=1.
 */
const base = process.env.STUDIO_BASE_URL ?? "http://e2e.app.localhost:4329";
const api = process.env.STUDIO_API_URL ?? "http://127.0.0.1:18081";
const email = process.env.STUDIO_AUTHOR_EMAIL ?? "author@e2e.test";
const password = process.env.STUDIO_AUTHOR_PASSWORD ?? "e2e-secret";
const fixture = fileURLToPath(new URL("../../../../api/packages/course-builder/resources/fixtures/coffee-brewing.md", import.meta.url));

test.skip(process.env.STUDIO_E2E !== "1", "set STUDIO_E2E=1 with the fake-driver API running");
test.describe.configure({ mode: "serial" });
// one run creates a course: desktop only
// Playwright requires the destructuring pattern for fixtures
// eslint-disable-next-line no-empty-pattern
test.beforeEach(({}, info) => test.skip(info.project.name !== "desktop", "desktop only"));

async function axe(page: Page, name: string, scope?: string): Promise<void> {
  const builder = new AxeBuilder({ page });
  if (scope) builder.include(scope);
  const result = await builder.withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"]).analyze();
  expect(result.violations.map((v) => `${name}: ${v.id} ${v.nodes.map((n) => n.target.join(" ")).join(", ")}`)).toEqual([]);
}

async function apiToken(): Promise<string> {
  const response = await fetch(`${api}/api/auth/login`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify({ email, password, remember_me: 1 }),
  });
  return ((await response.json()) as { data: { token: string } }).data.token;
}

async function apiGet<T>(path: string): Promise<T> {
  const response = await fetch(`${api}${path}`, { headers: { Accept: "application/json", Authorization: `Bearer ${await apiToken()}` } });
  expect(response.ok, `${path} → ${response.status}`).toBe(true);
  return ((await response.json()) as { data: T }).data;
}

let builtSession = "";
let builtCourse = 0;

test("build a course from a document, edit a question in chat, undo", async ({ page }) => {
  test.setTimeout(180_000);

  // sign in as the author
  await page.goto(`${base}/studio`);
  await expect(page).toHaveURL(/\/studio\/login/);
  await axe(page, "login");
  await page.getByLabel("E-mail").fill(email);
  await page.getByLabel("Password").fill(password);
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page.getByRole("heading", { name: "Course Builder" })).toBeVisible();
  await axe(page, "sessions");

  // start and upload
  await page.getByRole("link", { name: "Build a course from a document" }).click();
  await expect(page.getByRole("heading", { name: /Drop Markdown, PDF or DOCX/ })).toBeVisible();
  await axe(page, "start");
  await page.locator("[data-file]").setInputFiles(fixture);
  await expect(page).toHaveURL(/\/studio\/s\/[0-9a-z]{26}$/);
  const sessionId = page.url().split("/").pop()!;
  builtSession = sessionId;

  // interview: one question decided for me, then the rest
  const open = page.locator("form.cb-question-open");
  await expect(open).toBeVisible({ timeout: 30_000 });
  await expect(page.locator(".cb-source").getByText("Ready to cite")).toBeVisible();
  await axe(page, "interview");
  await open.getByRole("button", { name: "Decide for me" }).click();
  await expect(page.locator(".cb-question-answered").first()).toBeVisible();
  await page.getByRole("button", { name: "Decide the rest for me" }).click();

  // the brief panel is editable: price is free by default, the same control sets a paid price
  const briefPanel = page.locator(".st-brief");
  await expect(briefPanel.locator("dd").filter({ hasText: /^Free/ })).toBeVisible();
  await briefPanel.getByRole("button", { name: "Edit price" }).click();
  await axe(page, "brief editor", ".st-brief");
  await briefPanel.locator("label", { hasText: /^Paid$/ }).click();
  await briefPanel.getByLabel(/^Price \(/).fill("49");
  await briefPanel.getByRole("button", { name: "Save" }).click();
  await expect(briefPanel.locator("dd").filter({ hasText: /^49\.00 USD/ })).toBeVisible();

  // outline review: edit one objective, approve
  const outline = page.getByRole("region", { name: "Proposed outline" }).last();
  await expect(outline.getByRole("button", { name: "Approve outline & generate" })).toBeVisible({ timeout: 30_000 });
  await expect(outline.locator(".cb-cite").first()).toBeVisible();
  await axe(page, "outline review");
  await outline.getByRole("button", { name: /^Edit objective/ }).first().click();
  await outline.getByRole("textbox", { name: "Objective", exact: true }).fill("Explain what extraction means in one sentence");
  await outline.getByRole("button", { name: "Save" }).click();
  await outline.getByRole("button", { name: "Approve outline & generate" }).click();

  // generation progress, then the apply proposal
  await expect(page.getByRole("region", { name: "Generation progress" })).toBeVisible({ timeout: 60_000 });
  const apply = page.getByRole("button", { name: "Apply to my academy" });
  await expect(apply).toBeVisible({ timeout: 90_000 });
  await axe(page, "progress and apply");
  await apply.click();
  await page.getByRole("link", { name: "See your course" }).click({ timeout: 60_000 });

  // success screen and the course in the LMS
  await expect(page.getByRole("heading", { name: "Your course is ready." })).toBeVisible();
  await axe(page, "success");
  const href = await page.getByRole("link", { name: "Preview as learner" }).getAttribute("href");
  const courseId = Number(href!.split("/").pop());
  builtCourse = courseId;
  const course = await apiGet<{ id: number; title: string; status: string }>(`/api/admin/courses/${courseId}`);
  expect(course.status).toBe("draft");
  const state = await apiGet<{ session: { currentVersionId: string } }>(`/api/admin/course-builder/sessions/${sessionId}`);
  const version = await apiGet<{ document: { modules: Array<{ lessons: Array<{ quiz: { questions: Array<{ id: string; options: Array<{ text: string }> }> } | null }> }> } }>(
    `/api/admin/course-builder/versions/${state.session.currentVersionId}`
  );
  const question = version.document.modules[0]!.lessons[0]!.quiz!.questions[0]!;

  // author preview of the unpublished course: banner, lesson page, nothing tracked, not for others
  await page.goto(`${base}/preview/courses/${courseId}`);
  await expect(page.getByText("Preview: not published")).toBeVisible();
  await expect(page.getByRole("link", { name: "Back to the studio" })).toHaveAttribute("href", `/studio/s/${sessionId}/done`);
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute("content", /noindex/);
  await axe(page, "course preview");
  const lessonHref = await page.locator('a[href^="/preview/courses/"]').first().getAttribute("href");
  await page.goto(`${base}${lessonHref}`);
  await expect(page.getByText("Preview: not published")).toBeVisible();
  await expect(page.locator("ulams-progress")).toHaveCount(0);
  const anonymous = await fetch(`${base}/preview/courses/${courseId}`);
  expect(anonymous.status).toBe(404);
  expect(anonymous.headers.get("cache-control")).toBe("private, no-store");

  // workspace: chat edit of a quiz question
  await page.goto(`${base}/studio/s/${sessionId}/workspace`);
  await page.locator(`[data-element="${question.id}"]`).click();
  await expect(page.getByText(/^Editing: /)).toBeVisible();
  await axe(page, "workspace");
  await page.getByLabel("Change request").fill("make the distractors less obvious");
  await page.getByRole("button", { name: "Propose a change" }).click();
  const diff = page.locator(".cb-diff").last();
  await expect(diff.getByRole("button", { name: "Approve" })).toBeVisible({ timeout: 30_000 });
  await expect(diff.locator("ins").first()).toBeVisible();
  await diff.getByRole("button", { name: "Approve" }).click();
  await expect(page.locator(".cb-diff").last().getByText("Approved and applied")).toBeVisible({ timeout: 30_000 });

  const patched = await apiGet<{ session: { currentVersionId: string; appliedVersionId: string } }>(`/api/admin/course-builder/sessions/${sessionId}`);
  expect(patched.session.appliedVersionId).toBe(patched.session.currentVersionId);
  const after = await apiGet<{ kind: string }>(`/api/admin/course-builder/versions/${patched.session.currentVersionId}`);
  expect(after.kind).toBe("patch");

  // undo goes back to the generated content
  await page.getByRole("button", { name: "Undo" }).click();
  await expect
    .poll(async () => (await apiGet<{ session: { currentVersionId: string } }>(`/api/admin/course-builder/sessions/${sessionId}`)).session.currentVersionId, { timeout: 30_000 })
    .toBe(state.session.currentVersionId);
});

test("preview and discuss: select a quiz question, ask, approve, the element updates, undo", async ({ page }) => {
  test.setTimeout(120_000);
  expect(builtSession, "the build test runs first").not.toBe("");
  const state = await apiGet<{ session: { currentVersionId: string } }>(`/api/admin/course-builder/sessions/${builtSession}`);
  const version = await apiGet<{ document: { modules: Array<{ id: string; lessons: Array<{ id: string; quiz: { id: string; questions: Array<{ id: string }> } }> }> } }>(
    `/api/admin/course-builder/versions/${state.session.currentVersionId}`
  );
  const lesson = version.document.modules[0]!.lessons[0]!;
  const questionId = lesson.quiz.questions[0]!.id;

  // the entry point: the success page (each test has its own browser, so sign in again)
  await page.goto(`${base}/studio/login`);
  await page.getByLabel("E-mail").fill(email);
  await page.getByLabel("Password").fill(password);
  await page.getByRole("button", { name: "Sign in" }).click();
  await expect(page.getByRole("heading", { name: "Course Builder" })).toBeVisible();
  await page.goto(`${base}/studio/s/${builtSession}/done`);
  await expect(page.getByRole("link", { name: "Preview and discuss" })).toBeVisible();
  await page.getByRole("link", { name: "Preview and discuss" }).click();
  await expect(page.getByRole("heading", { name: "Preview and discuss" })).toBeVisible();
  const frame = page.frameLocator("iframe[data-frame]");
  await expect(frame.locator("[data-blueprint-id]").first()).toBeVisible({ timeout: 30_000 });
  await axe(page, "preview course page");

  // the learner pages mark every element with its blueprint id
  await page.goto(`${base}/studio/s/${builtSession}/preview/${lesson.id}`);
  await expect(frame.locator(`[data-blueprint-id="${lesson.id}"]`)).toBeVisible({ timeout: 30_000 });
  await expect(frame.locator('[data-blueprint-type="block"]').first()).toBeVisible();
  await expect(frame.locator("ulams-progress")).toHaveCount(0);

  // select a quiz question with the keyboard: Tab to Discuss, Enter
  await page.goto(`${base}/studio/s/${builtSession}/preview/${lesson.quiz.id}`);
  const question = frame.locator(`[data-blueprint-id="${questionId}"]`);
  await expect(question).toBeVisible({ timeout: 30_000 });
  const discuss = question.getByRole("button", { name: /^Discuss: / });
  await discuss.focus();
  await expect(discuss).toBeVisible();
  await discuss.press("Enter");
  await expect(page.locator(".st-scope-chip")).toContainText("Discussing: Lesson 1.1 › quiz › Q1");
  await expect(discuss).toHaveAttribute("aria-pressed", "true");
  await expect(page.getByLabel("Change request")).toBeFocused();
  await expect(page.getByRole("heading", { name: "Cited sources" })).toBeVisible();
  await expect(page.locator(".st-pv-sources .cb-cite").first()).toBeVisible();
  await axe(page, "preview with a selection");

  // ask for a change: the diff and its sources appear in the panel; nothing changes before approval
  const before = await question.innerText();
  await page.getByLabel("Change request").fill("make the distractors less obvious");
  await page.getByRole("button", { name: "Propose a change" }).click();
  const diff = page.locator(".cb-diff").last();
  await expect(diff.getByRole("button", { name: "Approve" })).toBeVisible({ timeout: 30_000 });
  expect(await question.innerText()).toBe(before);
  await diff.getByRole("button", { name: "Approve" }).click();
  await expect(page.locator(".cb-diff").last().getByText("Approved and applied")).toBeVisible({ timeout: 30_000 });

  // the element re-renders in place, with the same selection
  await expect.poll(() => question.innerText(), { timeout: 30_000 }).not.toBe(before);
  await expect(discuss).toHaveAttribute("aria-pressed", "true");

  // undo from the preview restores it; Esc in the panel returns to the element
  await page.getByRole("button", { name: "Undo" }).click();
  await expect.poll(() => question.innerText(), { timeout: 30_000 }).toBe(before);
  await page.getByLabel("Change request").press("Escape");
  await expect(discuss).toBeFocused();

  // the landing page and its sections
  await page.getByRole("link", { name: "Landing page" }).click();
  await expect(frame.locator("[data-blueprint-part]").first()).toBeVisible({ timeout: 30_000 });

  // a draft is still reachable only for its author
  const anonymous = await fetch(`${base}/preview/courses/${builtCourse}`);
  expect(anonymous.status).toBe(404);
});
