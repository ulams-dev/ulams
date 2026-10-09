import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * /cli/authorize (device login of the ulams CLI, ADR 0075) against the real tenant API:
 * the CLI side is played with plain fetch calls to the device endpoints, the person with the
 * browser. Needs an API that has the device endpoints (skipped otherwise) and a demo tenant.
 * WCAG 2.2 AA (axe) is checked in every state of the page.
 */
const port = process.env.WEB_BASE_PORT ?? "4321";
const slug = process.env.CLI_E2E_TENANT ?? "coffee";
const web = `http://${slug}.app.localhost:${port}`;
const api = process.env[`API_${slug.toUpperCase()}`] ?? `http://${slug}.localhost`;

interface Started {
  device_code: string;
  user_code: string;
  verification_uri_complete: string;
}

async function start(scopes = ["courses:write", "builder:write", "users:write"]): Promise<Started> {
  const res = await fetch(`${api}/api/auth/device/code`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify({ client_name: "ulams-cli on e2e-host", scopes }),
  });
  expect(res.status).toBe(200);
  return (await res.json()) as Started;
}

const poll = (deviceCode: string) =>
  fetch(`${api}/api/auth/device/token`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify({ device_code: deviceCode }),
  });

async function signInAsDemoStudent(page: Page) {
  await page.goto(`${web}/login`);
  await page.getByRole("button", { name: /demo student/i }).click();
  await page.waitForURL(`${web}/`);
}

