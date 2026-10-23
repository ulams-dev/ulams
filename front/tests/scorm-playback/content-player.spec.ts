/* eslint-disable @typescript-eslint/no-explicit-any */
// Plays the playback fixtures through the API content player (api/packages/scorm/resources/content-player,
// published to <slug>.content.<base>/scorm/_player/ and used by the reference front) with the vendored
// scorm-again, and compares what happened with the golden traces in
// api/packages/scorm/tests/fixtures/playback/golden/content-player.
// The LMS is the mock in server.mjs: it answers GET /api/scorm/content/{sco} with a canned launch `cmi` in the
// shape of the API's field strategies and accepts POST .../track. The stored tracking is not simulated here;
// the track requests the player sends are what the golden traces pin (order, body, headers).
import { expect, test, type Page } from "@playwright/test";
import { PINNED_SETTINGS, collectApiState, loadTrace } from "./helpers";

type Scenario = { name: string; fixture: string; variant: "fresh" | "resume" | "mastery"; query?: string };

const scenarios: Scenario[] = [
  { name: "scorm12-session.passed", fixture: "scorm12-session", variant: "fresh", query: "outcome=passed" },
  { name: "scorm12-session.failed", fixture: "scorm12-session", variant: "fresh", query: "outcome=failed" },
  { name: "scorm12-session.resume", fixture: "scorm12-session", variant: "resume", query: "phase=resume" },
  { name: "scorm12-mastery.below-as-sent-today", fixture: "scorm12-mastery", variant: "fresh", query: "score=65" },
  { name: "scorm12-mastery.above-as-sent-today", fixture: "scorm12-mastery", variant: "fresh", query: "score=85" },
  { name: "scorm12-mastery.below-with-mastery-score", fixture: "scorm12-mastery", variant: "mastery", query: "score=65" },
  { name: "scorm12-mastery.above-with-mastery-score", fixture: "scorm12-mastery", variant: "mastery", query: "score=85" },
  { name: "scorm2004-session.passed", fixture: "scorm2004-session", variant: "fresh", query: "outcome=passed" },
  { name: "scorm2004-session.failed", fixture: "scorm2004-session", variant: "fresh", query: "outcome=failed" },
  { name: "scorm2004-session.no-completion", fixture: "scorm2004-session", variant: "fresh", query: "scenario=no-completion" },
  { name: "scorm2004-session.resume", fixture: "scorm2004-session", variant: "resume", query: "phase=resume" },
];

const TOKEN = "fixture-token";
const MESSAGE_START = "This content could not be started. Close it and open the lesson again.";
const MESSAGE_LOAD = "This content could not be loaded. Check your connection and reload the page.";
const MESSAGE_EXPIRED = "This session has expired. Close the content and open the lesson again.";

/** In the player page only: keep an ordered log of every fetch call and the SCO's done message. */
async function instrument(page: Page) {
  await page.addInitScript(() => {
    const w = window as any;
    if (w.top !== w) return;
    w.__fetchLog = [];
    w.__scoDone = null;
    const original = w.fetch.bind(w);
    w.fetch = (input: any, init: any = {}) => {
      const headers: Record<string, string> = {};
      new Headers(init.headers ?? {}).forEach((value, key) => { headers[key] = value; });
      w.__fetchLog.push({
        method: init.method ?? "GET",
        url: String(input),
        headers,
        keepalive: init.keepalive === true,
        body: typeof init.body === "string" ? JSON.parse(init.body) : null,
      });
      return original(input, init);
    };
    window.addEventListener("message", (event) => {
      if (event.data && event.data.source === "ulams-scorm-fixture" && event.data.type === "done") {
        w.__scoDone = { trace: event.data.trace };
      }
    });
  });
}

const launchUrl = (origin: string, parts: { api?: string; sco?: string; token?: string | null }) => {
  const fragment = new URLSearchParams();
  fragment.set("api", parts.api ?? origin);
  if (parts.sco !== undefined) fragment.set("sco", parts.sco);
  if (parts.token !== null) fragment.set("token", parts.token ?? TOKEN);
  return `${origin}/scorm/_player/player.html#${fragment.toString()}`;
};

