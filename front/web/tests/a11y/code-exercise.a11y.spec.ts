import AxeBuilder from "@axe-core/playwright";
import { expect, test, type Page } from "@playwright/test";

/**
 * Code exercises (ADR 0170) against the mock tenant API: the lesson page with the real runner, a sandboxed frame on
 * a mock content origin that sets the API's content security policies (api/packages/code-exercises/resources/runner-csp.json),
 * esbuild-wasm and the test harness. Covers:
 *  - WCAG 2.2 AA (axe) before and after a run, light (coffee, desktop) and dark (nightsky, 360 px), reduced motion on;
 *  - the keyboard: labelled editors, Ctrl+Enter, a Tab key that moves on, "Start over" with a confirmation;
 *  - JavaScript and TypeScript runs, failures, build errors and what is reported as an attempt;
 *  - containment: an exercise that reaches for the network, for the page around it or for a forged result, and
 *    one that never ends. The "attacker" listener (tests/mock-api/evil.mjs) must never hear from it, the page
 *    must stay usable and the runner must survive.
 */
const web = process.env.A11Y_WEB_PORT ?? "4392";
const mock = process.env.MOCK_API_PORT ?? "4390";
const evil = process.env.A11Y_EVIL_PORT ?? "4399";
const host = (slug: string) => `http://${slug}.app.localhost:${web}`;
const tenantOf = (project: string) => (project === "desktop" ? "coffee" : "nightsky");
const TAGS = ["wcag2a", "wcag2aa", "wcag21a", "wcag21aa", "wcag22aa"];
const SUM = 902;
const DOUBLE = 903;
const NETWORK = 904;
const ESCAPE = 905;
const LOOP = 906;
const LEARNER_LOOP = 907;
const SOLUTION = "export function sum(a, b) {\n  return a + b;\n}\n";

test.use({ contextOptions: { reducedMotion: "reduce" } });

async function scan(page: Page) {
  const results = await new AxeBuilder({ page }).withTags(TAGS).exclude("iframe").analyze();
  return results.violations.map((v) => `${v.id} (${v.impact}): ${v.nodes.slice(0, 3).map((n) => `${n.target.join(" ")} - ${n.any[0]?.message ?? n.failureSummary ?? ""}`).join(" | ")}`);
}

/** Opens a lesson and waits until the runner has loaded esbuild and said ready. */
async function open(page: Page, slug: string, topic: number) {
  await page.request.post(`http://127.0.0.1:${mock}/__mock/reset-code?tenant=${slug}`);
  await page.goto(`${host(slug)}/learn/1/${topic}`, { waitUntil: "load" });
  await expect(page.locator("ulams-code-exercise")).toHaveAttribute("runner", "ready", { timeout: 40_000 });
}

const attempts = async (page: Page, slug: string, topic: number) => (await (await page.request.get(`http://127.0.0.1:${mock}/__mock/code-attempts/${topic}?tenant=${slug}`)).json()) as Array<{ passed: number; total: number; score: number; verified: boolean; results: Array<{ name: string; status: string; message?: string }>; runner: string; files_hash: string }>;
const evilHits = async (page: Page) => ((await (await page.request.get(`http://127.0.0.1:${evil}/__hits`)).json()) as Array<{ path: string }>).filter((h) => !h.path.startsWith("/control"));
const summary = (page: Page) => page.locator("[data-summary]");
const editor = (page: Page, path: string) => page.getByRole("textbox", { name: `Code of ${path}` });
const run = (page: Page) => page.getByRole("button", { name: "Run tests" });
/** The run button whatever it says: while the tests run it is disabled, busy and reads "Running the tests…". */
const runButton = (page: Page) => page.locator("ulams-code-exercise [data-run]");

