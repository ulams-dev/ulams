/* eslint-disable @typescript-eslint/no-explicit-any */
// Plays the playback fixtures through the legacy front's <ScormPreview> (front/src/lib/scorm-player) and
// compares what happened with the golden traces in api/packages/scorm/tests/fixtures/playback/golden/react-player.
// The same golden file must match for the legacy front's and the admin's service worker.
import { expect, test, type Page } from "@playwright/test";
import { PINNED_SETTINGS, collectApiState, loadTrace } from "./helpers";

type Scenario = { name: string; fixture: string; query?: string; mode?: "default" | "community" };

const scenarios: Scenario[] = [
  { name: "scorm12-session.passed", fixture: "scorm12-session", query: "outcome=passed" },
  { name: "scorm12-session.failed", fixture: "scorm12-session", query: "outcome=failed" },
  { name: "scorm12-session.resume", fixture: "scorm12-session", query: "phase=resume" },
  { name: "scorm12-mastery.below", fixture: "scorm12-mastery", query: "score=65" },
  { name: "scorm12-mastery.above", fixture: "scorm12-mastery", query: "score=85" },
  { name: "scorm2004-session.passed", fixture: "scorm2004-session", query: "outcome=passed" },
  { name: "scorm2004-session.failed", fixture: "scorm2004-session", query: "outcome=failed" },
  { name: "scorm2004-session.no-completion", fixture: "scorm2004-session", query: "scenario=no-completion" },
  { name: "scorm2004-session.resume", fixture: "scorm2004-session", query: "phase=resume" },
  { name: "community.scorm12-session.passed", fixture: "scorm12-session", query: "outcome=passed", mode: "community" },
  { name: "community.scorm2004-session.passed", fixture: "scorm2004-session", query: "outcome=passed", mode: "community" },
];

async function play(page: Page, scenario: Scenario) {
  await page.request.get("/__mock/reset");
  const uuid = `${scenario.fixture}~${scenario.query ?? ""}`;
  const errors: string[] = [];
  page.on("pageerror", (error) => errors.push(error.message));
  await page.goto(`/?uuid=${encodeURIComponent(uuid)}&mode=${scenario.mode ?? "default"}`);
  await page.waitForFunction(() => (window as any).__host.done !== null, undefined, { timeout: 30_000 });

  const run = await page.evaluate(collectApiState, PINNED_SETTINGS);
  const host = await page.evaluate(() => {
    const w = window as any;
    return { sco: w.__host.done.trace, posts: w.__host.posts, gets: w.__host.gets };
  });

  const requests: string[] = await (await page.request.get("/__mock/requests")).json();
  expect(errors, "the harness page must not throw").toEqual([]);
  return { fixture: scenario.fixture, query: scenario.query ?? "", mode: scenario.mode ?? "default", ...host, ...run, requests };
}

for (const scenario of scenarios) {
  test(`${scenario.name} matches its golden trace`, async ({ page, baseURL }) => {
    const trace = await play(page, scenario);
    const { matchGolden, normalise } = await loadTrace();
    const result = matchGolden("react-player", scenario.name, normalise(trace, { origins: [baseURL as string] }));
    expect(result.ok, result.message).toBe(true);
  });
}

test("every fixture call returned and the session ended", async ({ page }) => {
  const trace = await play(page, scenarios[0]);
  expect((trace.sco as any[]).some((entry) => entry.note === "api-not-found")).toBe(false);
  expect((trace.sco as any[]).every((entry) => entry.exception === undefined)).toBe(true);
});