async function play(page: Page, origin: string, scenario: Scenario) {
  await page.request.get("/__mock/reset");
  await instrument(page);
  const errors: string[] = [];
  page.on("pageerror", (error) => errors.push(error.message));
  const sco = `${scenario.fixture}~${scenario.variant}~${scenario.query ?? ""}`;
  await page.goto(launchUrl(origin, { sco }));
  await page.waitForFunction(() => (window as any).__scoDone !== null, undefined, { timeout: 30_000 });

  const run = await page.evaluate(collectApiState, PINNED_SETTINGS);
  const host = await page.evaluate(() => {
    const w = window as any;
    return { sco: w.__scoDone.trace, fetchLog: w.__fetchLog, address: w.location.pathname + w.location.hash };
  });
  const requests: string[] = await (await page.request.get("/__mock/requests")).json();
  expect(errors, "the player page must not throw").toEqual([]);
  return { fixture: scenario.fixture, variant: scenario.variant, query: scenario.query ?? "", ...host, ...run, requests };
}

for (const scenario of scenarios) {
  test(`${scenario.name} matches its golden trace`, async ({ page, baseURL }) => {
    const trace = await play(page, baseURL as string, scenario);
    const { matchGolden, normalise } = await loadTrace();
    const result = matchGolden("content-player", scenario.name, normalise(trace, { origins: [baseURL as string] }));
    expect(result.ok, result.message).toBe(true);
  });
}

test("the token never stays in the address bar or history", async ({ page, baseURL }) => {
  const trace = await play(page, baseURL as string, scenarios[0]);
  expect(trace.address).toBe("/scorm/_player/player.html");
});

test("every track request carries the tracking token and the API sees the same number of them", async ({ page, baseURL }) => {
  const trace = await play(page, baseURL as string, scenarios[0]);
  const posts = trace.fetchLog.filter((entry: any) => entry.method === "POST");
  expect(posts.length).toBeGreaterThan(0);
  for (const post of posts) {
    expect(post.headers["x-ulams-tracking-token"]).toBe(TOKEN);
    expect(post.keepalive).toBe(true);
  }
  expect(trace.requests.filter((entry: string) => entry.startsWith("POST ")).length).toBe(posts.length);
});

test.describe("the player refuses to start", () => {
  const cases: Array<[string, (origin: string) => string, string]> = [
    ["without a tracking token", (o) => launchUrl(o, { sco: "scorm12-session~fresh~", token: null }), MESSAGE_START],
    ["without an SCO", (o) => launchUrl(o, {}), MESSAGE_START],
    ["when the API address is not http(s)", (o) => launchUrl(o, { api: "javascript:alert(1)", sco: "scorm12-session~fresh~" }), MESSAGE_START],
    ["when the launch data points the frame at another origin", (o) => launchUrl(o, { sco: "scorm12-session~foreign-entry~" }), MESSAGE_START],
    ["when the API is unreachable", (o) => launchUrl(o, { api: "http://127.0.0.1:9", sco: "scorm12-session~fresh~" }), MESSAGE_LOAD],
    ["with a tracking token the API rejects", (o) => launchUrl(o, { sco: "scorm12-session~fresh~", token: "wrong" }), MESSAGE_EXPIRED],
    ["when the launch has expired", (o) => launchUrl(o, { sco: "scorm12-session~expired~" }), MESSAGE_EXPIRED],
  ];
  for (const [name, url, message] of cases) {
    test(name, async ({ page, baseURL }) => {
      await page.goto(url(baseURL as string));
      await expect(page.locator("#status")).toHaveText(message);
      await expect(page.locator("#status")).toBeVisible();
      // nothing is framed, so no SCO can find window.API
      expect(await page.locator("iframe").count()).toBe(0);
    });
  }
});
