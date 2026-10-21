import { describe, expect, it } from "vitest";
import { accountStrings } from "../src/lib/account-strings.ts";
import { formatLabel, formatMinutes } from "../src/lib/format.ts";
import { formatNoticeDate, pendingNoticeText, updateNoticeText } from "../src/lib/notices.ts";
import { fill, minutesLabel, plural, playerLocale, playerStrings, themeKey } from "../src/lib/player-strings.ts";
import { pageLocale } from "../src/lib/i18n.ts";

type Table = Record<string, unknown>;

const FORMS = ["zero", "one", "two", "few", "many", "other"];
const isPlural = (value: unknown): value is Record<string, string> => {
  if (!value || typeof value !== "object") return false;
  const entries = Object.entries(value as Table);
  return entries.length > 0 && entries.every(([k, v]) => typeof v === "string" && FORMS.includes(k));
};

/** Every text of a strings table by dotted path; the forms of a plural count as one entry (Polish has more forms than English). */
function entries(value: unknown, path = ""): Array<[string, string[]]> {
  if (typeof value === "string") return [[path, [value]]];
  if (isPlural(value)) return [[path, Object.values(value)]];
  if (value && typeof value === "object") return Object.entries(value as Table).flatMap(([k, v]) => entries(v, path ? `${path}.${k}` : k));
  return [];
}
const holders = (texts: string[]) => [...new Set(texts.flatMap((t) => t.match(/\{\w+\}/g) ?? []))].sort();

const TABLES: Array<[string, (lang: string) => unknown]> = [
  ["player strings", playerStrings],
  ["account strings", accountStrings],
];

describe.each(TABLES)("%s", (_name, table) => {
  const en = entries(table("en"));

  it("has every key in PL and ZH, none of them empty", () => {
    for (const locale of ["pl", "zh"]) {
      const other = entries(table(locale));
      expect(other.map(([path]) => path), locale).toEqual(en.map(([path]) => path));
      for (const [path, texts] of other) for (const text of texts) expect(text.trim().length, `${locale}.${path}`).toBeGreaterThan(0);
    }
  });

  it("has the plural forms the language needs", () => {
    const pl = table("pl") as Table;
    const zh = table("zh") as Table;
    const check = (value: unknown, locale: string, path = "") => {
      if (isPlural(value)) {
        const needed = new Intl.PluralRules(locale).resolvedOptions().pluralCategories.filter((c) => c !== "zero" || locale === "ar");
        for (const form of needed) expect(Object.keys(value), `${locale}.${path} needs "${form}"`).toContain(form);
      } else if (value && typeof value === "object") for (const [k, v] of Object.entries(value as Table)) check(v, locale, path ? `${path}.${k}` : k);
    };
    check(pl, "pl");
    check(zh, "zh");
  });

  it("keeps the placeholders of the English text in every language", () => {
    for (const locale of ["pl", "zh"]) {
      const other = new Map(entries(table(locale)));
      for (const [path, texts] of en) expect(holders(other.get(path)!), `${locale}.${path}`).toEqual(holders(texts));
    }
  });
});

describe("player strings", () => {
  it("picks the language from a tag", () => {
    expect(playerLocale("pl-PL")).toBe("pl");
    expect(playerLocale("zh_Hans")).toBe("zh");
    expect(playerLocale("de")).toBe("en");
    expect(playerLocale(undefined)).toBe("en");
    expect(themeKey("coffee")).toBe("coffee");
    expect(themeKey("unknown")).toBe("platform");
  });

  it("uses the Polish plural forms and the single Chinese form", () => {
    const forms = (l: string) => playerStrings(l).topicsCount;
    expect(plural("en", forms("en"), 1)).toBe("1 topic");
    expect(plural("pl", forms("pl"), 1)).toBe("1 temat");
    expect(plural("pl", forms("pl"), 3)).toBe("3 tematy");
    expect(plural("pl", forms("pl"), 5)).toBe("5 tematów");
    expect(plural("pl", forms("pl"), 22)).toBe("22 tematy");
    expect(plural("zh", forms("zh"), 7)).toBe("7 个主题");
  });

  it("writes minutes and hours in the language", () => {
    expect(minutesLabel("en", 5)).toBe("5 min");
    expect(minutesLabel("en", 75)).toBe("1 h 15 min");
    expect(minutesLabel("pl", 120)).toBe("2 godz.");
    expect(minutesLabel("zh", 75)).toBe("1 小时 15 分钟");
    expect(minutesLabel("pl", 0)).toBe("");
    expect(formatMinutes(90, "pl")).toBe("1 godz. 30 min");
    expect(formatMinutes(90)).toBe("1 h 30 min");
  });

  it("names the formats in the language and falls back to English", () => {
    expect(formatLabel("video")).toBe("Video");
    expect(formatLabel("quiz", "pl")).toBe("Quiz");
    expect(formatLabel("project", "zh")).toBe("项目");
    expect(formatLabel("unknown-kind", "pl")).toBe("unknown-kind");
  });

  it("fills placeholders", () => {
    expect(fill(playerStrings("pl").quizQuestionOf, { n: 2, total: 5 })).toBe("Pytanie 2 z 5");
    expect(fill(playerStrings("zh").quizQuestionOf, { n: 2, total: 5 })).toBe("第 2 题，共 5 题");
  });

  it("words the Living Course notices in the language, with dates in the language", () => {
    expect(updateNoticeText({ date: "2026-10-09T10:00:00Z" }).title).toBe("Updated since you completed it, 9 October 2026.");
    expect(updateNoticeText({ date: "2026-10-09T10:00:00Z" }, "pl").title).toContain("października 2026");
    expect(updateNoticeText({ started: true, message: "Nowy przykład" }, "pl")).toEqual({ title: "Ta lekcja została zaktualizowana po rozpoczęciu przez Ciebie.", change: "Co się zmieniło: Nowy przykład" });
    expect(pendingNoticeText(null, "zh")).toContain("审核");
    expect(formatNoticeDate("2026-10-09T10:00:00Z", "zh")).toContain("2026");
  });

  it("lets a learner page set the page language for the catalogue components", () => {
    expect(pageLocale({ locals: { uiLocale: "pl" } })).toBe("pl");
    expect(pageLocale({ currentLocale: "en", locals: { uiLocale: "pl" } })).toBe("pl");
    expect(pageLocale({ currentLocale: "zh", locals: {} })).toBe("zh");
    expect(pageLocale({ url: new URL("http://x.test/pl/faq") })).toBe("pl");
    expect(pageLocale({ locals: {} })).toBeUndefined();
  });
});
