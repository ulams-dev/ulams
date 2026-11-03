import { ApiError, type InsightsEvidence } from "@ulams/sdk";
import { describe, expect, it } from "vitest";
import { IMPROVE_STRINGS } from "../../src/studio/improve-i18n.ts";
import { costText, evidenceCardOptions, improveErrorText, proposalStateText, reviewHref } from "../../src/studio/improve-model.ts";
import { INSIGHTS_LOCALES, INSIGHTS_STRINGS } from "../../src/studio/insights-i18n.ts";
import { formatterFor } from "../../src/studio/insights-model.ts";

const evidence: InsightsEvidence = {
  periodDays: 30,
  learners: 1234,
  minGroupSize: 10,
  struggleRate: 0.384,
  remediationRate: 0.2,
  recoveryRate: null,
  helpfulShare: 0.75,
  topReasons: [{ rule: "failed_attempts", count: 16 }, { rule: "a_new_rule", count: 2 }],
  wrongOptions: [{ optionId: "o2", label: "1:17", share: 0.38 }, { optionId: "other", label: "", share: 0.12 }],
  tutorOutOfScope: 0,
};

const placeholders = (text: string): string[] => [...text.matchAll(/\{(\w+)\}/g)].map((m) => m[1]!).sort();

describe("improve strings", () => {
  it.each(INSIGHTS_LOCALES.filter((l) => l !== "en"))("%s has every key of English with the same placeholders", (locale) => {
    const en = IMPROVE_STRINGS.en as unknown as Record<string, unknown>;
    const other = IMPROVE_STRINGS[locale] as unknown as Record<string, unknown>;
    expect(Object.keys(other).sort()).toEqual(Object.keys(en).sort());
    for (const key of Object.keys(en)) {
      if (typeof en[key] === "string") {
        expect(typeof other[key], key).toBe("string");
        expect((other[key] as string).length, key).toBeGreaterThan(0);
        expect(placeholders(other[key] as string), key).toEqual(placeholders(en[key] as string));
      }
    }
    expect(Object.keys(other.reasons as object).sort()).toEqual(Object.keys(en.reasons as object).sort());
  });

  it("names every rule the insights table knows, in every language", () => {
    for (const locale of INSIGHTS_LOCALES) {
      for (const rule of Object.keys(INSIGHTS_STRINGS[locale].reasons)) expect(IMPROVE_STRINGS[locale].reasons[rule], `${locale} ${rule}`).toBeTruthy();
    }
  });

  it("marks each language with its html lang", () => {
    expect(INSIGHTS_LOCALES.map((l) => IMPROVE_STRINGS[l].htmlLang)).toEqual(INSIGHTS_LOCALES.map((l) => INSIGHTS_STRINGS[l].htmlLang));
  });
});

describe("evidence card", () => {
  it("writes every rate as text, says when there is not enough data and merges the rest into 'other'", () => {
    const f = formatterFor("en");
    const card = evidenceCardOptions(f, IMPROVE_STRINGS.en, evidence, { sent: true, level: 4 });

    expect(card.intro).toBe("Results of 1,234 learners in the last 30 days, as numbers. No individual learner is shown here, and none is sent to the AI.");
    expect(card.figures.map((x) => [x.label, x.value, x.rate])).toEqual([
      ["Struggled here", "38%", 0.384],
      ["Were offered extra help", "20%", 0.2],
      ["Recovered after help", "Not enough data", null],
      ["Found the help useful", "75%", 0.75],
    ]);
    expect(card.reasons).toEqual(["repeated failed attempts (16)", "a new rule (2)"]);
    expect(card.wrong!.table.rows.map((r) => r.cells)).toEqual([["1:17", "38%"], ["Other wrong answers", "12%"]]);
    expect(card.sent).toEqual({ title: "What the AI receives", text: IMPROVE_STRINGS.en.sentText });
    expect(card.level).toBe(4);
    expect(card.lang).toBe("en");
  });

  it("leaves out what does not apply: no wrong options, no tutor figure, no 'what the AI receives' on the review", () => {
    const card = evidenceCardOptions(formatterFor("pl"), IMPROVE_STRINGS.pl, { ...evidence, wrongOptions: [], topReasons: [], tutorOutOfScope: 0 });
    expect(card.wrong).toBeNull();
    expect(card.sent).toBeNull();
    expect(card.reasons).toEqual([]);
    expect(card.figures.some((x) => x.label.includes("tutor"))).toBe(false);
    expect(card.lang).toBe("pl");

    const withTutor = evidenceCardOptions(formatterFor("en"), IMPROVE_STRINGS.en, { ...evidence, tutorOutOfScope: 3 });
    expect(withTutor.figures.at(-1)).toMatchObject({ label: expect.stringContaining("Tutor questions"), value: "3" });
    expect(withTutor.figures.at(-1)!.rate).toBeUndefined();
  });
});

describe("refusals and states", () => {
  const refuse = (status: number, code?: string) => new ApiError(status, "/x", code ? { code } : null, "x");

  it.each([
    [refuse(422, "small_group"), "Fewer than 10 learners"],
    [refuse(429, "daily_limit"), "limit of improvement proposals"],
    [refuse(503, "ai_disabled"), "AI is switched off"],
    [refuse(422, "budget_blocked"), "budget of this course"],
    [refuse(404, "not_builder_element"), "not built with the Course Builder"],
    [refuse(422, "element_not_improvable"), "Only lessons, text blocks and quiz questions"],
    [refuse(422, "no_sources"), "cites no passage"],
    [refuse(409, "no_course"), "no approved version"],
    [refuse(403), "Only the author of this course"],
    [refuse(500), "could not be created"],
    [new TypeError("offline"), "could not be created"],
  ])("explains %#", (error, sentence) => {
    expect(improveErrorText(IMPROVE_STRINGS.en, error, 10)).toContain(sentence);
  });

  it("uses the minimum group of the table in the small-group sentence, in every language", () => {
    for (const locale of INSIGHTS_LOCALES) expect(improveErrorText(IMPROVE_STRINGS[locale], new ApiError(422, "/x", { code: "small_group" }, "x"), 25)).toContain("25");
  });

  it("formats costs and states", () => {
    expect(costText(0.1234)).toBe("$0.12");
    expect(costText(0.001)).toBe("less than $0.01");
    expect(costText(0)).toBe("$0.00");
    expect(proposalStateText(IMPROVE_STRINGS.en, "ready")).toBe("ready for your review");
    expect(proposalStateText(IMPROVE_STRINGS.en, "analysing")).toBe("the AI is working on it");
    expect(proposalStateText(IMPROVE_STRINGS.en, "awaiting_analysis")).toBe("waiting to be analysed");
    expect(reviewHref("s1", "p1", "zh")).toBe("/studio/s/s1/updates/p1?lang=zh");
  });
});