test.describe("the lesson", () => {
  test("shows the task, a labelled editor per file, the tests and the results, and passes axe before and after a run", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await open(page, slug, SUM);

    await expect(page.getByRole("heading", { level: 2, name: "The task" })).toBeVisible();
    await expect(page.locator(".u-ce__task strong")).toHaveText("sum");
    await expect(editor(page, "sum.js")).toHaveValue("export function sum(a, b) {\n  return 0;\n}\n");
    await expect(page.locator("label[for]", { hasText: "sum.js" })).toBeVisible();
    await expect(run(page)).toBeEnabled();
    await expect(page.getByText("does not count towards a certificate")).toBeVisible();
    // the tests are readable, closed by default
    await page.getByText("The tests", { exact: true }).click();
    await expect(page.locator(".u-ce__test pre")).toContainText("adds two numbers");
    expect(await scan(page)).toEqual([]);

    await run(page).click();
    await expect(summary(page)).toHaveText("0 of 3 tests passed.");
    await expect(page.locator("[data-results] li")).toHaveCount(3);
    expect(await scan(page)).toEqual([]);

    // nothing scrolls sideways at any width
    const overflowing = await page.evaluate(() => {
      const width = document.documentElement.clientWidth;
      return [...document.querySelectorAll("body *")]
        .filter((e) => e.getBoundingClientRect().right > width + 1 && !e.closest("pre, textarea, [hidden], iframe, details:not([open]) > :not(summary)"))
        .slice(0, 5)
        .map((e) => `${e.tagName.toLowerCase()}.${e.className}`);
    });
    expect(overflowing).toEqual([]);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
  });

  test("the runner frame is sandboxed without allow-same-origin, has no token in its URL and starts hidden", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await open(page, slug, SUM);
    const frame = page.locator("ulams-code-exercise iframe");
    expect(await frame.getAttribute("sandbox")).toBe("allow-scripts");
    expect(await frame.getAttribute("referrerpolicy")).toBe("no-referrer");
    expect(await frame.getAttribute("src")).toBe(`http://127.0.0.1:${mock}/runtime/code-runner/1/runner.html`);
    await expect(frame).toBeHidden();
  });
});

test.describe("keyboard and reduced motion", () => {
  test("a learner fixes the code with the keyboard, runs with Ctrl+Enter and the attempt is reported; Tab is not trapped", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await open(page, slug, SUM);

    await editor(page, "sum.js").focus();
    await page.keyboard.press("ControlOrMeta+A");
    await page.keyboard.insertText(SOLUTION);
    await page.keyboard.press("Control+Enter");
    await expect(summary(page)).toHaveText("All 3 tests passed.");
    await expect(page.locator("[data-saved]")).toHaveText("Attempt 1 saved.");

    const [attempt] = await attempts(page, slug, SUM);
    expect(attempt).toMatchObject({ passed: 3, total: 3, score: 100, verified: false, runner: "browser:1" });
    expect(attempt!.files_hash).toMatch(/^[a-f0-9]{64}$/);
    expect(attempt!.results.map((r) => r.status)).toEqual(["pass", "pass", "pass"]);
    await expect(page.locator("ulams-code-exercise")).toHaveAttribute("done", "");

    // Tab leaves the editor
    await editor(page, "sum.js").focus();
    await page.keyboard.press("Tab");
    await expect(editor(page, "sum.js")).not.toBeFocused();
  });

  test("start over asks first, and is reachable and answerable by keyboard", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await open(page, slug, SUM);
    await editor(page, "sum.js").fill("// my own code");

    await page.getByRole("button", { name: "Start over" }).focus();
    await page.keyboard.press("Enter");
    const group = page.getByRole("group", { name: "Start over" });
    await expect(group).toBeVisible();
    await expect(page.getByRole("button", { name: "Yes, start over" })).toBeFocused();
    await page.getByRole("button", { name: "Keep my code" }).click();
    await expect(editor(page, "sum.js")).toHaveValue("// my own code");

    await page.getByRole("button", { name: "Start over" }).click();
    await page.getByRole("button", { name: "Yes, start over" }).click();
    await expect(editor(page, "sum.js")).toHaveValue("export function sum(a, b) {\n  return 0;\n}\n");
    await expect(page.locator("ulams-code-exercise [data-live]")).toHaveText("Your code is back to the starting point.");
    await expect(page.getByRole("button", { name: "Start over" })).toBeFocused();
  });

  test("a draft survives a reload", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await open(page, slug, SUM);
    await editor(page, "sum.js").fill("// half done");
    await page.waitForTimeout(1000);
    await page.reload({ waitUntil: "load" });
    await expect(page.locator("ulams-code-exercise")).toHaveAttribute("runner", "ready", { timeout: 40_000 });
    await expect(editor(page, "sum.js")).toHaveValue("// half done");
  });
});

