import { describe, expect, it } from "vitest";
import { fill, formatDate, plural, trainingLocale, trainingStrings, type TrainingLocale } from "../src/lib/training-strings.ts";

const locales: TrainingLocale[] = ["en", "pl", "zh"];

describe("training strings", () => {
  it("has every key in PL and ZH, none of them empty", () => {
    const keys = Object.keys(trainingStrings("en")).sort();
    for (const locale of ["pl", "zh"] as const) {
      const table = trainingStrings(locale) as unknown as Record<string, unknown>;
      expect(Object.keys(table).sort(), locale).toEqual(keys);
      for (const key of keys) {
        const value = table[key];
        const text = typeof value === "string" ? [value] : Object.values(value as Record<string, string>);
        expect(text.every((t) => t.trim().length > 0), `${locale}.${key}`).toBe(true);
      }
    }
  });

  it("keeps the placeholders of the English text in every language", () => {
    const en = trainingStrings("en") as unknown as Record<string, string | Record<string, string>>;
    const holders = (v: string | Record<string, string>) => [...new Set((typeof v === "string" ? [v] : Object.values(v)).flatMap((s) => s.match(/\{\w+\}/g) ?? []))].sort();
    for (const locale of ["pl", "zh"] as const) {
      const table = trainingStrings(locale) as unknown as Record<string, string | Record<string, string>>;
      for (const key of Object.keys(en)) expect(holders(table[key]!), `${locale}.${key}`).toEqual(holders(en[key]!));
    }
  });

  it("picks the locale from a language tag", () => {
    expect(trainingLocale("pl-PL")).toBe("pl");
    expect(trainingLocale("zh_Hans")).toBe("zh");
    expect(trainingLocale("de")).toBe("en");
    expect(trainingLocale(null)).toBe("en");
  });

  it("uses the three Polish plural forms and the single Chinese form", () => {
    const forms = (l: TrainingLocale) => trainingStrings(l).statusOverdue;
    expect(plural("en", forms("en"), 1)).toBe("Overdue by 1 day");
    expect(plural("en", forms("en"), 3)).toBe("Overdue by 3 days");
    expect(plural("pl", forms("pl"), 1)).toBe("Po terminie o 1 dzień");
    expect(plural("pl", forms("pl"), 3)).toBe("Po terminie o 3 dni");
    expect(plural("pl", forms("pl"), 5)).toBe("Po terminie o 5 dni");
    expect(plural("zh", forms("zh"), 3)).toBe("已逾期 3 天");
  });

  it("fills placeholders and formats dates in the locale, in UTC", () => {
    expect(fill("Due {date}", { date: "1 May" })).toBe("Due 1 May");
    expect(formatDate("en", "2026-10-21T23:30:00+00:00")).toBe("21 October 2026");
    expect(formatDate("pl", "2026-10-21T00:00:00+00:00")).toContain("października");
    expect(formatDate("zh", "2026-10-21T00:00:00+00:00")).toContain("2026");
    expect(formatDate("en", null)).toBe("");
    expect(formatDate("en", "nope")).toBe("");
    expect(locales).toHaveLength(3);
  });
});
