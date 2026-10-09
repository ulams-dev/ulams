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

async function axe(page: Page, name: string): Promise<void> {
  const result = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"]).analyze();
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

  // interview: one question decided for me, then the rest
  const open = page.locator("form.cb-question-open");
  await expect(open).toBeVisible({ timeout: 30_000 });
  await expect(page.locator(".cb-source").getByText("Ready to cite")).toBeVisible();
  await axe(page, "interview");
  await open.getByRole("button", { name: "Decide for me" }).click();
  await expect(page.locator(".cb-question-answered").first()).toBeVisible();
  await page.getByRole("button", { name: "Decide the rest for me" }).click();

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
  const course = await apiGet<{ id: number; title: string; status: string }>(`/api/admin/courses/${courseId}`);
  expect(course.status).toBe("draft");
  const state = await apiGet<{ session: { currentVersionId: string } }>(`/api/admin/course-builder/sessions/${sessionId}`);
  const version = await apiGet<{ document: { modules: Array<{ lessons: Array<{ quiz: { questions: Array<{ id: string; options: Array<{ text: string }> }> } | null }> }> } }>(
    `/api/admin/course-builder/versions/${state.session.currentVersionId}`
  );
  const question = version.document.modules[0]!.lessons[0]!.quiz!.questions[0]!;

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