test.describe("running", () => {
  test("a TypeScript exercise is built and run", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await open(page, slug, DOUBLE);
    await editor(page, "double.ts").fill("export function double(n: number): number {\n  return n * 2;\n}\n");
    await run(page).click();
    await expect(summary(page)).toHaveText("All 2 tests passed.");
    expect((await attempts(page, slug, DOUBLE))[0]).toMatchObject({ passed: 2, total: 2 });
  });

  test("failures show their messages, are reported as an attempt, and do not complete the topic", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await open(page, slug, SUM);
    await run(page).click();
    await expect(summary(page)).toHaveText("0 of 3 tests passed.");
    const failed = page.locator("[data-results] li", { hasText: "adds two numbers" });
    await expect(failed).toContainText("Failed");
    await expect(failed).toContainText("Expected 3, received 0");
    await expect(page.locator("[data-saved]")).toHaveText("Attempt 1 saved.");
    await expect(page.locator("ulams-code-exercise")).not.toHaveAttribute("done", "");
    expect((await attempts(page, slug, SUM))[0]).toMatchObject({ passed: 0, total: 3, score: 0 });
  });

  test("a syntax error shows the file and line and is not reported as an attempt", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await open(page, slug, SUM);
    await editor(page, "sum.js").fill("export function sum(a, b) {\n  return a +;\n}\n");
    await run(page).click();
    await expect(summary(page)).toHaveText("Your code could not be built:");
    await expect(page.locator("[data-results]")).toContainText("sum.js:2:");
    expect(await attempts(page, slug, SUM)).toEqual([]);
    // and the runner is still fine
    await editor(page, "sum.js").fill(SOLUTION);
    await run(page).click();
    await expect(summary(page)).toHaveText("All 3 tests passed.");
  });

  test("an import of a package or a URL is a build error", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await open(page, slug, SUM);
    await editor(page, "sum.js").fill("import _ from 'lodash';\nexport const sum = (a, b) => _.add(a, b);\n");
    await run(page).click();
    await expect(page.locator("[data-results]")).toContainText("can only import its own files");
    expect(await attempts(page, slug, SUM)).toEqual([]);
  });
});

