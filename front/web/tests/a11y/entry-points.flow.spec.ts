import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * Entry points (ADR 0195, EP-2) through the reference frontend and the mock platform API: a host that no rule matches is
 * looked up, served as its academy in the entry point's theme, kept out of search engines when it is an internal
 * audience, never falls through to another academy when the platform cannot answer, and `/go/<key>` is the same context
 * without DNS. Hosts are example.test names that the browser maps to the web server.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const api = process.env.MOCK_API_PORT ?? "4390";
const main = `http://coffee.app.localhost:${web}`;
const sales = `http://sales.coffee.example.test:${web}`;
const course = `http://kubernetes.coffee.example.test:${web}`;
const ENTRY = "ulams_entry";
const SESSION = "ulams_session";
const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];

test.use({ launchOptions: { args: ["--host-resolver-rules=MAP *.example.test 127.0.0.1"] } });
test.describe.configure({ mode: "serial" });

const control = (query: string) => fetch(`http://127.0.0.1:${api}/__mock/entry-points?${query}`).then((r) => r.json() as Promise<{ outage: boolean; disabled: string[]; lookups: string[] }>);
const cookie = async (page: Page, name: string) => (await page.context().cookies()).find((c) => c.name === name);
/** A request to the web server under another host name (the request context does not use the browser's resolver rules). */
const asHost = (page: Page, host: string, path: string) =>
  page.request.get(`http://127.0.0.1:${web}${path}`, { headers: { Host: `${host}:${web}` }, maxRedirects: 0 });
const theme = (page: Page) => page.locator("html").getAttribute("data-theme");

test.afterEach(async () => {
  await control("outage=0&enable=sales&enable=kubernetes&enable=managers");
});

async function scan(page: Page) {
  const results = await new AxeBuilder({ page }).withTags(TAGS).exclude("iframe").analyze();
  return results.violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.slice(0, 3).map((n) => `${n.target.join(" ")} - ${n.any[0]?.message ?? n.failureSummary ?? ""}`).join(" | ")}`);
}

test("an entry host serves its academy in the entry point's theme", async ({ page }) => {
  const academy = await page.goto(`${main}/`);
  expect(academy?.status()).toBe(200);
  expect(await theme(page)).toBe("coffee");
  const academyTitle = await page.title();

  const response = await page.goto(`${sales}/`);
  expect(response?.status()).toBe(200);
  // the same academy (its pages and its name), another theme
  expect(await theme(page)).toBe("nightsky");
  expect(await page.title()).toBe(academyTitle);
  // the academy's own accent is gone with its preset; the entry point's colours are in
  // the accent of the entry point reaches the page through the contrast adjustment, as an inline style
  expect(await page.locator("style").evaluateAll((nodes) => nodes.map((n) => n.textContent ?? "").join("\n"))).toContain('[data-theme="nightsky"]{--ulams-color-accent');
});

test("a department host is kept out of search engines, the academy and a course host are not", async ({ page }) => {
  const internal = await page.goto(`${sales}/`);
  expect(internal?.headers()["x-robots-tag"]).toBe("noindex, nofollow");
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute("content", "noindex, nofollow");

  const open = await page.goto(`${course}/`);
  expect(open?.headers()["x-robots-tag"]).toBeUndefined();
  await expect(page.locator('meta[name="robots"][content*="noindex"]')).toHaveCount(0);
  expect(await page.locator("style").evaluateAll((nodes) => nodes.map((n) => n.textContent ?? "").join("\n"))).toContain("#0b6e4f");

  const academy = await page.goto(`${main}/`);
  expect(academy?.headers()["x-robots-tag"]).toBeUndefined();
});

test("a host that is no entry point gets no entry point", async ({ page }) => {
  // no rule, no entry point: the host is served as before this feature (the default academy, or the tenant picker)
  const response = await page.goto(`http://nobody.coffee.example.test:${web}/`);
  expect(response?.status()).toBe(200);
  await expect(page.locator("html")).not.toHaveAttribute("data-theme", "nightsky");
  expect(response?.headers()["x-robots-tag"]).toBeUndefined();
  expect((await control("")).lookups.some((l) => l.includes("nobody.coffee.example.test"))).toBe(true);
});

test("when the platform cannot answer the host says so; it never shows another academy", async ({ page }) => {
  await control("outage=1");
  const response = await asHost(page, "fresh.coffee.example.test", "/");
  expect(response.status()).toBe(503);
  expect(response.headers()["retry-after"]).toBe("5");
  expect(await response.text()).not.toContain("<html");
  // no session, no cookie of any kind on that answer
  expect(response.headers()["set-cookie"]).toBeUndefined();

  // a host looked up before keeps serving from the last answer
  await control("outage=0");
  expect((await page.goto(`${sales}/`))?.status()).toBe(200);
  await control("outage=1");
  expect((await page.goto(`${sales}/courses/1`))?.status()).toBe(200);
});

