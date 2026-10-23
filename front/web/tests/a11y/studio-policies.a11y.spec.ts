import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * The studio Policies pages (/studio/policies, ADR 0140, WCAG 2.2 AA, axe-core) against the mock tenant API, at desktop and
 * 360 px phone width (the Playwright projects) with reduced motion on, in English, Polish and Simplified Chinese: the list,
 * creating a policy, a policy with its departments, people (two pages) and versions, a first version that cannot be approved
 * yet, the review of a change (class choice, reason, pre-flight, approve, reject), and the failures. The studio signs in as
 * the demo admin of the mock tenant.
 *
 * Mock policies (tests/mock-api/server.mjs): A approved first version and a change in review (28 people), B a draft whose
 * second department is not ready, D someone else's, E fails its first dashboard request, C (anything else) is unknown.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const host = (slug: string) => `http://${slug}.app.localhost:${web}`;
const A = `01pola${"0".repeat(20)}`;
const B = `01polb${"0".repeat(20)}`;
const C = `01polc${"0".repeat(20)}`;
const D = `01pold${"0".repeat(20)}`;
const E = `01pole${"0".repeat(20)}`;
const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];

test.use({ contextOptions: { reducedMotion: "reduce" } });

async function scan(page: Page) {
  const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();
  return results.violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.slice(0, 3).map((n) => `${n.target.join(" ")} - ${n.any[0]?.message ?? n.failureSummary ?? ""}`).join(" | ")}`);
}
const noSidewaysScroll = (page: Page) => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
const slugOf = (project: string) => (project === "desktop" ? "coffee" : "nightsky");

async function open(page: Page, path: string, lang = "en", slug = "coffee") {
  await page.goto(`${host(slug)}${path}${path.includes("?") ? "&" : "?"}lang=${lang}`, { waitUntil: "load" });
}
async function reset(slug: string) {
  const response = await fetch(`http://127.0.0.1:${process.env.MOCK_API_PORT ?? "4390"}/__mock/policies/reset`, { method: "POST", headers: { "x-ulams-api-host": `${slug}.localhost` } });
  expect(response.ok).toBe(true);
}

test("the list shows every policy with its versions, passes axe and reflows", async ({ page }) => {
  await open(page, "/studio/policies");
  await expect(page.locator("h1")).toHaveText("Policies");
  await expect(page.locator("table caption")).toHaveText("Your policies");
  await expect(page.locator("tbody tr")).toHaveCount(3);
  await expect(page.locator("tbody tr").first()).toContainText("Remote work and data handling");
  await expect(page.locator("tbody tr").first()).toContainText("Version 2");
  await expect(page.locator("tbody tr").nth(1)).toContainText("Not approved yet");
  await expect(page.getByRole("link", { name: "New policy" })).toBeVisible();
  expect(await scan(page), "list").toEqual([]);
  expect(await noSidewaysScroll(page), "page scrolls sideways").toBeLessThanOrEqual(1);
});

test("the studio navigation marks Policies as the current page", async ({ page }) => {
  await open(page, "/studio/policies");
  await expect(page.getByRole("link", { name: "Policies" }).first()).toHaveAttribute("aria-current", "page");
});

test("creating a policy: the form passes axe, says what is missing and adds and removes departments by keyboard", async ({ page }) => {
  await open(page, "/studio/policies/new");
  await expect(page.locator("h1")).toHaveText("New policy");
  await expect(page.getByLabel("Policy title")).toBeVisible();
  expect(await scan(page), "form").toEqual([]);

  await page.getByRole("button", { name: "Create policy" }).click();
  await expect(page.locator("[data-result]")).toHaveText("Enter a title.");
  await expect(page.locator("[data-result]")).toHaveAttribute("role", "alert");
  await expect(page.getByLabel("Policy title")).toBeFocused();

  await page.getByLabel("Policy title").fill("Visitor access");
  await page.getByLabel("Policy file").setInputFiles({ name: "policy.md", mimeType: "text/markdown", buffer: Buffer.from("# Visitors\n\nVisitors sign in at the desk.") });
  await page.getByRole("button", { name: "Create policy" }).click();
  await expect(page.locator("[data-result]")).toHaveText("Name every department.");
  await expect(page.locator('[data-department="label"]').first()).toBeFocused();

  const add = page.getByRole("button", { name: "Add a department" });
  await add.focus();
  await page.keyboard.press("Enter");
  await expect(page.locator("[data-department-row]")).toHaveCount(2);
  await expect(page.locator('[data-department-row]:last-of-type [data-department="label"]')).toBeFocused();
  await page.getByRole("button", { name: "Remove department 2" }).focus();
  await page.keyboard.press("Enter");
  await expect(page.locator("[data-department-row]")).toHaveCount(1);
  await expect(add).toBeFocused();
  expect(await scan(page), "form after edits").toEqual([]);
});

