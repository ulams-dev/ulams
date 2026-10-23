import { describe, expect, it } from "vitest";
import { VARIANT_STRINGS, type VariantsLocale } from "../../src/studio/variants-i18n.ts";
import { INSIGHTS_LOCALES } from "../../src/studio/insights-i18n.ts";
import { acceptedInGroupText, countsText, courseName, groupEntries, groupItemProps, isGrouped } from "../../src/studio/variants-model.ts";
import { item } from "./updates-fixtures.ts";

const en = VARIANT_STRINGS.en;
const sibling = (id: string, number: number, variant: { id: string; key?: string; label?: string } | null, over = {}) => ({ id, number, status: "ready" as const, variant, decisions: {}, ...over });

describe("variant strings", () => {
  it.each(INSIGHTS_LOCALES.filter((l) => l !== "en"))("%s has every key of English, and no empty text", (locale: VariantsLocale) => {
    const t = VARIANT_STRINGS[locale];
    expect(Object.keys(t).sort()).toEqual(Object.keys(en).sort());
    expect(Object.keys(t.statuses).sort()).toEqual(Object.keys(en.statuses).sort());
    for (const [key, value] of Object.entries(t)) if (typeof value === "string") expect(value, key).not.toBe("");
    for (const value of Object.values(t.statuses)) expect(value).not.toBe("");
  });

  it.each(INSIGHTS_LOCALES)("%s has a text for every plural category of its language, each with {n}", (locale) => {
    const t = VARIANT_STRINGS[locale];
    const categories = new Intl.PluralRules(t.htmlLang).resolvedOptions().pluralCategories;
    for (const category of categories) expect(t.acceptedElsewhere[category], `${locale}:${category}`).toContain("{n}");
    expect(t.acceptedElsewhere.other).toContain("{n}");
  });

  it.each(INSIGHTS_LOCALES)("%s keeps the placeholders of the English texts", (locale) => {
    const t = VARIANT_STRINGS[locale];
    const names = (text: string) => [...text.matchAll(/\{(\w+)\}/g)].map((m) => m[1]).sort();
    expect(names(t.entryCounts)).toEqual(names(en.entryCounts));
  });
});

describe("courseName", () => {
  it("is the variant's label, falling back to its key and then to a word", () => {
    expect(courseName(en, { variant: { id: "v", key: "expert", label: "Expert" }, proposalGroup: "g" })).toBe("Expert");
    expect(courseName(en, { variant: { id: "v", key: "expert" }, proposalGroup: "g" })).toBe("expert");
    expect(courseName(en, { variant: { id: "v" }, proposalGroup: "g" })).toBe("Variant");
  });

  it("is Base course inside a group and nothing outside one", () => {
    expect(courseName(en, { variant: null, proposalGroup: "g" })).toBe("Base course");
    expect(courseName(en, { variant: null, proposalGroup: null })).toBeNull();
    expect(courseName(en, {})).toBeNull();
    expect(isGrouped({ variant: null, proposalGroup: null })).toBe(false);
    expect(isGrouped({ variant: null, proposalGroup: "g" })).toBe(true);
  });
});

describe("groupEntries", () => {
  const current = { id: "p2", number: 2, status: "ready" as const, variant: { id: "v1", label: "Expert" }, proposalGroup: "g", decisions: { accepted: 1, rejected: 1, pending: 3 } };

  it("is empty without siblings", () => {
    expect(groupEntries(en, current)).toEqual([]);
    expect(groupEntries(en, { ...current, siblings: [] })).toEqual([]);
  });

  it("merges the open proposal with its siblings in order of number", () => {
    const entries = groupEntries(en, { ...current, siblings: [sibling("p3", 3, { id: "v2", label: "Novice" }, { status: "applied", decisions: { accepted: 4 } }), sibling("p1", 1, null)] });
    expect(entries.map((e) => [e.id, e.name, e.isCurrent, e.statusText, e.counts])).toEqual([
      ["p1", "Base course", false, "Ready to review", ""],
      ["p2", "Expert", true, "Ready to review", "2 decided, 3 undecided"],
      ["p3", "Novice", false, "Applied", "4 decided, 0 undecided"],
    ]);
    expect(entries.map((e) => e.open)).toEqual([true, true, false]);
  });

  it("keeps an unknown status readable", () => {
    const [entry] = groupEntries(en, { ...current, siblings: [sibling("p1", 1, null, { status: "mystery" })] });
    expect(entry!.statusText).toBe("mystery");
  });

  it("counts in the reader's language", () => {
    expect(countsText(VARIANT_STRINGS.pl, { accepted: 2, pending: 1 })).toBe("rozstrzygnięte: 2, bez decyzji: 1");
    expect(countsText(VARIANT_STRINGS.zh, { rejected: 1 })).toBe("已决定 1 项，待决定 0 项");
  });
});

describe("groupItemProps", () => {
  it("is empty without strings, so a page outside a group is unchanged", () => {
    expect(groupItemProps(undefined, item({ kind: "update" }), true)).toEqual({});
  });

  it("offers the group action on shared kinds when there are siblings", () => {
    for (const kind of ["update", "no_change", "remove"] as const) expect(groupItemProps(en, item({ kind }), true)).toEqual({ acceptInGroupLabel: "Accept in all versions" });
    for (const kind of ["citation_remap", "manual", "uncovered", "new_lesson"] as const) expect(groupItemProps(en, item({ kind }), true)).toEqual({});
    expect(groupItemProps(en, item({ kind: "update" }), false)).toEqual({});
  });

  it("an item that reuses the base patch gets the action and the note even of any kind or without siblings", () => {
    expect(groupItemProps(en, item({ kind: "update", flags: { reusedFrom: "b1" } }), false)).toEqual({
      acceptInGroupLabel: "Accept in all versions",
      reusedNote: "Same change as in the base course; no extra analysis was needed.",
    });
  });
});

describe("acceptedInGroupText", () => {
  it("counts the other versions with the right plural", () => {
    expect(acceptedInGroupText(en, 1)).toBe("Accepted here and in 1 other version");
    expect(acceptedInGroupText(en, 3)).toBe("Accepted here and in 3 other versions");
    expect(acceptedInGroupText(VARIANT_STRINGS.pl, 1)).toBe("Zaakceptowano tutaj i w 1 innej wersji");
    expect(acceptedInGroupText(VARIANT_STRINGS.pl, 3)).toBe("Zaakceptowano tutaj i w 3 innych wersjach");
    expect(acceptedInGroupText(VARIANT_STRINGS.pl, 5)).toBe("Zaakceptowano tutaj i w 5 innych wersjach");
    expect(acceptedInGroupText(VARIANT_STRINGS.zh, 2)).toBe("已在此处及另外 2 个版本中接受");
  });

  it("says nothing else was waiting for zero", () => {
    expect(acceptedInGroupText(en, 0)).toContain("No other version had the same change");
  });
});