test("the academy's own pages are not looked up on the platform", async ({ page }) => {
  const before = (await control("")).lookups.length;
  await page.goto(`${main}/courses/1`);
  expect((await control("")).lookups.length).toBe(before);
});

test("access is the same on every host: the session is per host and the API sees one academy", async ({ page }) => {
  await page.goto(`${main}/courses/1`);
  const onMain = await asHost(page, "coffee.app.localhost", "/learn/1");
  const onSales = await asHost(page, "sales.coffee.example.test", "/learn/1");
  // no grant by host: the same answer, whatever it is
  expect(onSales.status()).toBe(onMain.status());

  const cookies = (await page.context().cookies()).filter((c) => c.name === SESSION);
  expect(cookies.length).toBeGreaterThan(0);
  for (const c of cookies) {
    // per-host sessions: a cookie is bound to the host that set it (no Domain attribute)
    expect(c.domain.startsWith(".")).toBe(false);
    expect(c.httpOnly).toBe(true);
  }
  expect(new Set(cookies.map((c) => c.domain)).size).toBe(cookies.length);
});

test("/go/<key> on the academy's host gives the same context without DNS, for the session", async ({ page }) => {
  await page.goto(`${main}/`);
  expect(await theme(page)).toBe("coffee");

  await page.goto(`${main}/go/sales?next=%2Fcourses%2F1`);
  await expect(page).toHaveURL(`${main}/courses/1`);
  expect(await theme(page)).toBe("nightsky");
  const entry = await cookie(page, ENTRY);
  expect(entry?.value).toBe("sales");
  expect(entry?.httpOnly).toBe(true);
  expect(entry?.sameSite).toBe("Lax");
  // a session cookie: it dies with the browser, and it names a key, never a token
  expect(entry?.expires).toBe(-1);
  expect(entry?.value).not.toMatch(/token/i);

  // the internal-audience rules follow
  const response = await page.goto(`${main}/`);
  expect(response?.headers()["x-robots-tag"]).toBe("noindex, nofollow");

  await page.goto(`${main}/go/main`);
  expect(await theme(page)).toBe("coffee");
  expect(await cookie(page, ENTRY)).toBeUndefined();
});

test("/go/<key> refuses what is not an entry point of this academy and what is not a path of this site", async ({ page }) => {
  expect((await page.goto(`${main}/go/nope`))?.status()).toBe(404);
  expect((await page.goto(`${main}/go/Not%20A%20Key`))?.status()).toBe(404);
  expect(await cookie(page, ENTRY)).toBeUndefined();

  await page.goto(`${main}/go/sales?next=${encodeURIComponent("//evil.example/x")}`);
  await expect(page).toHaveURL(`${main}/`);
  await page.goto(`${main}/go/main`);

  // on an entry host the entry point is the address: /go is not there
  expect((await page.goto(`${sales}/go/kubernetes`))?.status()).toBe(404);
  // and only the academy's own hosts: a platform host has no academy
  expect((await page.goto(`http://app.localhost:${web}/go/sales`))?.status()).toBe(404);
});

test("a /go cookie of an entry point that was disabled is dropped, not obeyed", async ({ page }) => {
  // "managers" has not been looked up by any test yet, so the answer is the platform's current one
  await control("disable=managers");
  await page.context().addCookies([{ name: ENTRY, value: "managers", url: main }]);

  const response = await page.goto(`${main}/`);
  expect(response?.status()).toBe(200);
  expect(await theme(page)).toBe("coffee");
  expect(response?.headers()["x-robots-tag"]).toBeUndefined();
  expect(await cookie(page, ENTRY)).toBeUndefined();
  await control("enable=managers");
});

for (const [name, url] of [["a department host", `${sales}/`], ["its course page", `${sales}/courses/1`], ["a course host", `${course}/`]] as const) {
  test(`${name}: no axe violations in the entry point's theme`, async ({ page }) => {
    const response = await page.goto(url, { waitUntil: "load" });
    expect(response?.status()).toBe(200);
    await page.evaluate(async () => {
      for (let y = 0; y < document.body.scrollHeight; y += 600) {
        window.scrollTo({ top: y, behavior: "instant" });
        await new Promise((r) => setTimeout(r, 40));
      }
      window.scrollTo({ top: 0, behavior: "instant" });
    });
    await page.waitForTimeout(300);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1), "nothing pushes the page sideways").toBe(true);
    const violations = await scan(page);
    expect(violations, violations.join("\n")).toEqual([]);
  });
}
