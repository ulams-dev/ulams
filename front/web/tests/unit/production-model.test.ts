import { describe, expect, it } from "vitest";
import { PRODUCTION_STRINGS } from "../../src/studio/production-i18n.ts";
import { barText, costFigures, elementClasses, flatRow, hoursFigure, productionFormatterFor, stageName, stageRows, toCsv, toJson, totals, type CourseMetricData } from "../../src/studio/production-model.ts";

const metric = (over: Partial<CourseMetricData> = {}): CourseMetricData => ({
  course_id: 12,
  session_id: "01s0000000000000000000abcd",
  method_version: 1,
  computed_at: "2026-10-22T12:00:00+00:00",
  time: { generation_seconds: 464, ai_edit_seconds: 0, interview_seconds: 18, model_seconds: 902.4, author_minutes: null },
  cost: { build_micro_usd: 284100, maintenance_micro_usd: 1520000, llm_calls: 27, llm_calls_failed: 1 },
  counts: { lessons: 6, questions: 18, blocks: 35, cited_blocks: 35, learner_minutes_estimate: 60 },
  elements: { ai_elements: 50, accepted_unchanged: 40, revised_on_request: 5, edited_by_author: 5, unknown: 0, author_written: 2 },
  stages: [
    { run_id: "r1", run_kind: "outline", stage: "outline", started_at: "", finished_at: "", seconds: 24, quality: "exact" },
    { run_id: "r2", run_kind: "generate", stage: "lessons", started_at: "", finished_at: "", seconds: 417, quality: "exact" },
    { run_id: "r3", run_kind: "generate", stage: "variant:expert", started_at: "", finished_at: null, seconds: null, quality: "incomplete" },
  ],
  ...over,
});

describe("durations", () => {
  const f = productionFormatterFor("en");
  it.each([
    [24, "24 s"],
    [417, "6 min 57 s"],
    [464, "7 min 44 s"],
    [3725, "1 h 2 min"],
    [0, "0 s"],
    [-5, "0 s"],
    [59.6, "1 min 0 s"],
  ])("%s seconds is %s", (seconds, text) => expect(f.duration(seconds)).toBe(text));

  it("is written the way each language writes it", () => {
    expect(productionFormatterFor("pl").duration(417)).toBe("6 min 57 s");
    expect(productionFormatterFor("pl").duration(3725)).toBe("1 godz. 2 min");
    expect(productionFormatterFor("zh").duration(417)).toBe("6 分 57 秒");
    expect(productionFormatterFor("zh").duration(3725)).toBe("1 小时 2 分钟");
  });
});

describe("money", () => {
  it("shows small amounts to four places and larger ones to two", () => {
    expect(productionFormatterFor("en").usd(284100)).toBe("$0.2841");
    expect(productionFormatterFor("en").usd(1520000)).toBe("$1.52");
    expect(productionFormatterFor("en").usd(0)).toBe("$0.0000");
  });
});

describe("stage table", () => {
  const f = productionFormatterFor("en");
  it("names stages and runs in words and gives every time or the reason there is none", () => {
    const rows = stageRows(f, metric().stages!);
    expect(rows.map((r) => r.cells)).toEqual([
      ["Outline", "Outline", "24 s", "Measured start to finish"],
      ["Lessons", "Writing", "6 min 57 s", "Measured start to finish"],
      ["expert", "Writing", "–", "Did not finish normally"],
    ]);
  });
  it("keeps an unknown stage under its own key", () => expect(stageName(PRODUCTION_STRINGS.en, "future-stage")).toBe("future-stage"));
});

describe("figures", () => {
  const f = productionFormatterFor("en");
  it("never shows author time as zero when it was not recorded", () => {
    const author = totals(f, metric()).find((x) => x.key === "author")!;
    expect(author.value).toBe(PRODUCTION_STRINGS.en.authorTimeNotRecorded);
    expect(author.value).not.toMatch(/^\d|0 min/);
    expect(totals(f, metric({ time: { ...metric().time, author_minutes: 135 } })).find((x) => x.key === "author")!.value).toBe("2 h 15 min of active screen time");
  });
  it("lists build and maintenance cost with the call counts", () => {
    expect(costFigures(f, metric()).map((x) => [x.key, x.value])).toEqual([["build", "$0.2841"], ["maintenance", "$1.52"], ["calls", "27"], ["failed", "1"]]);
  });
  it("splits the AI elements with shares of the AI elements only", () => {
    const rows = elementClasses(f, metric());
    expect(rows.map((r) => [r.key, r.count, r.text])).toEqual([["accepted", 40, "40 (80%)"], ["revised", 5, "5 (10%)"], ["edited", 5, "5 (10%)"], ["unknown", 0, "0 (0%)"]]);
    expect(barText(f, rows)).toBe("Share of AI elements: Kept as the AI wrote it 40 (80%); Revised by the AI on your request 5 (10%); Edited by an author 5 (10%)");
  });
  it("has no shares when the AI wrote nothing", () => {
    expect(elementClasses(f, metric({ elements: { ai_elements: 0, accepted_unchanged: 0, revised_on_request: 0, edited_by_author: 0, unknown: 0, author_written: 3 } })).every((r) => r.share === 0)).toBe(true);
  });
});

