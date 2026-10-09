import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * End-to-end Living Course updates (M3.2 and M3.3) on the fake LLM driver: a course is built from
 * coffee-brewing.v1.md through the builder, a second version of the source is uploaded through the
 * sources API, and the author then sees the staleness markers and the banner in the workspace and
 * the badge in the session list, opens the proposal list and the review, decides on items, accepts
 * all, applies, and ends on the done state with the update in the version history and the course
 * back in sync. axe (WCAG 2.2 AA) on every state, at desktop and 360 px width (both Playwright
 * projects run the whole flow on their own session).
 *
 * Needs the same setup as studio.spec.ts (README-studio.md). Skipped unless STUDIO_E2E=1.
 */
const base = process.env.STUDIO_BASE_URL ?? "http://e2e.app.localhost:4329";
const api = process.env.STUDIO_API_URL ?? "http://127.0.0.1:18081";
const email = process.env.STUDIO_AUTHOR_EMAIL ?? "author@e2e.test";
const password = process.env.STUDIO_AUTHOR_PASSWORD ?? "e2e-secret";
const builderFixture = fileURLToPath(new URL("../../../../api/packages/living-course/resources/fixtures/coffee-brewing.v1.md", import.meta.url));
const fixtureDir = fileURLToPath(new URL("../../../../api/packages/living-course/resources/fixtures/", import.meta.url));
const v2 = readFileSync(`${fixtureDir}coffee-brewing.v2.md`);

test.skip(process.env.STUDIO_E2E !== "1", "set STUDIO_E2E=1 with the fake-driver API running");
test.describe.configure({ mode: "serial" });

interface ApiSource {
  id: string;
  status: string;
  connection: { latestRevision: { number: number } | null } | null;
}
interface ApiProposal {
  id: string;
  number: number;
  status: string;
}
interface ApiVersionRow {
  number: number;
  kind: string;
  reason: string | null;
}

async function axe(page: Page, name: string): Promise<void> {
  const result = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"]).analyze();
  expect(result.violations.map((v) => `${name}: ${v.id} ${v.nodes.map((n) => n.target.join(" ")).join(", ")}`)).toEqual([]);
}

async function noHorizontalScroll(page: Page, name: string): Promise<void> {
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  expect(overflow, `${name}: horizontal scroll`).toBeLessThanOrEqual(1);
}

async function apiToken(): Promise<string> {
  const response = await fetch(`${api}/api/auth/login`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify({ email, password, remember_me: 1 }),
  });
  return ((await response.json()) as { data: { token: string } }).data.token;
}

