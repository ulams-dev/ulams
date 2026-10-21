import { readFileSync } from "node:fs";
import { fileURLToPath } from "node:url";
import { describe, expect, it } from "vitest";
import type { CourseElements, CourseInsightsFull } from "@ulams/sdk";
import { fill, INSIGHTS_LOCALES, INSIGHTS_STRINGS, pickInsightsLocale } from "../../src/studio/insights-i18n.ts";
import { elementRows, formatterFor, helpFigures, learnerRows, periodLabel, progressFigures, quizFigures, rateText, supportFigures, timelineRows } from "../../src/studio/insights-model.ts";

const summary: CourseInsightsFull = {
  courseId: 1,
  periodDays: 30,
  minGroupSize: 10,
  enrolled: 48,
  smallGroup: false,
  learners: { enrolled: 48, active: 31, completed: 12, completionRate: 0.25 },
  quiz: { attempts: 57, averageScore: 0.68, passRate: 0.61 },
  statuses: { on_track: 36, struggling: 8, at_risk: 4 },
  nudges: { sent: 9, suppressed: 3, opened: 5, recoveryRate: null },
  remediation: { shown: 22, helpful: 14, notHelpful: 3, recoveryRate: 0.64 },
};

const elements: CourseElements = {
  courseId: 1,
  periodDays: 30,
  minGroupSize: 10,
  elements: [
    { elementKey: "question:3", label: "2.3 Question 3", hidden: false, activeLearners: 29, struggleRate: 0.41, remediationRate: 0.34, recoveryRate: null, topReasons: [{ rule: "failed_attempts", count: 12 }, { rule: "mystery_rule", count: 1 }] },
    { elementKey: "topic:14", label: "4.2 Milk texture", hidden: false, activeLearners: 12, struggleRate: 0, remediationRate: 0, recoveryRate: 0.5, topReasons: [] },
    { elementKey: "topic:15", label: "4.3 Rosetta", hidden: true, reason: "not_enough_learners" },
  ],
};

describe("insights strings", () => {
  const en = INSIGHTS_STRINGS.en;
  const placeholders = (s: string) => [...s.matchAll(/\{(\w+)\}/g)].map((m) => m[1]).sort();

  it.each(INSIGHTS_LOCALES.filter((l) => l !== "en"))("%s has every key of English, none empty, with the same placeholders", (locale) => {
    const t = INSIGHTS_STRINGS[locale];
    expect(Object.keys(t).sort()).toEqual(Object.keys(en).sort());
    for (const [key, value] of Object.entries(t)) {
      if (typeof value === "string") {
        expect(value.trim(), `${locale}.${key}`).not.toBe("");
        expect(placeholders(value), `${locale}.${key}`).toEqual(placeholders((en as unknown as Record<string, string>)[key]!));
      }
    }
    expect(Object.keys(t.reasons).sort()).toEqual(Object.keys(en.reasons).sort());
    expect(Object.keys(t.signals).sort()).toEqual(Object.keys(en.signals).sort());
  });

  it("names every signal type the API can send and every rule it reports", () => {
    const php = readFileSync(fileURLToPath(new URL("../../../../api/packages/learner-insights/src/Signals/SignalType.php", import.meta.url)), "utf8");
    const types = [...php.matchAll(/public const [A-Z_]+ = '([a-z_]+\.[a-z_]+)';/g)].map((m) => m[1]!);
    expect(types.length).toBeGreaterThan(15);
    for (const locale of INSIGHTS_LOCALES) {
      for (const type of types) expect(INSIGHTS_STRINGS[locale].signals[type], `${locale} ${type}`).toBeTruthy();
    }
    const rules = ["inactivity", "struggling_elements", "failed_attempts", "slow_element", "abandoned_element", "repetition"];
    for (const locale of INSIGHTS_LOCALES) for (const rule of rules) expect(INSIGHTS_STRINGS[locale].reasons[rule], `${locale} ${rule}`).toBeTruthy();
  });

  it("fills placeholders and leaves an unknown one visible", () => {
    expect(fill("Hidden: fewer than {n} learners", { n: 10 })).toBe("Hidden: fewer than 10 learners");
    expect(fill("{a} and {b}", { a: "x" })).toBe("x and {b}");
  });

  it("picks the language from ?lang=, then Accept-Language, then English", () => {
    expect(pickInsightsLocale("pl", "zh-CN,zh;q=0.9")).toBe("pl");
    expect(pickInsightsLocale(null, "zh-CN,zh;q=0.9,en;q=0.8")).toBe("zh");
    expect(pickInsightsLocale(null, "de-DE,pl;q=0.8,en;q=0.5")).toBe("pl");
    expect(pickInsightsLocale("xx", "fr")).toBe("en");
    expect(pickInsightsLocale("PL", null)).toBe("pl");
    expect(pickInsightsLocale(undefined, undefined)).toBe("en");
  });
});

