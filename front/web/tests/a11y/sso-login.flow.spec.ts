import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * Single sign-on through the reference frontend and the mock tenant API (identity package, ADR 0121): the
 * login page lists the tenant's enabled providers as accessible links, a sign-in ends in a session cookie and
 * no token in any URL, a code works once and only with the verifier cookie of the browser that started the
 * login, and the API's reasons are shown without trusting anything else. The provider itself is skipped:
 * the mock API's start endpoint redirects straight back with a code.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const api = process.env.MOCK_API_PORT ?? "4390";
const site = `http://coffee.app.localhost:${web}`;
const SESSION = "ulams_session";
const SSO = "ulams_sso";
const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];

// The tenant API is `http://coffee.localhost` for the browser (the start redirect of the web server points
// there); resolve that host to the mock API.
test.use({ launchOptions: { args: [`--host-resolver-rules=MAP coffee.localhost 127.0.0.1:${api}`] } });

const cookie = async (page: Page, name: string) => (await page.context().cookies()).find((c) => c.name === name);

async function scan(page: Page) {
  const results = await new AxeBuilder({ page }).withTags(TAGS).analyze();
  return results.violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.slice(0, 3).map((n) => `${n.target.join(" ")} - ${n.any[0]?.message ?? n.failureSummary ?? ""}`).join(" | ")}`);
}

test("the login page offers the tenant's provider as a labelled link and passes axe", async ({ page }) => {
  await page.goto(`${site}/login`);

  const nav = page.getByRole("navigation", { name: "Other ways to sign in" });
  const link = nav.getByRole("link", { name: "Continue with Acme staff" });
  await expect(link).toBeVisible();
  await expect(link).toHaveAttribute("href", "/auth/sso/acme-sso?next=%2F");

  // keyboard: the link is reachable and activated with Enter
  await link.focus();
  await expect(link).toBeFocused();

  const violations = await scan(page);
  expect(violations, violations.join("\n")).toEqual([]);
});

test("signing in through the provider ends with a session cookie and no token in any URL", async ({ page }) => {
  const urls: string[] = [];
  page.on("request", (request) => urls.push(request.url()));

  await page.goto(`${site}/login?next=%2Fcourses%2F1`);
  await page.getByRole("link", { name: "Continue with Acme staff" }).click();
  await page.waitForURL(`${site}/courses/1`);

  const session = await cookie(page, SESSION);
  expect(session?.value).toMatch(/^sso-token-/);
  expect(session?.httpOnly).toBe(true);
  expect(await cookie(page, SSO)).toBeUndefined();
  expect(urls.some((u) => u.includes("/auth/sso/callback?code="))).toBe(true);
  expect(urls.filter((u) => u.includes("sso-token") || /[?&]token=/.test(u))).toEqual([]);
  expect(page.url()).not.toContain("code=");
  expect(await page.evaluate(() => document.cookie)).not.toContain("sso-token");
});

test("the callback answers without caching and without a referrer", async ({ page }) => {
  let callback: { status: number; headers: Record<string, string> } | null = null;
  page.on("response", (response) => {
    if (response.url().includes("/auth/sso/callback")) callback = { status: response.status(), headers: response.headers() };
  });

  await page.goto(`${site}/login`);
  await page.getByRole("link", { name: "Continue with Acme staff" }).click();
  await page.waitForURL(`${site}/`);

  expect(callback).not.toBeNull();
  expect(callback!.status).toBe(303);
  expect(callback!.headers["referrer-policy"]).toBe("no-referrer");
  expect(callback!.headers["cache-control"]).toContain("no-store");
});

test("a code cannot be used twice", async ({ page }) => {
  let callbackUrl = "";
  page.on("request", (request) => {
    if (request.url().includes("/auth/sso/callback?code=")) callbackUrl = request.url();
  });
  await page.goto(`${site}/login`);
  await page.getByRole("link", { name: "Continue with Acme staff" }).click();
  await page.waitForURL(`${site}/`);
  expect(callbackUrl).not.toBe("");

  await page.context().clearCookies();
  await page.goto(callbackUrl);
  await expect(page).toHaveURL(/\/login\?error=invalid/);
  await expect(page.getByRole("alert")).toHaveText("Sign-in failed. Try again.");
  expect(await cookie(page, SESSION)).toBeUndefined();
});

test("a code from another browser is useless without this browser's verifier", async ({ page, request }) => {
  const attacker = await request.get(`http://127.0.0.1:${api}/api/identity/sso/acme-sso/start`, {
    params: { return_to: `${site}/auth/sso/callback`, challenge: "A".repeat(43) },
    maxRedirects: 0,
  });
  const injected = attacker.headers()["location"]!;
  expect(injected).toContain("code=");

  const started = await page.request.get(`${site}/auth/sso/acme-sso`, { maxRedirects: 0 });
  expect(started.status()).toBe(302);
  expect(await cookie(page, SSO)).toBeDefined();

  await page.goto(injected);
  await expect(page).toHaveURL(/\/login\?error=invalid/);
  expect(await cookie(page, SESSION)).toBeUndefined();
  expect(await cookie(page, SSO)).toBeUndefined();
});

test("a callback without the verifier cookie is refused", async ({ page }) => {
  await page.goto(`${site}/auth/sso/callback?code=${"x".repeat(43)}`);
  await expect(page).toHaveURL(/\/login\?error=invalid/);
  expect(await cookie(page, SESSION)).toBeUndefined();
});

test("the reasons of the API are shown in an alert that passes axe, nothing else is trusted", async ({ page }) => {
  await page.goto(`${site}/auth/sso/callback?error=signup_disabled`);
  await expect(page.getByRole("alert")).toContainText("does not create accounts through this sign-in");
  const violations = await scan(page);
  expect(violations, violations.join("\n")).toEqual([]);

  await page.goto(`${site}/auth/sso/callback?error=link_required`);
  await expect(page.getByRole("alert")).toContainText("Sign in with your password first");

  await page.goto(`${site}/login?error=%3Cscript%3Ealert(1)%3C%2Fscript%3E`);
  await expect(page.getByRole("alert")).toHaveText("Sign-in failed. Try again.");
});

test("only providers the tenant lists start a login", async ({ page }) => {
  expect((await page.goto(`${site}/auth/sso/not-a-provider`))?.status()).toBe(404);
  expect((await page.goto(`${site}/auth/sso/Bad_Slug`))?.status()).toBe(404);
});

test("the start refuses to bounce to another site after sign-in", async ({ page }) => {
  await page.goto(`${site}/auth/sso/acme-sso?next=${encodeURIComponent("//evil.example/x")}`);
  await page.waitForURL(`${site}/`);
});
