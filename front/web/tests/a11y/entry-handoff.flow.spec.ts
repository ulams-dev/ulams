import { expect, test, type Page } from "@playwright/test";

/**
 * Moving a signed-in learner between the hosts of one academy (ADR 0195, EP-3) through the reference frontend and the mock
 * tenant API: the main host and an entry host each keep a session of their own, a one-time code is the only thing that
 * travels in a URL, and a code is useless to anybody but the browser that asked for it, once, for that host, for that
 * academy. Hosts are example.test names that the browser maps to the web server.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const api = process.env.MOCK_API_PORT ?? "4390";
const mainHost = `coffee.app.localhost:${web}`;
const main = `http://${mainHost}`;
const salesHost = `sales.coffee.example.test:${web}`;
const sales = `http://${salesHost}`;
const SESSION = "ulams_session";
const VERIFIER = "ulams_handoff";

// the tenant API is `http://coffee.localhost` for the browser (social login redirects there): resolve it to the mock API
test.use({ launchOptions: { args: [`--host-resolver-rules=MAP coffee.localhost 127.0.0.1:${api}, MAP *.example.test 127.0.0.1`] } });
test.describe.configure({ mode: "serial" });

const control = (query: string) => fetch(`http://127.0.0.1:${api}/__mock/entry-points?${query}`).then((r) => r.json());
const issued = () => fetch(`http://127.0.0.1:${api}/__mock/handoffs`).then((r) => r.json() as Promise<Array<{ tenant: string; host: string }>>);

/** The two Playwright projects run the same specs side by side: what a spec changes in the mock it changes under its own name. */
const mine = () => test.info().project.name;

test.afterEach(async () => {
  await control(`recover=fresh2-${mine()}.coffee.example.test&enable=finance-${mine()}`);
});

const cookies = async (page: Page, url: string) => (await page.context().cookies(url)).filter((c) => [SESSION, VERIFIER].includes(c.name));
const session = async (page: Page, url: string) => (await cookies(page, url)).find((c) => c.name === SESSION);

function recordUrls(page: Page): string[] {
  const urls: string[] = [];
  page.on("request", (request) => urls.push(request.url()));
  return urls;
}

/** Signs in on the academy's own host with Google (the mock provider answers at once): a session of that host. */
async function signInOnMain(page: Page) {
  await page.goto(`${main}/auth/social/google`);
  await page.waitForURL(`${main}/`);
  const c = await session(page, main);
  expect(c?.value).toMatch(/^social-token-/);
  return c!.value;
}

const me = async (page: Page, origin: string) => {
  const response = await page.request.get(`http://127.0.0.1:${web}/bff/api/profile/me`, {
    headers: { Host: origin.replace("http://", ""), Cookie: (await cookies(page, origin)).filter((c) => c.name === SESSION).map((c) => `${c.name}=${c.value}`).join("; ") },
  });
  return { status: response.status(), body: response.status() === 200 ? ((await response.json()) as { data: { id: number } }).data : null };
};

test("signed in on the academy's host, the learner continues on an entry host with a session of its own and no token in any URL", async ({ page }) => {
  const mainToken = await signInOnMain(page);
  const urls = recordUrls(page);

  await page.goto(`${sales}/handoff/begin?from=${mainHost}&next=%2Fcourses%2F1`);
  await expect(page).toHaveURL(`${sales}/courses/1`);

  // each host has its own session: a different token, bound to its own host, httpOnly, no Domain
  const onSales = await session(page, sales);
  const onMain = await session(page, main);
  expect(onSales?.value).toMatch(/^handoff-[0-9a-f]{10}-/);
  expect(onMain?.value).toBe(mainToken);
  expect(onSales?.value).not.toBe(mainToken);
  expect(onSales?.httpOnly).toBe(true);
  expect(onSales?.domain.startsWith(".")).toBe(false);
  expect(onSales?.domain).not.toBe(onMain?.domain);
  // the verifier is used up
  expect((await cookies(page, sales)).find((c) => c.name === VERIFIER)).toBeUndefined();

  // the same learner on both hosts
  const [a, b] = [await me(page, main), await me(page, sales)];
  expect(a.status).toBe(200);
  expect(b.status).toBe(200);
  expect(b.body?.id).toBe(a.body?.id);

  // the one-time code was in a URL; no token ever was
  expect(urls.filter((u) => u.includes("/handoff/complete?code=")).length).toBe(1);
  expect(urls.filter((u) => u.includes(mainToken) || u.includes(onSales!.value) || /[?&]token=/.test(u))).toEqual([]);
  expect(page.url()).not.toContain("code=");
});

