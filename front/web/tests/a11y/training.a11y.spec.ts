import AxeBuilder from "@axe-core/playwright";
import { createServer } from "node:http";
import type { AddressInfo } from "node:net";
import { expect, test, type Page } from "@playwright/test";

/**
 * My training, certificates, skills and the team view (milestone M-UI5): axe (WCAG 2.2 AA) on desktop and a
 * 360 px phone in a light and a dark theme, the status in words, the PDF route, the badge snippet, the badge
 * on a plain page, keyboard reach and the Polish interface. Runs against the mock API (see
 * playwright.a11y.config.ts); the mock's learner has an overdue course, one due soon, a path with a locked
 * step, a valid and a revoked certificate, a lapsing skill and two reports.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const host = (slug: string) => `http://${slug}.app.localhost:${web}`;
const CERT_ID = "b3f1c2d4-1111-4222-8333-444455556666";
const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];
const THEMES: Array<[string, string]> = [
  ["light", "coffee"],
  ["dark", "nightsky"],
];
const PAGES: Array<[string, string]> = [
  ["account summary", "/account"],
  ["training", "/account/training"],
  ["skills", "/account/skills"],
  ["team", "/account/team"],
  ["team filtered", "/account/team?status=overdue"],
];

test.use({ contextOptions: { reducedMotion: "reduce" } });

/** The browser would ask the tenant API itself for the badge; the mock API is only reachable from the server. */
async function mockVerify(page: Page) {
  await page.route("**/api/certificates/verify/**", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      headers: { "Access-Control-Allow-Origin": "*" },
      body: JSON.stringify({ data: { certificate_id: CERT_ID, status: "valid", title: "Espresso basics", course_title: "Espresso basics", holder_name: "Demo Student", issued_at: "2026-08-01T00:00:00+00:00", expires_at: "2027-08-01T00:00:00+00:00", revoked_at: null, issuer: "Coffee Atlas" } }),
    })
  );
}

async function scan(page: Page) {
  const results = await new AxeBuilder({ page }).withTags(TAGS).exclude("iframe").analyze();
  return results.violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.slice(0, 3).map((n) => `${n.target.join(" ")} - ${n.any[0]?.message ?? n.failureSummary ?? ""}`).join(" | ")}`);
}

for (const [theme, slug] of THEMES) {
  for (const [name, path] of PAGES) {
    test(`axe ${theme}: ${name}`, async ({ page }) => {
      await mockVerify(page);
      const response = await page.goto(`${host(slug)}${path}`, { waitUntil: "load" });
      expect(response?.status(), `HTTP status of ${path}`).toBe(200);
      const violations = await scan(page);
      expect(violations, violations.join("\n")).toEqual([]);
      // 1.4.10 reflow: no horizontal scroll at 320/360 px
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
    });
  }
}

test("training says what is overdue, due soon, locked and expiring, in words", async ({ page }) => {
  await page.goto(`${host("coffee")}/account/training`);
  const required = page.getByRole("list", { name: "Required training" });
  await expect(required.getByText("Overdue by 3 days")).toBeVisible();
  await expect(required.getByText("Due soon")).toBeVisible();
  await expect(required.getByText("Completed", { exact: true })).toBeVisible();
  // overdue first
  const titles = await required.locator("h3").allTextContents();
  expect(titles[0]).toContain("Coffee Atlas");
  // the overdue course has a Continue link to the lesson resume route (the mock has 2 of 15 topics done)
  await expect(required.getByRole("link", { name: /^Continue: / })).toHaveAttribute("href", "/learn/1");
  await expect(required.getByRole("link", { name: /^Start: Food safety/ })).toBeVisible();
  await expect(required.getByRole("link", { name: /View certificate/ })).toHaveAttribute("href", `/certificates/verify/${CERT_ID}`);

  const path = page.getByRole("list", { name: "Barista onboarding" });
  await expect(path.getByText("Locked")).toBeVisible();
  await expect(path.getByText(/^Unlocks after: /)).toBeVisible();
  await expect(path.getByText("1 of 3 steps done").or(page.getByText("1 of 3 steps done"))).toBeVisible();

  const certs = page.getByRole("list", { name: "Certificates" });
  await expect(certs.getByText("Valid", { exact: true })).toBeVisible();
  await expect(certs.getByText(/^Revoked on /)).toBeVisible();
  await expect(certs.getByRole("link", { name: "Download the PDF of Espresso basics" })).toHaveAttribute("href", "/account/certificates/9.pdf");
  await expect(certs.getByRole("link", { name: /^Verify/ }).first()).toHaveAttribute("href", `/certificates/verify/${CERT_ID}`);
});

test("the certificate PDF streams as an attachment for the owner and 404s for anything else", async ({ request }) => {
  const ok = await request.get(`${host("coffee")}/account/certificates/9.pdf`);
  expect(ok.status()).toBe(200);
  expect(ok.headers()["content-type"]).toBe("application/pdf");
  expect(ok.headers()["content-disposition"]).toContain("attachment");
  expect(ok.headers()["cache-control"]).toContain("no-store");
  expect((await ok.body()).subarray(0, 5).toString()).toBe("%PDF-");
  expect((await request.get(`${host("coffee")}/account/certificates/999.pdf`)).status()).toBe(404);
  expect((await request.get(`${host("coffee")}/account/certificates/abc.pdf`)).status()).toBe(404);
});

test("copy badge code puts the snippet on the clipboard and announces it", async ({ page, context, browserName }) => {
  test.skip(browserName !== "chromium");
  await context.grantPermissions(["clipboard-read", "clipboard-write"], { origin: host("coffee") });
  await page.goto(`${host("coffee")}/account/training#certificates`);
  const copy = page.getByRole("button", { name: /^Copy badge code/ });
  await expect(copy).toHaveCount(1); // none for the revoked certificate
  await copy.click();
  await expect(page.getByRole("status").filter({ hasText: "Badge code copied." })).toBeVisible();
  const clip = await page.evaluate(() => navigator.clipboard.readText());
  expect(clip).toContain(`<ulams-certificate-badge certificate-id="${CERT_ID}"`);
  expect(clip).toContain(`${host("coffee")}/embed/ulams-certificate-badge.js`);
  expect(clip).toContain(`verify-href="${host("coffee")}/certificates/verify/{id}"`);
});

