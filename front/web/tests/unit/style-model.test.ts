import { describe, expect, it } from "vitest";
import { STYLE_LOCALES, STYLE_STRINGS, fill } from "../../src/studio/style-i18n.ts";
import { changeLabel, changeRows, hasTheme, profileRows, styleFormatterFor, themeLinkFor, valueSections, valueText, withEdits, withoutItem, type StyleProfile } from "../../src/studio/style-model.ts";

const F1 = "frg_aaaaaaaaaaaa";
const F2 = "frg_bbbbbbbbbbbb";
const profile = (over: Partial<StyleProfile> = {}): StyleProfile => ({
  id: "01arz3ndektsv4rrffq69g5fav",
  name: "Northwind",
  scope: { type: "tenant", id: null },
  status: "suggested",
  sourceId: null,
  warnings: [],
  createdBy: 1,
  approvedBy: null,
  approvedAt: null,
  createdAt: "2026-10-22T09:30:00+00:00",
  fragments: { [F1]: { label: "§1 Voice", preview: "Write to the learner as you." }, [F2]: { label: "§2 Terms", preview: "Use learner." } },
  profile: {
    schemaVersion: 1,
    theme: { preset: "coffee", accent: "#1d6f5c", fonts: ["Inter"], citations: [F1] },
    voice: { person: "you", formality: 3, sentenceLength: 20, citations: [F1] },
    readingLevel: [{ language: "en", level: "grade-8", citations: [F1] }],
    terminology: [{ use: "learner", avoid: ["student", "user"], citations: [F2] }, { use: "lesson", avoid: [], citations: [] }],
    banned: { blocking: false, phrases: [{ phrase: "simply", citations: [F2] }] },
    components: { preferred: ["Callout"], disallowed: ["FlipCards"], citations: [F1] },
    imagery: { altTextRequired: true, decorativeAllowed: false, citations: [F1] },
    examples: [{ text: "You start small.", citations: [F1] }],
  },
  ...over,
});

describe("style model", () => {
  it("has the same keys in every language", () => {
    const keys = (o: object): string[] => Object.entries(o).flatMap(([k, v]) => (typeof v === "object" ? keys(v).map((x) => `${k}.${x}`) : [k]));
    for (const locale of STYLE_LOCALES) {
      expect(keys(STYLE_STRINGS[locale]), locale).toEqual(keys(STYLE_STRINGS.en));
      for (const [key, value] of Object.entries(STYLE_STRINGS[locale])) if (typeof value === "string") expect(value.trim(), `${locale}.${key}`).not.toBe("");
    }
    expect(fill("a {x} b", { x: 1 })).toBe("a 1 b");
  });

  it("makes table rows with the status and the scope in words", () => {
    const f = styleFormatterFor("en");
    expect(profileRows(f, [profile(), profile({ status: "approved", scope: { type: "entry_point", id: "x" } })])).toMatchObject([
      { name: "Northwind", status: "Suggested, not approved", statusKey: "suggested", scope: "The whole academy" },
      { status: "Approved", scope: "One entry point" },
    ]);
    expect(profileRows(styleFormatterFor("pl"), [profile()])[0]?.status).toBe("Propozycja, niezatwierdzony");
  });

  it("groups the values by part with the passages they cite, and marks the author's own", () => {
    const sections = valueSections(styleFormatterFor("en"), profile());
    expect(sections.map((s) => s.key)).toEqual(["theme", "voice", "readingLevel", "terminology", "banned", "components", "imagery", "examples"]);
    const byKey = Object.fromEntries(sections.flatMap((s) => s.items.map((i) => [i.key, i])));
    expect(byKey["voice.formality"]).toMatchObject({ text: "Formality (1 to 5): 3", own: false, citations: [{ id: F1, label: "§1 Voice" }] });
    expect(byKey["terminology.learner"]?.text).toBe("learner (instead of student, user)");
    expect(byKey["terminology.lesson"]).toMatchObject({ text: "lesson", own: true, citations: [] });
    expect(byKey["banned.blocking"]?.text).toBe("A banned phrase blocks publishing: no");
    expect(byKey["imagery.decorativeAllowed"]?.text).toBe("Decorative images allowed: no");
    expect(byKey["theme.fonts"]?.text).toBe("Fonts: Inter");
    expect(byKey["terminology.learner"]?.remove).toEqual({ part: "terminology", index: 0 });
    // an empty profile has no sections
    expect(valueSections(styleFormatterFor("en"), profile({ profile: { schemaVersion: 1 } }))).toEqual([]);
  });

  it("describes changes with a word and a symbol, never colour alone", () => {
    const f = styleFormatterFor("en");
    const p = profile({
      diff: [
        { path: "voice.formality", change: "changed", before: 2, after: 3, citations: [F1] },
        { path: "terminology.learner", change: "added", before: null, after: ["student"], citations: [F2] },
        { path: "banned.phrase.just", change: "removed", before: "just", after: null, citations: [] },
        { path: "banned.blocking", change: "changed", before: false, after: true, citations: [] },
      ],
    });
    const rows = changeRows(f, p);
    expect(rows[0]).toMatchObject({ kindLabel: "Changed", symbol: "~", label: "Voice: Formality (1 to 5)", before: "2", after: "3", citations: [{ label: "§1 Voice" }] });
    expect(rows[1]).toMatchObject({ kindLabel: "Added", symbol: "+", before: null, after: "student" });
    expect(rows[2]).toMatchObject({ kindLabel: "Removed", symbol: "−", before: "just", after: null });
    expect(rows[3]).toMatchObject({ label: "Banned phrases: A banned phrase blocks publishing", before: "no", after: "yes" });
    expect(changeLabel(f, "banned.phrase.simply")).toBe("Banned phrases: simply");
    expect(changeLabel(styleFormatterFor("zh"), "voice.person")).toBe("语气: 人称");
    expect(valueText(f, [true, "x"])).toBe("yes, x");
  });

  it("builds the theme link for the admin app and says whether there is a theme to hand over", () => {
    expect(themeLinkFor("http://coffee.admin.localhost", "01arz3ndektsv4rrffq69g5fav")).toBe("http://coffee.admin.localhost/configuration/settings/theme_settings?styleProfile=01arz3ndektsv4rrffq69g5fav");
    expect(themeLinkFor("", "x")).toBeNull();
    expect(themeLinkFor("not a url", "x")).toBeNull();
    expect(hasTheme(profile())).toBe(true);
    expect(hasTheme(profile({ profile: { theme: { fonts: ["Inter"] } } }))).toBe(false);
    expect(hasTheme(profile({ profile: {} }))).toBe(false);
  });

  it("removes one list item and drops a part that becomes empty", () => {
    const p = profile().profile;
    expect(withoutItem(p, { part: "terminology", index: 0 }).terminology).toEqual([{ use: "lesson", avoid: [], citations: [] }]);
    expect(withoutItem(p, { part: "examples", index: 0 }).examples).toBeUndefined();
    const banned = withoutItem(p, { part: "banned", index: 0 });
    expect(banned.banned).toBeUndefined();
    expect(p.banned?.phrases).toHaveLength(1);
  });

  it("applies the voice fields and the blocking switch, and removes a rule when its field is empty", () => {
    const p = profile().profile;
    const next = withEdits(p, { person: "we", formality: "4", sentenceLength: "", blocking: true });
    expect(next.voice).toEqual({ person: "we", formality: 4, citations: [F1] });
    expect(next.banned?.blocking).toBe(true);
    expect(p.voice?.person).toBe("you");
    expect(withEdits(p, { person: "", formality: "", sentenceLength: "", blocking: false }).voice).toBeUndefined();
  });
});