async function axe(page: Page) {
  const results = await new AxeBuilder({ page }).withTags(["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"]).analyze();
  expect(results.violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(" ")).join(", ")}`)).toEqual([]);
}

test.beforeAll(async () => {
  const probe = await fetch(`${api}/api/auth/device/token`, { method: "POST", headers: { "Content-Type": "application/json", Accept: "application/json" }, body: "{}" }).catch(() => null);
  test.skip(!probe || probe.status === 404, "the tenant API has no device login endpoints");
});

test.describe("/cli/authorize", () => {
  test("signed-out visitors are sent to the login and come back to the code", async ({ page }) => {
    const { user_code } = await start();
    await page.goto(`${web}/cli/authorize?code=${user_code}`);
    await expect(page).toHaveURL(new RegExp(`/login\\?next=${encodeURIComponent(`/cli/authorize?code=${user_code}`).replace(/[.*+?^${}()|[\]\\]/g, "\\$&")}`));
  });

  test("approve with fewer scopes: the CLI collects a token with exactly those", async ({ page }) => {
    const started = await start();
    await signInAsDemoStudent(page);
    await page.goto(`${web}/cli/authorize?code=${started.user_code}`);

    await expect(page.getByRole("heading", { level: 1, name: /authorize the ulams cli/i })).toBeVisible();
    await expect(page.getByText("ulams-cli on e2e-host")).toBeVisible();
    await expect(page.locator(".u-cli__code")).toHaveText(started.user_code);
    await expect(page.getByRole("checkbox")).toHaveCount(3);
    await expect(page.getByText("(sensitive)")).toHaveCount(1); // users:write
    await expect(page.getByRole("radio", { name: "90 days" })).toBeChecked();
    await axe(page);

    // pending until answered
    expect((await poll(started.device_code)).status).toBe(400);

    await page.getByRole("checkbox", { name: /users and groups/i }).uncheck();
    await page.getByRole("radio", { name: "30 days" }).check();
    await page.getByRole("button", { name: "Approve" }).click();
    await expect(page.getByRole("heading", { name: "Approved" })).toBeVisible();
    await axe(page);

    // the poll interval is 5 s: wait it out, then collect once
    await new Promise((r) => setTimeout(r, 5200));
    const collected = await poll(started.device_code);
    expect(collected.status).toBe(200);
    const body = (await collected.json()) as { access_token: string; scopes: string[]; token_type: string };
    expect(body.token_type).toBe("Bearer");
    expect(body.access_token.startsWith("ulams_pat_")).toBe(true);
    expect(body.scopes).toEqual(["builder:write", "courses:write"]);
    await new Promise((r) => setTimeout(r, 5200));
    const again = await poll(started.device_code);
    expect(again.status).toBe(400);
    expect(((await again.json()) as { error: string }).error).toBe("expired_token");

    // the token is real, scoped and belongs to the signed-in user
    const me = await fetch(`${api}/api/auth/tokens/current`, { headers: { Authorization: `Bearer ${body.access_token}`, Accept: "application/json" } });
    expect(me.status).toBe(200);
    expect(((await me.json()) as { data: { scoped: boolean; created_via: string } }).data).toMatchObject({ scoped: true, created_via: "device" });
  });

  test("deny: the CLI is told access was denied", async ({ page }) => {
    const started = await start(["courses:read"]);
    await signInAsDemoStudent(page);
    await page.goto(`${web}/cli/authorize?code=${started.user_code}`);
    await page.getByRole("button", { name: "Deny" }).click();
    await expect(page.getByRole("heading", { name: "Denied" })).toBeVisible();
    await axe(page);
    await new Promise((r) => setTimeout(r, 5200));
    const res = await poll(started.device_code);
    expect(res.status).toBe(400);
    expect(((await res.json()) as { error: string }).error).toBe("access_denied");
  });

  test("unticking every permission asks for one instead of approving", async ({ page }) => {
    const started = await start(["courses:read"]);
    await signInAsDemoStudent(page);
    await page.goto(`${web}/cli/authorize?code=${started.user_code}`);
    await page.getByRole("checkbox").first().uncheck();
    await page.getByRole("button", { name: "Approve" }).click();
    await expect(page.getByRole("alert")).toContainText("Tick at least one permission");
    await axe(page);
  });

  test("the code can be typed in any case; a wrong or used code explains itself", async ({ page }) => {
    const started = await start(["courses:read"]);
    await signInAsDemoStudent(page);
    await page.goto(`${web}/cli/authorize`);
    await expect(page.getByLabel("Code")).toBeVisible();
    await axe(page);
    await page.getByLabel("Code").fill(started.user_code.toLowerCase().replace("-", " "));
    await page.getByRole("button", { name: "Continue" }).click();
    await expect(page.getByText("ulams-cli on e2e-host")).toBeVisible();

    await page.goto(`${web}/cli/authorize?code=nonsense`);
    await expect(page.getByRole("alert")).toContainText("not a valid code");
    await page.goto(`${web}/cli/authorize?code=BBBB-BBBB`);
    await expect(page.getByRole("alert")).toContainText("unknown, has expired or was already answered");
    await axe(page);
  });

  test("the page cannot be framed, cached or leak the code through Referer", async ({ page }) => {
    const started = await start(["courses:read"]);
    await signInAsDemoStudent(page);
    const response = await page.goto(`${web}/cli/authorize?code=${started.user_code}`);
    const headers = response!.headers();
    expect(headers["x-frame-options"]).toBe("DENY");
    expect(headers["content-security-policy"]).toContain("frame-ancestors 'none'");
    expect(headers["referrer-policy"]).toBe("no-referrer");
    expect(headers["cache-control"]).toContain("no-store");
  });

  test("a cross-origin approval POST is refused", async ({ page, request }) => {
    const started = await start(["courses:read"]);
    await signInAsDemoStudent(page);
    const cookies = await page.context().cookies(web);
    const res = await request.post(`${web}/cli/authorize`, {
      headers: { Origin: "https://evil.example", Cookie: cookies.map((c) => `${c.name}=${c.value}`).join("; ") },
      form: { code: started.user_code, decision: "approve", scope: "courses:read" },
    });
    expect(res.status()).toBe(403);
    expect((await poll(started.device_code)).status).toBe(400);
  });
});
