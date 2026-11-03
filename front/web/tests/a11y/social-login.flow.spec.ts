import { expect, test, type Page } from "@playwright/test";

/**
 * Sign-in with Google through the reference frontend and the mock tenant API (ADR 0121): the
 * browser never sees an access token in a URL, the one-time code works once and only together with
 * the verifier cookie of the browser that started the login. The provider itself is skipped: the
 * mock API's start endpoint redirects straight back with a code.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const api = process.env.MOCK_API_PORT ?? "4390";
const site = `http://coffee.app.localhost:${web}`;
const SESSION = "ulams_session";
const SSO = "ulams_sso";

// The tenant API is `http://coffee.localhost` for the browser (the start redirect of the web server points
// there); resolve that host to the mock API. Playwright's `route` does not see redirect targets.
test.use({ launchOptions: { args: [`--host-resolver-rules=MAP coffee.localhost 127.0.0.1:${api}`] } });

const cookie = async (page: Page, name: string) => (await page.context().cookies()).find((c) => c.name === name);

function recordUrls(page: Page): string[] {
  const urls: string[] = [];
  page.on("request", (request) => urls.push(request.url()));
  return urls;
}

test("signing in with Google ends with a session cookie and no token in any URL", async ({ page }) => {
  const urls = recordUrls(page);

  await page.goto(`${site}/login?next=%2Fcourses%2F1`);
  await page.getByRole("link", { name: "Continue with Google" }).click();
  await page.waitForURL(`${site}/courses/1`);

  const session = await cookie(page, SESSION);
  expect(session?.value).toMatch(/^social-token-/);
  expect(session?.httpOnly).toBe(true);
  // the verifier cookie is used up
  expect(await cookie(page, SSO)).toBeUndefined();
  // the code reached the callback, the token never travelled in a URL
  expect(urls.some((u) => u.includes("/auth/social/callback?code="))).toBe(true);
  expect(urls.filter((u) => u.includes("social-token") || /[?&]token=/.test(u))).toEqual([]);
  expect(page.url()).not.toContain("code=");
  expect(await page.evaluate(() => document.cookie)).not.toContain("social-token");
});

test("the callback answers without caching and without a referrer", async ({ page }) => {
  let callback: { status: number; headers: Record<string, string> } | null = null;
  page.on("response", (response) => {
    if (response.url().includes("/auth/social/callback")) callback = { status: response.status(), headers: response.headers() };
  });

  await page.goto(`${site}/login`);
  await page.getByRole("link", { name: "Continue with Google" }).click();
  await page.waitForURL(`${site}/`);

  expect(callback).not.toBeNull();
  expect(callback!.status).toBe(303);
  expect(callback!.headers["referrer-policy"]).toBe("no-referrer");
  expect(callback!.headers["cache-control"]).toContain("no-store");
});

test("a code cannot be used twice", async ({ page }) => {
  let callbackUrl = "";
  page.on("request", (request) => {
    if (request.url().includes("/auth/social/callback?code=")) callbackUrl = request.url();
  });
  await page.goto(`${site}/login`);
  await page.getByRole("link", { name: "Continue with Google" }).click();
  await page.waitForURL(`${site}/`);
  expect(callbackUrl).not.toBe("");

  // replay in the same browser: the verifier cookie is gone, so the code is refused here already
  await page.context().clearCookies();
  await page.goto(callbackUrl);
  await expect(page).toHaveURL(/\/login\?error=invalid/);
  await expect(page.getByRole("alert")).toHaveText("Sign-in failed. Try again.");
  expect(await cookie(page, SESSION)).toBeUndefined();
});

test("a code from another browser is useless without this browser's verifier", async ({ page, request }) => {
  // the attacker starts a login of their own and gets a code bound to their verifier
  const attacker = await request.get(`http://127.0.0.1:${api}/api/auth/social/google`, {
    params: { return_url: `${site}/auth/social/callback`, challenge: "A".repeat(43) },
    maxRedirects: 0,
  });
  const injected = attacker.headers()["location"]!;
  expect(injected).toContain("code=");

  // the victim's browser has started a login too (verifier cookie set), and is tricked into the attacker's callback
  // (the request context shares the browser's cookies; the redirect to the API is not followed)
  const started = await page.request.get(`${site}/auth/social/google`, { maxRedirects: 0 });
  expect(started.status()).toBe(302);
  expect(await cookie(page, SSO)).toBeDefined();

  await page.goto(injected);
  await expect(page).toHaveURL(/\/login\?error=invalid/);
  expect(await cookie(page, SESSION)).toBeUndefined();
  expect(await cookie(page, SSO)).toBeUndefined();
});

test("a callback without the verifier cookie is refused", async ({ page }) => {
  await page.goto(`${site}/auth/social/callback?code=${"x".repeat(43)}`);
  await expect(page).toHaveURL(/\/login\?error=invalid/);
  expect(await cookie(page, SESSION)).toBeUndefined();
});

test("the API's reasons are shown, nothing else is trusted", async ({ page }) => {
  await page.goto(`${site}/auth/social/callback?error=link_required`);
  await expect(page.getByRole("alert")).toContainText("Sign in with your password first");

  await page.goto(`${site}/login?error=%3Cscript%3Ealert(1)%3C%2Fscript%3E`);
  await expect(page.getByRole("alert")).toHaveText("Sign-in failed. Try again.");
});

test("only configured providers start a login", async ({ page }) => {
  expect((await page.goto(`${site}/auth/social/twitter`))?.status()).toBe(404);
});

test("the start of a login refuses to bounce to another site after sign-in", async ({ page }) => {
  await page.goto(`${site}/auth/social/google?next=${encodeURIComponent("//evil.example/x")}`);
  await page.waitForURL(`${site}/`);
});