async function apiCall<T>(method: string, path: string, body?: BodyInit, json = false): Promise<T> {
  const headers: Record<string, string> = { Accept: "application/json", Authorization: `Bearer ${await apiToken()}` };
  if (json) headers["Content-Type"] = "application/json";
  const response = await fetch(`${api}${path}`, { method, headers, body });
  expect(response.ok, `${method} ${path} → ${response.status}`).toBe(true);
  return ((await response.json()) as { data: T }).data;
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

/** This run's course in the session list (earlier runs leave their own courses there). */
const sessionCard = (page: Page) => page.locator("li.cb-card", { has: page.locator(`a[href^="/studio/s/${sessionId}"]`) });

let sessionId = "";
let sourceId = "";
let proposalId = "";

test.beforeAll(async ({ browser }) => {
  test.setTimeout(240_000);
  // 1. build the course from version 1 through the builder, always at desktop width
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await context.newPage();
  await signIn(page, "/studio/new");
  await page.locator("[data-file]").setInputFiles(builderFixture);
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
  await context.close();

  // 2. a new version of the source: the upload creates revision 2 and an update proposal
  await expect
    .poll(async () => {
      const sources = await apiCall<ApiSource[]>("GET", `/api/admin/living-course/sessions/${sessionId}/sources`);
      const source = sources[0];
      if (source?.status === "ready" && source.connection?.latestRevision) sourceId = source.id;
      return sourceId;
    }, { timeout: 60_000 })
    .not.toBe("");
  const form = new FormData();
  form.append("file", new Blob([new Uint8Array(v2)], { type: "text/markdown" }), "coffee-brewing.v2.md");
  await apiCall("POST", `/api/admin/living-course/sources/${sourceId}/revisions`, form);
  await expect
    .poll(async () => {
      const proposals = await apiCall<ApiProposal[]>("GET", `/api/admin/living-course/sessions/${sessionId}/proposals`);
      const proposal = proposals[0];
      if (proposal && ["ready", "awaiting_analysis"].includes(proposal.status)) proposalId = proposal.id;
      return proposalId;
    }, { timeout: 90_000 })
    .not.toBe("");
});

test("stale course: markers in the workspace, banner with a link to the review, badge in the session list", async ({ page }) => {
  test.setTimeout(90_000);
  await signIn(page, "/studio");
  await expect(sessionCard(page).locator(".st-fresh")).toContainText(/Stale/);
  await axe(page, "sessions: stale badge");
  await noHorizontalScroll(page, "sessions: stale badge");

  await page.goto(`${base}/studio/s/${sessionId}/workspace`);
  const banner = page.getByRole("region", { name: "Source freshness" });
  await expect(banner).toBeVisible({ timeout: 30_000 });
  await expect(banner).toContainText(/out of date with the source|The source changed/);
  // markers carry words, not colour only
  await expect(page.locator("[data-tree] [data-marker]").first()).toContainText(/Update pending|Answer may be wrong|Source removed/);
  await page.locator("[data-tree] [data-element]").nth(1).click();
  await axe(page, "workspace: stale");
  await noHorizontalScroll(page, "workspace: stale");

  await banner.getByRole("link", { name: "Review the update" }).click();
  await expect(page).toHaveURL(new RegExp(`/studio/s/${sessionId}/updates/${proposalId}$`));
});

test("updates list and review: decide, accept all, apply, done", async ({ page }) => {
  test.setTimeout(240_000);
  await signIn(page, `/studio/s/${sessionId}/updates`);
  await expect(page.getByRole("heading", { name: "Source updates", level: 1 })).toBeVisible();
  const row = page.locator("[data-updates-table] tbody tr").first();
  await expect(row).toContainText("r1 → r2");
  await expect(row).toContainText(/Ready to review|Waiting to be analysed/);
  await axe(page, "updates: list");
  await noHorizontalScroll(page, "updates: list");
  await row.getByRole("link", { name: /Update 1/ }).click();

  // review: summary, source changes, items per lesson
  await expect(page.getByRole("heading", { name: /Source update: revision 1 → 2/ })).toBeVisible({ timeout: 30_000 });
  const analyse = page.getByRole("button", { name: /^Analyse/ });
  if (await analyse.isVisible()) {
    await analyse.click();
    await page.getByRole("button", { name: "Start the analysis" }).click();
  }
  await expect(page.locator("[data-update-item]").first()).toBeVisible({ timeout: 90_000 });
  await expect(page.locator("[data-analysing]")).toHaveCount(0, { timeout: 90_000 });
  await expect(page.getByRole("region", { name: "Impact of this update" })).toBeVisible();
  await expect(page.locator("[data-source-changes] article").first()).toBeVisible({ timeout: 30_000 });
  await expect(page.locator("[data-source-changes] article ins, [data-source-changes] article del").first()).toBeVisible();
  await expect(page.locator("[data-group]").first().getByRole("heading", { level: 3 })).toContainText(/Lesson|Course|Final test|New in the source/);
  await axe(page, "review: ready");
  await noHorizontalScroll(page, "review: ready");

  // one decision by hand: the button reports its state and the apply bar follows
  const first = page.locator("[data-update-item]").first();
  const accept = first.locator('[data-decision="accepted"]');
  await accept.click();
  await expect(accept).toHaveAttribute("aria-pressed", "true");
  await expect(first).toHaveAttribute("data-status", "accepted");
  await expect(page.locator("[data-apply-summary]")).toContainText(/\d+ accepted/);
  await first.getByRole("button", { name: "Undo my decision" }).click();
  await expect(first).toHaveAttribute("data-status", "pending");
  await axe(page, "review: after decisions");

  // reject all asks first, and cancelling changes nothing
  await page.getByRole("button", { name: "Reject all" }).click();
  await expect(page.locator("[data-confirm]")).toContainText("marks source revision 2 as reviewed");
  await page.locator("[data-confirm]").getByRole("button", { name: "Cancel" }).click();
  await expect(page.locator("[data-confirm]")).toHaveCount(0);

  // accept all, apply
  await page.getByRole("button", { name: "Accept all" }).click();
  const apply = page.getByRole("button", { name: /^Apply \d+ accepted changes?$/ });
  await expect(apply).toBeEnabled({ timeout: 30_000 });
  await axe(page, "review: all accepted");
  await noHorizontalScroll(page, "review: apply bar");
  await apply.click();
  const overwrite = page.getByRole("button", { name: "Overwrite and apply" });
  if (await overwrite.isVisible({ timeout: 3_000 }).catch(() => false)) await overwrite.click();
  await expect(page.locator("[data-done]")).toBeVisible({ timeout: 180_000 });
  await expect(page.locator("[data-done]")).toContainText("The update is applied");
  await axe(page, "review: applied");
  await noHorizontalScroll(page, "review: applied");

  // the API agrees: applied, and the course has an update version
  const proposal = await apiCall<ApiProposal>("GET", `/api/admin/living-course/proposals/${proposalId}`);
  expect(proposal.status).toBe("applied");
  const versions = await apiCall<{ versions: ApiVersionRow[] }>("GET", `/api/admin/course-builder/sessions/${sessionId}/versions`);
  const update = versions.versions.filter((v) => v.kind === "update").at(-1);
  expect(update?.reason).toMatch(/^Source update r1 → r2/);
});

test("after the apply: the course is in sync and the history names the source update", async ({ page }) => {
  test.setTimeout(90_000);
  await signIn(page, `/studio/s/${sessionId}/workspace`);
  await expect(page.locator("[data-tree] [data-element]").first()).toBeVisible({ timeout: 30_000 });
  // the update is applied: the course is no longer stale. Items the author left undecided (a section
  // no lesson covers yet) count as dismissed, and then the banner says so instead of disappearing.
  const banner = page.getByRole("region", { name: "Source freshness" });
  if (await banner.isVisible()) await expect(banner).toContainText("You kept the earlier version");
  await expect(page.locator("[data-tree] [data-marker]")).toHaveCount(0);
  await page.getByText("Version history").click();
  await expect(page.locator("[data-history]")).toContainText(/Source update r1 → r2/, { timeout: 30_000 });
  await axe(page, "workspace: in sync");

  await page.goto(`${base}/studio`);
  await expect(sessionCard(page).locator(".st-fresh")).toContainText(/In sync|Updates dismissed/);
  await axe(page, "sessions: in sync");
});
