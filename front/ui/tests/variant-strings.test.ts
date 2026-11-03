import { describe, expect, it } from "vitest";
import { fill, hintReasonText, levelInSentence, levelName, plural, variantStrings } from "../src/lib/variant-strings.ts";

type Table = Record<string, unknown>;

/** Every leaf string of a table with its path, so nested records and plural forms are compared too. */
const leaves = (value: unknown, path = ""): Array<[string, string]> =>
  typeof value === "string" ? [[path, value]] : Object.entries(value as Table).flatMap(([key, child]) => leaves(child, path ? `${path}.${key}` : key));

const holders = (text: string) => [...new Set(text.match(/\{\w+\}/g) ?? [])].sort();

describe("variant strings", () => {
  const en = leaves(variantStrings("en"));

  it("has every English key in PL and ZH, none of them empty", () => {
    const isPlural = (path: string) => /(statusCarried|hintTooHard|hintTooEasy)\./.test(path);
    for (const locale of ["pl", "zh"] as const) {
      const table = leaves(variantStrings(locale));
      const keys = (rows: Array<[string, string]>) => rows.filter(([p]) => !isPlural(p)).map(([p]) => p).sort();
      expect(keys(table), locale).toEqual(keys(en));
      for (const [path, text] of table) expect(text.trim().length, `${locale}.${path}`).toBeGreaterThan(0);
    }
  });

  it("keeps the placeholders of the English text in every language", () => {
    for (const locale of ["pl", "zh"] as const) {
      const table = new Map(leaves(variantStrings(locale)));
      // a singular form may leave the number out ("the first module"), so plurals are compared on their `other` form
      for (const [path, text] of en.filter(([p]) => !/\.(one|few|many)$/.test(p))) {
        expect(table.get(path), `${locale}.${path}`).toBeDefined();
        expect(holders(table.get(path)!), `${locale}.${path}`).toEqual(holders(text));
      }
    }
  });

  it("gives Polish its three plural forms and Chinese the single one", () => {
    const pl = variantStrings("pl").statusCarried;
    expect(plural("pl", pl, 1)).toBe("1 ukończona lekcja została przeniesiona z poprzedniej wersji.");
    expect(plural("pl", pl, 3)).toBe("3 ukończone lekcje zostały przeniesione z poprzedniej wersji.");
    expect(plural("pl", pl, 5)).toBe("5 ukończonych lekcji zostało przeniesionych z poprzedniej wersji.");
    expect(plural("zh", variantStrings("zh").statusCarried, 4)).toBe("已从你之前的版本沿用 4 个已完成的课时。");
    expect(plural("en", variantStrings("en").statusCarried, 1)).toBe("1 completed lesson was carried over from your previous version.");
  });

  it("names the three levels in each language and keeps an author's own word", () => {
    expect(levelName("en", "expert")).toBe("Expert");
    expect(levelName("pl", "intermediate")).toBe("Średniozaawansowany");
    expect(levelName("zh", "beginner")).toBe("入门");
    expect(levelName("pl", "advanced")).toBe("advanced");
    expect(levelInSentence("pl", "intermediate")).toBe("średniozaawansowanym");
  });

  it("renders the hint reasons from their message keys in the learner's language", () => {
    const hard = { messageKey: "insights.reason.variant_too_hard", params: { modules: 2 }, message: "API text" };
    expect(hintReasonText("en", hard)).toBe("You are finding the first 2 modules hard; an easier version of this course may suit you better.");
    expect(hintReasonText("pl", hard)).toBe("Pierwsze 2 moduły sprawiają Ci trudność; łatwiejsza wersja tego kursu może lepiej Ci pasować.");
    expect(hintReasonText("zh", hard)).toContain("前 2 个模块");
    const easy = { messageKey: "insights.reason.variant_too_easy", params: { modules: 2, percent: 41.6 } };
    expect(hintReasonText("en", easy)).toContain("first 2 modules");
    expect(hintReasonText("en", easy)).toContain("about 42% of the usual time");
  });

  it("falls back to the API's own message for an unknown key or missing parameters, and to nothing", () => {
    expect(hintReasonText("pl", { messageKey: "insights.reason.other", message: "  From the API  " })).toBe("From the API");
    expect(hintReasonText("en", { messageKey: "insights.reason.variant_too_easy", params: { modules: 2 }, message: "Fallback" })).toBe("Fallback");
    expect(hintReasonText("en", { messageKey: "insights.reason.unknown" })).toBeNull();
    expect(hintReasonText("en", {})).toBeNull();
  });

  it("fills placeholders", () => {
    expect(fill(variantStrings("en").placementReason, { correct: 5, total: 6, level: "intermediate", label: "Expert" })).toBe(
      "You answered 5 of 6 intermediate questions correctly, so we suggest Expert."
    );
  });
});