describe("insights view model", () => {
  it("formats the figures in the reader's language", () => {
    const en = formatterFor("en");
    expect(progressFigures(en, summary).map((x) => [x.label, x.value])).toEqual([
      ["Enrolled", "48"],
      ["Active in the period", "31"],
      ["Completed the course", "12"],
      ["Completion rate", "25%"],
    ]);
    expect(quizFigures(en, summary).map((x) => x.value)).toEqual(["57", "61%", "68%"]);
    expect(supportFigures(en, summary).map((x) => x.value)).toEqual(["36", "8", "4"]);
    const pl = formatterFor("pl");
    expect(progressFigures(pl, summary)[0]!.label).toBe("Zapisani");
    expect(formatterFor("zh").percent(0.25)).toBe("25%");
  });

  it("says why a rate is missing instead of showing zero", () => {
    const f = formatterFor("en");
    expect(rateText(f, null)).toBe("Not enough data");
    const noQuiz = { ...summary, quiz: { attempts: 0, averageScore: null, passRate: null } };
    expect(quizFigures(f, noQuiz).map((x) => x.value)).toEqual(["0", "Not enough data", "Not enough data"]);
    const help = helpFigures(f, summary);
    expect(help.find((x) => x.label === "Recovered after a nudge")).toMatchObject({ value: "Not enough data", note: "Shown once at least 10 cases are closed." });
    expect(help.find((x) => x.label === "Recovered after help")!.note).toBeUndefined();
  });

  it("shows withheld sections as withheld, with no figures to read", () => {
    const rows = elementRows(formatterFor("en"), elements);
    expect(rows.map((r) => r.key)).toEqual(["question:3", "topic:14", "topic:15"]);
    expect(rows[2]).toEqual({ key: "topic:15", label: "4.3 Rosetta", withheld: true });
    expect(rows[0]).toMatchObject({ active: "29", struggle: { rate: 0.41, text: "41%" }, helped: { text: "34%" }, recovered: { rate: null, text: "Not enough data" } });
  });

  it("renders reasons from keys and keeps an unknown key rather than dropping it", () => {
    const rows = elementRows(formatterFor("en"), elements);
    expect(rows[0]!.reasons).toBe("Repeated failed attempts (12); mystery_rule (1)");
    expect(rows[1]!.reasons).toBe("None recorded");
    expect(elementRows(formatterFor("pl"), elements)[0]!.reasons).toContain("Powtarzane nieudane próby");
  });

  it("labels the learners and their timeline", () => {
    const f = formatterFor("en");
    expect(learnerRows(f, [{ userId: 21, name: "Ada Lovelace", status: "at_risk", since: "2026-10-01T10:00:00+00:00", reasons: ["a", "b"] }, { userId: 22, name: "", status: "struggling", since: "bad", reasons: [] }])).toEqual([
      { userId: 21, name: "Ada Lovelace", status: "At risk", reasons: "a; b", since: "Oct 1, 2026" },
      { userId: 22, name: "#22", status: "Struggling", reasons: "–", since: "–" },
    ]);
    const rows = timelineRows(f, [{ at: "2026-10-01T10:00:00+00:00", type: "quiz.answer", element: null, value: 0, data: null, source: "gift" }, { at: "2026-10-01T09:00:00+00:00", type: "new.thing", element: "Lesson", value: null, data: null, source: "x" }]);
    expect(rows.map((r) => [r.what, r.where])).toEqual([["Answered a question", "–"], ["new.thing", "Lesson"]]);
  });

  it("names the periods", () => {
    expect([7, 30, 90].map((p) => periodLabel(INSIGHTS_STRINGS.en, p as 7))).toEqual(["Last 7 days", "Last 30 days", "Last 90 days"]);
  });
});
