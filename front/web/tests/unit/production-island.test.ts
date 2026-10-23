// @vitest-environment jsdom
import axe from "axe-core";
import { afterEach, describe, expect, it, vi } from "vitest";
import { mountProduction } from "../../src/studio/production.ts";
import type { CourseMetricData } from "../../src/studio/production-model.ts";

const data = (over: Partial<CourseMetricData> = {}): CourseMetricData => ({
  course_id: 5,
  session_id: "01s0000000000000000000abcd",
  method_version: 1,
  computed_at: "2026-10-22T12:00:00+00:00",
  time: { generation_seconds: 464, ai_edit_seconds: 0, interview_seconds: 18, model_seconds: 902, author_minutes: null },
  cost: { build_micro_usd: 284100, maintenance_micro_usd: 0, llm_calls: 27, llm_calls_failed: 1 },
  counts: { lessons: 6, questions: 18, blocks: 35, cited_blocks: 35, learner_minutes_estimate: 60 },
  elements: { ai_elements: 59, accepted_unchanged: 49, revised_on_request: 4, edited_by_author: 6, unknown: 0, author_written: 2 },
  stages: [{ run_id: "r1", run_kind: "outline", stage: "outline", started_at: "", finished_at: "", seconds: 24, quality: "exact" }],
  ...over,
});

/** announce() writes after 50 ms so that the same message is read twice in a row. */
const announced = async () => {
  await new Promise((resolve) => setTimeout(resolve, 80));
  return document.querySelector("[data-announcer]")?.textContent;
};
const settle = () => new Promise((resolve) => setTimeout(resolve, 0));
const violations = async (root: HTMLElement) => (await axe.run(root, { rules: { "color-contrast": { enabled: false }, region: { enabled: false } } })).violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.html).join(" | ")}`);

async function mount(answer: () => Response | Promise<Response>, locale = "en", save = vi.fn()) {
  document.body.innerHTML = `<main><div data-studio-production data-course="5" data-locale="${locale}"></div></main><div data-announcer></div>`;
  const calls: string[] = [];
  const root = document.querySelector<HTMLElement>("[data-studio-production]")!;
  mountProduction(root, async (url) => (calls.push(url), answer()), save);
  await settle();
  await settle();
  return { root, calls, save };
}

const json = (body: unknown, status = 200) => new Response(JSON.stringify(body), { status });

afterEach(() => (document.body.innerHTML = ""));

describe("Production island", () => {
  it("loads the course through the BFF and shows time, cost, counts and the element split as text", async () => {
    const { root, calls } = await mount(() => json({ data: data() }));

    expect(calls).toEqual(["/studio/api/authoring-metrics/courses/5"]);
    expect(root.textContent).toContain("7 min 44 s");
    expect(root.textContent).toContain("$0.2841");
    expect(root.textContent).toContain("Not recorded. Review time is only recorded when your administrator turns it on.");
    expect(root.querySelector('[role="img"]')?.getAttribute("aria-label")).toContain("Kept as the AI wrote it 49 (83%)");
    expect(root.querySelectorAll("table")).toHaveLength(2);
    expect(root.querySelectorAll("table caption")).toHaveLength(2);
    expect(await violations(root)).toEqual([]);
  });

  it("makes a CSV and a JSON file from the loaded figures", async () => {
    const { root, save } = await mount(() => json({ data: data() }));

    root.querySelector<HTMLButtonElement>('[data-export="csv"]')!.click();
    root.querySelector<HTMLButtonElement>('[data-export="json"]')!.click();

    expect(save).toHaveBeenCalledTimes(2);
    const [csvCall, jsonCall] = save.mock.calls as [string, string, string][];
    expect(csvCall!.slice(0, 2)).toEqual(["authoring-metrics-course-5.csv", "text/csv"]);
    expect(csvCall![2]).toMatch(/^course_id,session_id,/);
    expect(JSON.parse(jsonCall![2])).toMatchObject({ course_id: 5, author_minutes: null });
    expect(await announced()).toBe("Downloaded authoring-metrics-course-5.json");
  });

  it("says there is nothing to classify when the AI wrote nothing", async () => {
    const { root } = await mount(() => json({ data: data({ elements: { ai_elements: 0, accepted_unchanged: 0, revised_on_request: 0, edited_by_author: 0, unknown: 0, author_written: 0 } }) }));
    expect(root.textContent).toContain("No AI elements to classify yet.");
    expect(root.querySelector('[role="img"]')).toBeNull();
  });

  it("explains unknown elements instead of guessing", async () => {
    const { root } = await mount(() => json({ data: data({ elements: { ai_elements: 59, accepted_unchanged: 0, revised_on_request: 0, edited_by_author: 0, unknown: 59, author_written: 0 } }) }));
    expect(root.textContent).toContain("Elements from before provenance was recorded are not guessed.");
  });

  it.each([
    [404, "not-built", "not made with the Course Builder"],
    [403, "forbidden", "belongs to another author"],
    [500, "error", "could not be loaded"],
  ])("answers %s with a sentence", async (status, state, words) => {
    const { root } = await mount(() => json({ message: "x" }, status));
    expect(root.querySelector(`[data-state="${state}"]`)?.textContent).toContain(words);
    expect(await announced()).toContain(words);
    expect(await violations(root)).toEqual([]);
  });

  it("retries after an error", async () => {
    let n = 0;
    const { root } = await mount(() => (++n === 1 ? json({}, 500) : json({ data: data() })));
    root.querySelector<HTMLButtonElement>("button")!.click();
    await settle();
    await settle();
    expect(root.textContent).toContain("$0.2841");
  });

  it("treats a network failure as an error with a retry", async () => {
    const { root } = await mount(() => Promise.reject(new Error("offline")));
    expect(root.querySelector('[data-state="error"] button')).not.toBeNull();
  });

  it("writes in Polish and Chinese", async () => {
    const pl = await mount(() => json({ data: data() }), "pl");
    expect(pl.root.textContent).toContain("Czas na etapach");
    expect(pl.root.textContent).toContain("Nie zapisano.");
    const zh = await mount(() => json({ data: data() }), "zh");
    expect(zh.root.textContent).toContain("各阶段耗时");
  });
});
