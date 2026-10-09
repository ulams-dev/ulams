import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * End-to-end Living Course audit trail screen (M3.5) on the fake LLM driver: a session with an
 * imported source and a second revision → the Audit page shows the chain as verified and the
 * entries newest first → filter by action group and by a date range that has nothing → open the
 * full record of an entry → the CSV and JSON exports are downloads of the filtered trail → the
 * browser cannot write to the trail. axe (WCAG 2.2 AA) on every state, at desktop and 360 px width (both Playwright projects run the
 * whole flow on their own session).
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
  status: string;
  connection: { latestRevision: { number: number } | null } | null;
}

interface ApiAudit {
  entries: Array<{ id: number; action: string; hash: string }>;
  total: number;
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
  const state = await apiCall<{ session: { id: string } }>("POST", "/api/admin/course-builder/sessions", JSON.stringify({ title: "Audit e2e" }), true);
  sessionId = state.session.id;
  const form = new FormData();
  form.append("file", new Blob([new Uint8Array(v1)], { type: "text/markdown" }), "coffee-brewing.md");
  await apiCall("POST", `/api/admin/course-builder/sessions/${sessionId}/sources`, form);
  await expect
    .poll(async () => {
      const sources = await apiCall<ApiSource[]>("GET", `/api/admin/living-course/sessions/${sessionId}/sources`);
      const source = sources[0];
      if (source?.status === "ready" && source.connection?.latestRevision) sourceId = source.id;
      return sourceId;
    }, { timeout: 60_000 })
    .not.toBe("");
  // a second revision gives the trail a "New source revision" (or "no impact") entry beyond the import
  const upload = new FormData();
  upload.append("file", new Blob([new Uint8Array(v2)], { type: "text/markdown" }), "coffee-brewing.md");
  await apiCall("POST", `/api/admin/living-course/sources/${sourceId}/revisions`, upload);
});