describe("files", () => {
  it("has the columns of the admin export, in order, with not recorded as an empty cell", () => {
    const csv = toCsv(metric()).trimEnd().split("\n") as [string, string];
    expect(csv[0]).toBe("course_id,session_id,method_version,computed_at,generation_seconds,ai_edit_seconds,interview_seconds,model_seconds,author_minutes,cost_build_micro_usd,cost_maintenance_micro_usd,llm_calls,llm_calls_failed,lessons,questions,blocks,cited_blocks,learner_minutes_estimate,hours_per_learner_hour,ai_elements,ai_accepted_unchanged,ai_revised_on_request,ai_edited_by_author,ai_unknown,author_elements");
    const cells = csv[1].split(",");
    expect(cells).toHaveLength(csv[0].split(",").length);
    expect(cells[8]).toBe("");
  });
  it("is numbers, identifiers and dates only: no cell can open with a formula character", () => {
    for (const cell of toCsv(metric()).trim().split("\n")[1]!.split(",")) expect(cell).not.toMatch(/^[=+\-@]/);
  });
  it("is JSON with null for what was not recorded", () => {
    expect(JSON.parse(toJson(metric()))).toMatchObject({ course_id: 12, author_minutes: null, ai_accepted_unchanged: 40 });
    expect(Object.keys(flatRow(metric()))).toHaveLength(25);
  });
});

describe("hours per learner hour", () => {
  const f = productionFormatterFor("en");
  const hours = (over: Partial<NonNullable<CourseMetricData["hours"]>> = {}): CourseMetricData => metric({ hours: { work_hours: 2.1667, learner_hours: 1, hours_per_learner_hour: 2.17, reason: null, formula: "x", ...over } });

  it("states the figure and what it was made from", () => {
    const h = hoursFigure(f, hours());
    expect(h).toEqual({ value: "2.17 h of work per hour of learner time", inputs: "Work: 2 h 10 min. Learner time: 1 h 0 min.", figure: true });
  });
  it("says why there is no figure instead of showing one", () => {
    const none = hoursFigure(f, hours({ work_hours: null, hours_per_learner_hour: null, reason: "author_time_not_recorded" }));
    expect(none.figure).toBe(false);
    expect(none.value).toContain("author time is not recorded");
    expect(hoursFigure(f, hours({ hours_per_learner_hour: null, reason: "no_learner_minutes" })).value).toContain("no declared minutes");
    expect(hoursFigure(f, metric()).figure).toBe(false);
  });
  it("is written in Polish and Chinese", () => {
    expect(hoursFigure(productionFormatterFor("pl"), hours()).value).toBe("2,17 godz. pracy na godzinę czasu kursanta");
    expect(hoursFigure(productionFormatterFor("zh"), hours()).value).toBe("每 1 小时学员学习时间需 2.17 小时工作");
  });
  it("is in the file, empty when there is none", () => {
    expect(flatRow(hours()).hours_per_learner_hour).toBe(2.17);
    expect(flatRow(metric()).hours_per_learner_hour).toBeNull();
  });
});

describe("strings", () => {
  it("have the same keys in English, Polish and Chinese", () => {
    const keys = (o: object): string[] => Object.entries(o).flatMap(([k, v]) => (typeof v === "object" ? keys(v).map((s) => `${k}.${s}`) : [k])).sort();
    expect(keys(PRODUCTION_STRINGS.pl)).toEqual(keys(PRODUCTION_STRINGS.en));
    expect(keys(PRODUCTION_STRINGS.zh)).toEqual(keys(PRODUCTION_STRINGS.en));
  });
  it("claim no benchmark: no industry figure appears in any language", () => {
    const all = JSON.stringify(PRODUCTION_STRINGS);
    for (const banned of ["Chapman", "Brandon Hall", "716", "79 h", "184 h", "490 h", "49 h", "industry average", "benchmark"]) expect(all.toLowerCase()).not.toContain(banned.toLowerCase());
    expect(all).not.toMatch(/\b(49|79|184|490|716)\s*(h|hours|godz|小时)/i);
  });
});