test("a policy shows its departments, who is trained, the people and the versions", async ({ page }) => {
  await open(page, `/studio/policies/${A}`);
  await expect(page.locator("[data-department]").first()).toBeVisible();
  await expect(page.locator('li[data-department="Engineering"]')).toContainText("Course applied: publish it in the Studio");
  await expect(page.locator('li[data-department="Engineering"]')).toContainText("Completion rate");
  await expect(page.locator("table").first().locator("tbody tr")).toHaveCount(25);
  await expect(page.locator("[data-page-status]")).toHaveText("Page 1 of 2 (people: 28)");
  await expect(page.getByRole("link", { name: "Export as CSV" })).toHaveAttribute("href", new RegExp(`/studio/api/policies/${A}/dashboard/export`));
  await expect(page.getByRole("link", { name: "Review version 2" })).toBeVisible();
  expect(await scan(page), "policy").toEqual([]);
  expect(await noSidewaysScroll(page), "page scrolls sideways").toBeLessThanOrEqual(1);
});

test("people: the next page opens by keyboard and is announced, the filters narrow the table", async ({ page }) => {
  await open(page, `/studio/policies/${A}`);
  await expect(page.locator("[data-page-status]")).toBeVisible();
  const next = page.getByRole("button", { name: "Next page" });
  await next.focus();
  await page.keyboard.press("Enter");
  await expect(page.locator("[data-page-status]")).toHaveText("Page 2 of 2 (people: 28)");
  await expect(page.locator("[data-announcer]")).toHaveText("People updated: page 2 of 2.");
  await expect(page.locator("tbody tr").first()).toBeVisible();
  expect(await scan(page), "page 2").toEqual([]);

  await page.getByLabel("Status").selectOption("overdue");
  await expect(page.locator("[data-page-status]")).toContainText("Page 1 of 1");
  await expect(page.locator("tbody").first().locator("tr").first()).toContainText("Overdue");
  await page.getByLabel("Department").selectOption({ label: "Sales" });
  await expect(page.locator("[data-page-status]")).toContainText("Page 1 of 1");
});

test("tables and controls are reachable by keyboard, show focus and are big enough", async ({ page }) => {
  await open(page, `/studio/policies/${A}`);
  await expect(page.locator("tbody tr").first()).toBeVisible();
  await expect(page.locator('[role="region"][tabindex="0"]').first()).toHaveAccessibleName(/table/i);
  await page.locator("#main").focus();
  for (let i = 0; i < 50; i++) {
    await page.keyboard.press("Tab");
    const stop = await page.evaluate(() => {
      const el = document.activeElement as HTMLElement | null;
      if (!el || el === document.body) return null;
      const style = getComputedStyle(el);
      const rect = el.getBoundingClientRect();
      return { tag: el.tagName, outline: style.outlineStyle !== "none" && parseFloat(style.outlineWidth) >= 2, width: rect.width, height: rect.height, label: (el.textContent ?? "").trim().slice(0, 30) };
    });
    if (!stop) break;
    expect(stop.outline, `focus indicator on ${stop.tag} ${stop.label}`).toBe(true);
    if (stop.tag === "BUTTON") expect(Math.min(stop.width, stop.height), `target size of ${stop.label}`).toBeGreaterThanOrEqual(24);
  }
});

test("a first version that is not ready says what is missing", async ({ page }) => {
  await open(page, `/studio/policies/${B}`);
  await expect(page.locator('li[data-department="Sales"]')).toContainText("Outline ready: review it in the Studio");
  await page.getByRole("button", { name: "Approve version 1" }).click();
  await expect(page.locator("[data-result]")).toHaveText("The policy cannot be approved yet.");
  await expect(page.locator("[data-blockers]")).toContainText("Sales: review and apply the course in the Studio first.");
  expect(await scan(page), "blocked").toEqual([]);
});

test("the review of a change shows the diff, the departments and what approving will do", async ({ page }) => {
  await open(page, `/studio/policies/${A}/versions/2`);
  await expect(page.locator("h1")).toHaveText("Version 2");
  await expect(page.getByText("Sections 2 changed")).toBeVisible();
  await expect(page.locator("table caption").first()).toHaveText("Changed sections of the policy");
  await expect(page.getByText("Nothing it cites changed")).toBeVisible();
  await expect(page.getByRole("radio", { name: /Major/ })).toBeChecked();
  await expect(page.locator("[data-preflight] tbody tr").first()).toContainText("3");
  await expect(page.locator("[data-preflight] tbody tr").first()).toContainText("The course");
  await expect(page.locator("[data-preflight] tbody tr").nth(1)).toContainText("Not retrained");
  expect(await scan(page), "review").toEqual([]);
  expect(await noSidewaysScroll(page), "page scrolls sideways").toBeLessThanOrEqual(1);
});

