import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * End-to-end Living Course sources page (M3.1) on the fake LLM driver: a session with an imported
 * source → the Sources page shows the connection card and the empty state → upload a new version
 * (revision 2 with its changes, word diffs and a cosmetic toggle that only appears when there is
 * something cosmetic) → upload the same file again ("already the latest revision") → a rejected
 * upload shows the API message. axe (WCAG 2.2 AA) on every state, at desktop and 360 px width (both
 * Playwright projects run the whole flow on their own session).
 *
 * Needs the same setup as studio.spec.ts (README-studio.md). Skipped unless STUDIO_E2E=1.
 */
const base = process.env.STUDIO_BASE_URL ?? "http://e2e.app.localhost:4329";
const api = process.env.STUDIO_API_URL ?? "http://127.0.0.1:18081";
const email = process.env.STUDIO_AUTHOR_EMAIL ?? "author@e2e.test";
const password = process.env.STUDIO_AUTHOR_PASSWORD ?? "e2e-secret";
const fixtureDir = fileURLToPath(new URL("../../../../api/packages/living-course/resources/fixtures/", import.meta.url));
const v1 = readFileSync(`${fixtureDir}coffee-brewing.v1.md`);
const v2 = readFileSync(`${fixtureDir}coffee-brewing.v2.md`);

test.skip(process.env.STUDIO_E2E !== "1", "set STUDIO_E2E=1 with the fake-driver API running");
test.describe.configure({ mode: "serial" });

interface ApiSource {
  id: string;
  name: string;
  status: string;
  connection: { connector: string; latestRevision: { number: number } | null } | null;
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

let sessionId = "";
let sourceId = "";

test.beforeAll(async () => {
  const state = await apiCall<{ session: { id: string } }>("POST", "/api/admin/course-builder/sessions", JSON.stringify({ title: "Sources e2e" }), true);
  sessionId = state.session.id;
  const form = new FormData();
  form.append("file", new Blob([new Uint8Array(v1)], { type: "text/markdown" }), "coffee-brewing.md");
  await apiCall("POST", `/api/admin/course-builder/sessions/${sessionId}/sources`, form);
  // the import runs as a job; revision 1 exists once the source is ready
  await expect
    .poll(async () => {
      const sources = await apiCall<ApiSource[]>("GET", `/api/admin/living-course/sessions/${sessionId}/sources`);
      const source = sources[0];
      if (source?.status === "ready" && source.connection?.latestRevision) sourceId = source.id;
      return sourceId;
    }, { timeout: 60_000 })
    .not.toBe("");
});

test("sources page: card, upload a new version, revision diff, same file, rejected file", async ({ page }) => {
  test.setTimeout(120_000);

  // sign in as the author, open the page
  await page.goto(`${base}/studio/s/${sessionId}/sources`);
  await expect(page).toHaveURL(/\/studio\/login/);
  await page.getByLabel("E-mail").fill(email);
  await page.getByLabel("Password").fill(password);
  await page.getByRole("button", { name: "Sign in" }).click();
  await page.goto(`${base}/studio/s/${sessionId}/sources`);
  await expect(page.getByRole("heading", { name: "Sources", level: 1 })).toBeVisible();

  // the connection card: upload connector, revision 1 in the course, no changes yet
  const block = page.locator(`[data-source-block="${sourceId}"]`);
  await expect(block.getByRole("heading", { name: /Coffee Brewing/i }).first()).toBeVisible({ timeout: 30_000 });
  await expect(block.getByText("Up to date")).toBeVisible();
  await expect(block.getByText("Upload", { exact: false }).first()).toBeVisible();
  await expect(block.getByText("In your course").first()).toBeVisible();
  await expect(block.getByText("No source changes yet. New versions appear here when you upload them.")).toBeVisible();
  await axe(page, "sources: empty");
  await noHorizontalScroll(page, "sources: empty");

  // upload a new version: revision 2, its counts and the changed fragments
  await block.locator("[data-file]").setInputFiles({ name: "coffee-brewing.v2.md", mimeType: "text/markdown", buffer: v2 });
  await expect(block.getByText(/Revision 2 added: /)).toBeVisible({ timeout: 60_000 });
  await expect(block.getByText("New version available")).toBeVisible();
  const timeline = block.getByRole("list", { name: "Revisions, newest first" });
  await expect(timeline.getByText("Latest")).toBeVisible();
  await expect(timeline.getByText("In your course")).toHaveCount(1);
  await expect(timeline.locator("li").first().locator(".cb-rev-counts")).toContainText(/\d+ (changed|removed|added|moved)/);
  const changes = block.getByRole("region", { name: /What changed in revision 2/ });
  await expect(changes.locator("article").first()).toBeVisible();
  // a changed fragment shows the word diff with text markers, not colour only
  const changed = changes.locator("article.cb-frag-changed").first();
  await expect(changed.locator("ins").first()).toContainText("added:");
  await expect(changed.locator("del").first()).toContainText("removed:");
  await expect(changed.locator(".cb-badge")).toContainText("Changed");
  await axe(page, "sources: revision 2 diff");
  await noHorizontalScroll(page, "sources: revision 2 diff");

  // cosmetic changes are hidden; the toggle exists only when there are some
  const toggle = block.getByRole("button", { name: /cosmetic change/ });
  if (await toggle.isVisible()) {
    const before = await changes.locator("article").count();
    await expect(toggle).toHaveAttribute("aria-pressed", "false");
    await toggle.click();
    await expect(toggle).toHaveAttribute("aria-pressed", "true");
    expect(await changes.locator("article").count()).toBeGreaterThan(before);
    await axe(page, "sources: cosmetic shown");
  }

  // selecting revision 1 shows its own (empty) change list; revision 2 again shows the diff
  await timeline.getByRole("button", { name: /Revision 1/ }).click();
  await expect(block.getByRole("region", { name: /What changed in revision 1/ })).toBeVisible();
  await timeline.getByRole("button", { name: /Revision 2/ }).click();
  await expect(changes.locator("article").first()).toBeVisible();

  // the API agrees: two revisions, the second one latest and not yet in the course
  const revisions = await apiCall<Array<{ number: number; synced: boolean; latest: boolean }>>("GET", `/api/admin/living-course/sources/${sourceId}/revisions`);
  expect(revisions.map((r) => [r.number, r.synced, r.latest])).toEqual([
    [2, false, true],
    [1, true, false],
  ]);

  // the same file again is not a new revision
  await block.locator("[data-file]").setInputFiles({ name: "coffee-brewing.v2.md", mimeType: "text/markdown", buffer: v2 });
  await expect(block.getByText(/already the latest revision \(revision 2\)/)).toBeVisible({ timeout: 30_000 });
  expect(await apiCall<unknown[]>("GET", `/api/admin/living-course/sources/${sourceId}/revisions`)).toHaveLength(2);
  await axe(page, "sources: unchanged");

  // a file type the API rejects shows its message and creates nothing
  await block.locator("[data-file]").setInputFiles({ name: "notes.exe", mimeType: "application/octet-stream", buffer: Buffer.from("MZ not a document") });
  await expect(block.locator("form[data-drop] [role=alert]")).not.toBeEmpty({ timeout: 30_000 });
  expect(await apiCall<unknown[]>("GET", `/api/admin/living-course/sources/${sourceId}/revisions`)).toHaveLength(2);
  await axe(page, "sources: rejected upload");
  await noHorizontalScroll(page, "sources: rejected upload");
});