test("every page of the hand-off answers without caching and without a referrer", async ({ page }) => {
  await signInOnMain(page);
  const seen: Array<{ url: string; status: number; headers: Record<string, string> }> = [];
  page.on("response", (response) => {
    if (response.url().includes("/handoff/")) seen.push({ url: response.url(), status: response.status(), headers: response.headers() });
  });

  await page.goto(`${sales}/handoff/begin?from=${mainHost}`);
  await page.waitForURL(`${sales}/`);

  expect(seen.map((s) => new URL(s.url).pathname)).toEqual(["/handoff/begin", "/handoff/issue", "/handoff/complete"]);
  for (const s of seen) {
    expect(s.status, s.url).toBe(303);
    expect(s.headers["cache-control"], s.url).toContain("no-store");
    expect(s.headers["referrer-policy"], s.url).toBe("no-referrer");
  }
});

test("a code works once", async ({ page }) => {
  await signInOnMain(page);
  let completeUrl = "";
  page.on("request", (request) => {
    if (request.url().includes("/handoff/complete?code=")) completeUrl = request.url();
  });
  await page.goto(`${sales}/handoff/begin?from=${mainHost}`);
  await page.waitForURL(`${sales}/`);
  expect(completeUrl).not.toBe("");

  // replay in the same browser: the verifier cookie is gone, so the code is refused here already
  await page.context().clearCookies({ name: SESSION, domain: "sales.coffee.example.test" });
  await page.goto(completeUrl);
  await expect(page).toHaveURL(/\/login\?error=invalid/);
  expect(await session(page, sales)).toBeUndefined();
});

test("a code taken from the URL is useless in another browser, even one that started a hand-off of its own", async ({ page, browser }) => {
  await signInOnMain(page);
  let stolen = "";
  page.on("request", (request) => {
    if (request.url().includes("/handoff/complete?code=")) stolen = request.url();
  });
  await page.goto(`${sales}/handoff/begin?from=${mainHost}`);
  await page.waitForURL(`${sales}/`);

  // the attacker starts a hand-off of their own (not signed in on the academy's host: they stop at the sign-in), which
  // leaves a verifier cookie of their own at the entry host, and then brings the stolen code
  const attacker = await browser.newContext();
  const evil = await attacker.newPage();
  await evil.goto(`${sales}/handoff/begin?from=${mainHost}`);
  await expect(evil).toHaveURL(/\/login\?next=/);
  expect((await attacker.cookies(sales)).some((c) => c.name === VERIFIER)).toBe(true);

  await evil.goto(stolen);
  await expect(evil).toHaveURL(/\/login\?error=invalid/);
  expect((await attacker.cookies(sales)).find((c) => c.name === SESSION)).toBeUndefined();
  await attacker.close();
});

test("a code needs the browser's verifier: without a hand-off started here there is nothing to redeem it with", async ({ page }) => {
  await page.goto(`${sales}/handoff/complete?code=${"x".repeat(43)}`);
  await expect(page).toHaveURL(/\/login\?error=invalid/);
  expect(await session(page, sales)).toBeUndefined();
});

test("only another host of this academy can be the other end", async ({ page }) => {
  await signInOnMain(page);
  const before = (await issued()).length;

  // another academy's host, an unknown host, an address, a scheme, a port that is not this deployment's
  for (const from of ["nightsky.app.localhost", "nobody.coffee.example.test", "10.0.0.1", "http://evil.example", `coffee.app.localhost:9999`, "evil.example/path", ""]) {
    const response = await page.goto(`${sales}/handoff/begin?from=${encodeURIComponent(from)}`);
    expect(response?.status(), `begin from ${from}`).toBe(404);
  }
  for (const to of ["nightsky.app.localhost", "nobody.coffee.example.test", "coffee.localhost", "10.0.0.1", "evil.example", `sales.coffee.example.test:9999`]) {
    const response = await page.goto(`${main}/handoff/issue?to=${encodeURIComponent(to)}&challenge=${"C".repeat(43)}`);
    expect(response?.status(), `issue to ${to}`).toBe(404);
  }
  // a host of this host's own name is not a hand-off either
  expect((await page.goto(`${main}/handoff/issue?to=${encodeURIComponent(mainHost)}&challenge=${"C".repeat(43)}`))?.status()).toBe(404);
  expect((await issued()).length, "no code was issued").toBe(before);
});