test("choosing the lower class updates the pre-flight and asks for a reason by keyboard", async ({ page }, info) => {
  const slug = slugOf(info.project.name);
  await reset(slug);
  await open(page, `/studio/policies/${A}/versions/2`, "en", slug);
  await expect(page.getByRole("radio", { name: /Minor/ })).toBeVisible();
  await page.getByRole("radio", { name: /Minor/ }).focus();
  await page.keyboard.press("Space");
  await expect(page.locator("[data-preflight] tbody tr").first()).toContainText("The exam");
  await expect(page.locator("[data-preflight] tbody tr").first()).toContainText("2");
  await expect(page.getByLabel("Reason for choosing a lower class than suggested")).toHaveAttribute("aria-required", "true");

  await page.getByRole("button", { name: "Approve version 2" }).click();
  await expect(page.locator("[data-decision] [data-result]")).toHaveText("Say why.");
  await expect(page.locator("[data-decision] [data-result]")).toHaveAttribute("role", "alert");
  await expect(page.getByLabel("Reason for choosing a lower class than suggested")).toBeFocused();
  expect(await scan(page), "reason required").toEqual([]);

  await page.getByLabel("Reason for choosing a lower class than suggested").fill("Wording only.");
  await page.getByRole("button", { name: "Approve version 2" }).click();
  await expect(page.locator("[data-state='decided']")).toContainText("This version is Approved.");
  await expect(page.locator("[data-announcer]")).toContainText(/Approved/);
  expect(await scan(page), "approved").toEqual([]);
});

test("rejecting a change needs a reason and records the decision", async ({ page }, info) => {
  const slug = slugOf(info.project.name);
  await reset(slug);
  await open(page, `/studio/policies/${A}/versions/2`, "en", slug);
  await expect(page.locator("[data-reject]")).toBeVisible();
  await page.getByRole("button", { name: "Reject this version" }).click();
  await expect(page.locator("[data-reject] [data-result]")).toHaveText("Say why.");
  await page.getByLabel("Why is it rejected?").fill("Sent by mistake.");
  await page.getByRole("button", { name: "Reject this version" }).click();
  await expect(page.locator("[data-state='decided']")).toContainText("This version is Rejected.");
  expect(await scan(page), "rejected").toEqual([]);
});

test("failures are explained: someone else's policy, an unknown policy, and an error that retries", async ({ page }, info) => {
  await open(page, `/studio/policies/${D}`);
  await expect(page.locator('[data-policies-view] [role="alert"]')).toContainText("Your account cannot use policies");
  expect(await scan(page), "forbidden").toEqual([]);
  await open(page, `/studio/policies/${C}`);
  await expect(page.locator('[data-policies-view] [role="alert"]')).toContainText("was not found");

  const slug = slugOf(info.project.name);
  await reset(slug);
  await open(page, `/studio/policies/${E}`, "en", slug);
  await expect(page.locator('[data-policies-view] [role="alert"]')).toContainText("The policy data could not be loaded.");
  expect(await scan(page), "error").toEqual([]);
  await page.getByRole("button", { name: "Try again" }).click();
  await expect(page.locator('li[data-department="Engineering"]')).toBeVisible();
});

test("Polish and Chinese pages pass axe and say their language", async ({ page }) => {
  await open(page, "/studio/policies", "pl");
  await expect(page.locator("h1")).toHaveText("Polityki");
  await expect(page.locator("main")).toHaveAttribute("lang", "pl");
  expect(await scan(page), "pl list").toEqual([]);
  await open(page, `/studio/policies/${A}/versions/2`, "pl");
  await expect(page.locator("h1")).toHaveText("Wersja 2");
  await expect(page.getByRole("radio", { name: /Duża/ })).toBeChecked();
  expect(await scan(page), "pl review").toEqual([]);
  await open(page, `/studio/policies/${A}`, "zh");
  await expect(page.locator("main")).toHaveAttribute("lang", "zh-Hans");
  await expect(page.locator("[data-department]").first()).toBeVisible();
  expect(await scan(page), "zh policy").toEqual([]);
  await open(page, "/studio/policies/new", "zh");
  await expect(page.locator("h1")).toHaveText("新建政策");
  expect(await scan(page), "zh form").toEqual([]);
});