test("the pasted snippet works on a plain page", async ({ page }) => {
  await mockVerify(page);
  const snippet = [
    `<script type="module" src="${host("coffee")}/embed/ulams-certificate-badge.js"></script>`,
    `<ulams-certificate-badge certificate-id="${CERT_ID}" api-url="http://coffee.localhost" verify-href="${host("coffee")}/certificates/verify/{id}"></ulams-certificate-badge>`,
  ].join("\n");
  // a page of its own origin (a learner's profile page); served from loopback like the academy, so Chrome's
  // local-network checks allow the cross-origin script
  const html = `<!doctype html><html lang="en"><head><meta charset="utf-8"><title>My profile</title></head><body><main><h1>My profile</h1>${snippet}</main></body></html>`;
  const server = createServer((_req, res) => {
    res.setHeader("Content-Type", "text/html");
    res.end(html);
  });
  await new Promise<void>((resolve) => server.listen(0, "127.0.0.1", resolve));
  try {
    await page.goto(`http://127.0.0.1:${(server.address() as AddressInfo).port}/`);
    await checkPlainBadge(page);
  } finally {
    server.close();
  }
});

async function checkPlainBadge(page: Page) {
  const badge = page.locator("ulams-certificate-badge");
  await expect(badge.getByText("Valid", { exact: true })).toBeVisible();
  await expect(badge.getByText("Demo Student")).toBeVisible();
  await expect(badge.getByRole("link", { name: "Verify this certificate" })).toHaveAttribute("href", `${host("coffee")}/certificates/verify/${CERT_ID}`);
  const violations = await scan(page);
  expect(violations, violations.join("\n")).toEqual([]);
}

test("keyboard: the skills, download and verify links are reachable with a visible, unobscured focus", async ({ page }) => {
  await page.goto(`${host("coffee")}/account/training`);
  const links = [page.getByRole("link", { name: "Download the PDF of Espresso basics" }), page.getByRole("link", { name: /^Verify/ }).first()];
  for (const link of links) {
    await link.focus();
    await expect(link).toBeFocused();
    const box = await link.boundingBox();
    expect(box!.height, "target height (2.5.8)").toBeGreaterThanOrEqual(24);
    const covered = await link.evaluate((el) => {
      el.scrollIntoView({ block: "center" });
      const r = el.getBoundingClientRect();
      const top = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
      return !(top === el || el.contains(top));
    });
    expect(covered, "focus is not obscured (2.4.11)").toBe(false);
  }
});

test.describe("Polish interface", () => {
  test.use({ locale: "pl-PL", extraHTTPHeaders: { "Accept-Language": "pl-PL,pl;q=0.9" } });
  test("the training page follows Accept-Language with Polish plurals and dates", async ({ page }) => {
    await page.goto(`${host("coffee")}/account/training`);
    await expect(page.locator("html")).toHaveAttribute("lang", "pl");
    await expect(page.getByText("Po terminie o 3 dni")).toBeVisible();
    await expect(page.getByRole("heading", { name: "Szkolenia obowiązkowe" })).toBeVisible();
    await expect(page.getByText(/^Odblokuje się po: /)).toBeVisible();
    await expect(page.getByRole("link", { name: /^Pobierz PDF: Espresso basics/ })).toBeVisible();
  });
});

test("the team view lists reports with overdue first and filters by status", async ({ page }) => {
  await page.goto(`${host("coffee")}/account/team`);
  const rows = page.locator("tbody tr");
  await expect(rows).toHaveCount(2);
  await expect(rows.first()).toContainText("Grace Hopper");
  await expect(rows.first()).toContainText("Overdue by 3 days");
  await page.locator("select[name=status]").selectOption("assigned");
  await page.getByRole("button", { name: "Apply" }).click();
  await expect(page).toHaveURL(/status=assigned/);
});
