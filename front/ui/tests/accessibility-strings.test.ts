import { describe, expect, it } from "vitest";
import { accessibilityStrings } from "../src/lib/accessibility-strings.ts";

const flat = (value: string | string[]) => (typeof value === "string" ? [value] : value);

describe("accessibility statement strings", () => {
  it("has every key in PL and ZH, none of them empty, with the same number of list items", () => {
    const en = accessibilityStrings("en") as unknown as Record<string, string | string[]>;
    for (const locale of ["pl", "zh"] as const) {
      const table = accessibilityStrings(locale) as unknown as Record<string, string | string[]>;
      expect(Object.keys(table).sort(), locale).toEqual(Object.keys(en).sort());
      for (const key of Object.keys(en)) {
        expect(flat(table[key]!).length, `${locale}.${key}`).toBe(flat(en[key]!).length);
        expect(flat(table[key]!).every((t) => t.trim().length > 0), `${locale}.${key}`).toBe(true);
      }
    }
  });

  it("keeps the placeholders of the English text in every language", () => {
    const en = accessibilityStrings("en") as unknown as Record<string, string | string[]>;
    const holders = (v: string | string[]) => [...new Set(flat(v).flatMap((s) => s.match(/\{\w+\}/g) ?? []))].sort();
    for (const locale of ["pl", "zh"] as const) {
      const table = accessibilityStrings(locale) as unknown as Record<string, string | string[]>;
      for (const key of Object.keys(en)) expect(holders(table[key]!), `${locale}.${key}`).toEqual(holders(en[key]!));
    }
  });

  it("names WCAG 2.2 AA and EN 301 549 in every language", () => {
    for (const locale of ["en", "pl", "zh"]) {
      const text = accessibilityStrings(locale).targetText;
      expect(text, locale).toContain("WCAG 2.2");
      expect(text, locale).toContain("EN 301 549");
    }
  });
});