test("an entry point that is not active is not a target", async ({ page }) => {
  await signInOnMain(page);
  const before = (await issued()).length;
  // "finance" is looked up by no other test, so the answer is the platform's current one
  await control(`disable=finance-${mine()}`);

  const response = await page.goto(`${main}/handoff/issue?to=${encodeURIComponent(`finance-${mine()}.coffee.example.test:${web}`)}&challenge=${"C".repeat(43)}`);
  expect(response?.status()).toBe(404);
  expect((await issued()).length).toBe(before);
});

test("an academy host that cannot be checked right now is 'try again', never 'yes'", async ({ page }) => {
  await signInOnMain(page);
  await control(`outage=fresh2-${mine()}.coffee.example.test`);

  const response = await page.goto(`${main}/handoff/issue?to=${encodeURIComponent(`fresh2-${mine()}.coffee.example.test:${web}`)}&challenge=${"C".repeat(43)}`);
  expect(response?.status()).toBe(503);
});

test("not signed in at the source: sign in first, no code is made", async ({ page }) => {
  const before = (await issued()).length;

  await page.goto(`${main}/handoff/issue?to=${encodeURIComponent(salesHost)}&challenge=${"C".repeat(43)}&next=%2Fcourses%2F1`);

  await expect(page).toHaveURL(/\/login\?next=%2Fhandoff%2Fissue/);
  expect((await issued()).length).toBe(before);
  expect(await session(page, sales)).toBeUndefined();
});

test("the hand-off goes on to where the learner was, and only to a page of this site", async ({ page }) => {
  await signInOnMain(page);

  await page.goto(`${sales}/handoff/begin?from=${mainHost}&next=${encodeURIComponent("//evil.example/x")}`);
  await expect(page).toHaveURL(`${sales}/`);
});

test("signing out everywhere asks the API to end every session, with this host's own token", async ({ page }) => {
  await signInOnMain(page);
  await page.goto(`${sales}/handoff/begin?from=${mainHost}`);
  await page.waitForURL(`${sales}/`);
  const token = (await session(page, sales))!.value;

  const response = await page.request.post(`http://127.0.0.1:${web}/logout?everywhere=1`, {
    headers: { Host: salesHost, Origin: sales, Cookie: `${SESSION}=${token}` },
    maxRedirects: 0,
  });

  expect(response.status()).toBe(303);
  expect(response.headers()["set-cookie"] ?? "").toMatch(new RegExp(`${SESSION}=deleted; Path=/; Expires=Thu, 01 Jan 1970`));
  const logged = (await fetch(`http://127.0.0.1:${api}/__mock/logouts`).then((r) => r.json())) as Array<{ tenant: string; everywhere: boolean; token: string }>;
  // the other project's run of this spec logs out too: look for this host's own token
  expect(logged.filter((l) => l.token === token)).toEqual([{ tenant: "coffee", everywhere: true, token }]);
});

test("a plain log out only clears this host's cookie", async ({ page }) => {
  await signInOnMain(page);
  const token = (await session(page, main))!.value;

  const response = await page.request.post(`http://127.0.0.1:${web}/logout`, {
    headers: { Host: mainHost, Origin: main, Cookie: `${SESSION}=${token}` },
    maxRedirects: 0,
  });

  expect(response.status()).toBe(303);
  // the API was not asked to end anything: the other sessions of the learner stay
  const logged = (await fetch(`http://127.0.0.1:${api}/__mock/logouts`).then((r) => r.json())) as Array<{ token: string }>;
  expect(logged.filter((l) => l.token === token)).toEqual([]);
});