test.describe("containment", () => {
  test("the runner is an opaque origin: no cookies, no storage, no parent, no request with the app's cookie", async ({ page, context }, info) => {
    const slug = tenantOf(info.project.name);
    await context.addCookies([{ name: "app_session", value: "SECRET", url: host(slug) }]);
    const contentRequests: Array<{ url: string; cookie?: string; authorization?: string }> = [];
    context.on("request", (r) => {
      if (r.url().startsWith(`http://127.0.0.1:${mock}/runtime/`)) contentRequests.push({ url: r.url(), cookie: r.headers().cookie, authorization: r.headers().authorization });
    });
    await open(page, slug, SUM);
    await run(page).click();
    await expect(summary(page)).toHaveText("0 of 3 tests passed.");

    const frame = page.frames().find((f) => f.url().includes("/runtime/code-runner/1/runner.html"))!;
    const probe = await frame.evaluate(async ({ target, evilPort }) => {
      const attempt = async (fn: () => unknown) => {
        try {
          await fn();
          return "allowed";
        } catch (e) {
          return (e as Error).name;
        }
      };
      return {
        origin: window.origin,
        cookie: await attempt(() => document.cookie),
        local: await attempt(() => localStorage.getItem("x")),
        parent: await attempt(() => window.parent.document.title),
        top: await attempt(() => (window.top!.location.href = "http://example.com/")),
        fetchApp: await attempt(async () => (await fetch(`${target}/bff/api/profile/me`)).status),
        fetchEvil: await attempt(async () => (await fetch(`http://127.0.0.1:${evilPort}/runner`)).status),
      };
    }, { target: host(slug), evilPort: evil });
    expect(probe).toMatchObject({ origin: "null", cookie: "SecurityError", local: "SecurityError", parent: "SecurityError", top: "SecurityError" });
    expect(probe.fetchApp).not.toBe("allowed");
    expect(probe.fetchEvil).not.toBe("allowed");
    expect(contentRequests.length).toBeGreaterThan(0);
    expect(contentRequests.every((r) => r.cookie === undefined && r.authorization === undefined)).toBe(true);
    expect(await evilHits(page)).toEqual([]);
  });

  test("the policies of the two runner pages forbid eval, inline script and (for the page that runs the code) any request", async ({ page }) => {
    const get = async (file: string) => (await page.request.get(`http://127.0.0.1:${mock}/runtime/code-runner/1/${file}`)).headers()["content-security-policy"]!;
    const runner = await get("runner.html");
    const exec = await get("exec.html");
    for (const csp of [runner, exec]) {
      expect(csp).toContain("default-src 'none'");
      expect(csp).toContain("sandbox allow-scripts");
      // 'wasm-unsafe-eval' (esbuild's wasm) is not 'unsafe-eval'
      expect(csp).not.toContain("'unsafe-eval'");
      expect(csp).not.toContain("'unsafe-inline'");
    }
    expect(runner).toContain("script-src 'self' 'wasm-unsafe-eval'");
    expect(exec).not.toContain("wasm-unsafe-eval");
    expect(runner).toContain("connect-src 'self'");
    expect(exec).not.toMatch(/connect-src|img-src|frame-src|font-src|media-src/);
    expect(exec).toContain("worker-src blob:");
  });

  test("network probes of every kind are blocked and the attacker's server never hears from them", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    // a positive control: the listener does record requests, so an empty list below means something
    const control = `/control-${info.project.name}`;
    expect((await page.request.get(`http://127.0.0.1:${evil}${control}`)).ok()).toBe(true);
    await open(page, slug, NETWORK);
    await run(page).click();
    await expect(summary(page)).toHaveText("All 10 tests passed.", { timeout: 30_000 });
    // give any request that escaped time to arrive
    await page.waitForTimeout(1500);
    const all = (await (await page.request.get(`http://127.0.0.1:${evil}/__hits`)).json()) as Array<{ path: string }>;
    expect(all.map((h) => h.path)).toContain(control);
    expect(await evilHits(page)).toEqual([]);
  });

  test("the code has no window, parent, storage or postMessage and cannot forge a result", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await open(page, slug, ESCAPE);
    await run(page).click();
    await expect(summary(page)).toHaveText("All 2 tests passed.", { timeout: 30_000 });
    // only the two real tests are in the list: nothing forged got in
    await expect(page.locator("[data-results] li")).toHaveCount(2);
  });

  test("an endless loop is stopped at the limit, the page stays usable and the runner survives", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await open(page, slug, LOOP);
    const started = Date.now();
    await run(page).click();
    await expect(runButton(page)).toHaveAttribute("aria-busy", "true");
    // the lesson page is not frozen: the editor takes input while the code loops
    await editor(page, "work.js").fill("// typed while the tests were looping\n");
    await expect(editor(page, "work.js")).toHaveValue("// typed while the tests were looping\n");
    await expect(summary(page)).toContainText("took too long", { timeout: 15_000 });
    expect(Date.now() - started).toBeLessThan(12_000);
    await expect(run(page)).toBeEnabled();
    expect(await attempts(page, slug, LOOP)).toEqual([]);

    // the runner is still there: another run answers again (the file now has no loop and the test fails to import work)
    await run(page).click();
    await expect(summary(page)).not.toHaveText("The tests took too long and were stopped.", { timeout: 15_000 });
  });

  test("a learner's endless loop is stopped, and fixing it and running again works", async ({ page }, info) => {
    const slug = tenantOf(info.project.name);
    await open(page, slug, LEARNER_LOOP);
    await run(page).click();
    await expect(summary(page)).toContainText("took too long", { timeout: 15_000 });
    await editor(page, "count.js").fill("let n = 0;\nexport const count = () => n;\n");
    await run(page).click();
    await expect(summary(page)).toHaveText("All 1 tests passed.", { timeout: 15_000 });
    expect((await attempts(page, slug, LEARNER_LOOP))[0]).toMatchObject({ passed: 1, total: 1 });
  });
});