test("audit page: chain, entries, filters, full record, exports", async ({ page }) => {
  test.setTimeout(120_000);

  // sign in as the author, open the page
  await page.goto(`${base}/studio/s/${sessionId}/audit`);
  await expect(page).toHaveURL(/\/studio\/login/);
  await page.getByLabel("E-mail").fill(email);
  await page.getByLabel("Password").fill(password);
  await page.getByRole("button", { name: "Sign in" }).click();
  await page.goto(`${base}/studio/s/${sessionId}/audit`);
  await expect(page.getByRole("heading", { name: "Audit trail", level: 1 })).toBeVisible();
  await expect(page.getByRole("navigation", { name: "Course Builder" }).getByRole("link", { name: "Audit" })).toHaveAttribute("aria-current", "page");

  // the chain is verified and the entries come newest first, with who and what in words
  await expect(page.locator("[data-chain]")).toContainText(/Chain verified: \d+ entries? checked/, { timeout: 30_000 });
  const table = page.getByRole("table", { name: /Audit trail, page 1 of/ });
  await expect(table).toBeVisible();
  const rows = table.locator("tr.cb-audit-row");
  await expect(rows.first()).toBeVisible();
  const total = await rows.count();
  expect(total).toBeGreaterThanOrEqual(2);
  await expect(table.getByText("Source connected").first()).toBeVisible();
  await expect(table.getByText("connection.created").first()).toBeVisible();
  const ids = await rows.evaluateAll((els) => els.map((el) => Number((el as HTMLElement).dataset.entry)));
  expect(ids).toEqual([...ids].sort((a, b) => b - a));
  await expect(page.locator("[data-range]")).toContainText(new RegExp(`Showing 1 to ${total} of ${total} entr`));
  await axe(page, "audit: first page");
  await noHorizontalScroll(page, "audit: first page");

  // the full record of the first entry: hash, previous hash, who, which source revision
  const first = rows.first();
  const toggle = first.getByRole("button", { name: /^Details for entry/ });
  await expect(toggle).toHaveAttribute("aria-expanded", "false");
  await toggle.click();
  await expect(toggle).toHaveAttribute("aria-expanded", "true");
  const detail = page.locator(`#${await toggle.getAttribute("aria-controls")}`);
  await expect(detail).toBeVisible();
  await expect(detail.getByText("Hash", { exact: true })).toBeVisible();
  await expect(detail.getByText("Previous hash", { exact: true })).toBeVisible();
  await expect(detail.getByText("Who", { exact: true })).toBeVisible();
  const apiTrail = await apiCall<ApiAudit>("GET", `/api/admin/living-course/sessions/${sessionId}/audit?perPage=200`);
  expect(apiTrail.total).toBe(total);
  await expect(detail).toContainText(apiTrail.entries[0]!.hash);
  await axe(page, "audit: record open");
  await noHorizontalScroll(page, "audit: record open");
  await toggle.click();
  await expect(detail).toBeHidden();

  // filter: connections only → every row is a connection entry
  await page.getByLabel("Action", { exact: true }).selectOption({ label: "Connections" });
  await page.getByRole("button", { name: "Apply filters" }).click();
  await expect(page.locator("[data-range]")).toContainText(/Showing 1 to \d+ of \d+ entr/);
  await expect
    .poll(async () => {
      const codes = await table.locator("tr.cb-audit-row .cb-audit-code").allTextContents();
      return codes.length > 0 && codes.every((c) => c.startsWith("connection."));
    })
    .toBe(true);
  await axe(page, "audit: filtered");

  // an end date before the start date is refused on the page
  await page.getByLabel("From", { exact: true }).fill("2026-10-09");
  await page.getByLabel("To", { exact: true }).fill("2026-10-01");
  await page.getByRole("button", { name: "Apply filters" }).click();
  await expect(page.getByRole("alert").filter({ hasText: "The end date is before the start date." })).toBeVisible();

  // a day in the far past has no entries: the empty state names the way out
  await page.getByLabel("From", { exact: true }).fill("2001-01-01");
  await page.getByLabel("To", { exact: true }).fill("2001-01-02");
  await page.getByRole("button", { name: "Apply filters" }).click();
  await expect(page.getByText("No entries match these filters.")).toBeVisible();
  await axe(page, "audit: empty");
  await noHorizontalScroll(page, "audit: empty");

  // the exports are plain links; the current filters apply, paging does not
  const csvHref = await page.getByRole("link", { name: "Export CSV" }).getAttribute("href");
  expect(csvHref).toContain(`/studio/api/living-course/sessions/${sessionId}/audit/export?format=csv`);
  expect(csvHref).toContain("from=2001-01-01");
  expect(csvHref).not.toContain("page=");

  // clearing the filters brings the whole trail back, and the exports cover all of it
  await page.getByRole("button", { name: "Clear filters" }).click();
  await expect(page.locator("[data-range]")).toContainText(new RegExp(`of ${total} entr`));
  const csv = await page.request.get(`${base}${await page.getByRole("link", { name: "Export CSV" }).getAttribute("href")}`);
  expect(csv.status()).toBe(200);
  expect(csv.headers()["content-type"]).toContain("text/csv");
  expect(csv.headers()["content-disposition"]).toContain("living-course-audit-");
  const lines = (await csv.text()).trim().split("\n");
  expect(lines[0]).toContain("action");
  expect(lines[0]).toContain("hash");
  expect(lines.length).toBeGreaterThanOrEqual(total + 1);
  const json = await page.request.get(`${base}${await page.getByRole("link", { name: "Export JSON" }).getAttribute("href")}`);
  expect(json.status()).toBe(200);
  const exported = (await json.json()) as Array<{ id: number; action: string }>;
  expect(exported).toHaveLength(total);
  expect(exported.map((e) => e.action)).toContain("connection.created");

  // the trail cannot be changed from the browser: only GET is forwarded
  const post = await page.request.post(`${base}/studio/api/living-course/sessions/${sessionId}/audit`, { data: {} });
  expect([403, 404]).toContain(post.status());
});
