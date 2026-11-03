import { describe, expect, it } from "vitest";
import type { CompareChangeKind, CourseVersion } from "@ulams/sdk";
import { COMPARE_LOCALES, COMPARE_STRINGS } from "../../src/studio/compare-i18n.ts";
import {
  breadcrumb,
  buildTree,
  citationRows,
  compareFormatterFor,
  comparePath,
  elementName,
  elementTitle,
  fieldName,
  fieldValue,
  impactFigures,
  isEmpty,
  isTextField,
  kindsOf,
  kindsPresent,
  latestOfBranch,
  matches,
  NO_FILTERS,
  orderPair,
  quizFor,
  quizHeadline,
  sideLine,
  summaryFigures,
  typesPresent,
  variantPair,
  versionOptionLabel,
  visibleIds,
  walk,
} from "../../src/studio/compare-model.ts";
import { change, changes, payload } from "./compare-fixture.ts";

const en = compareFormatterFor("en");

describe("compare strings", () => {
  const base = COMPARE_STRINGS.en;
  const placeholders = (s: string) => [...s.matchAll(/\{(\w+)\}/g)].map((m) => m[1]).sort();

  it.each(COMPARE_LOCALES.filter((l) => l !== "en"))("%s has every key of English, none empty, with the same placeholders", (locale) => {
    const t = COMPARE_STRINGS[locale];
    expect(Object.keys(t).sort()).toEqual(Object.keys(base).sort());
    for (const group of ["kinds", "types", "fields", "origins", "statuses"] as const) expect(Object.keys(t[group]).sort(), group).toEqual(Object.keys(base[group]).sort());
    for (const [key, value] of Object.entries(t)) {
      if (typeof value === "string") {
        expect(value, key).not.toBe("");
        expect(placeholders(value), key).toEqual(placeholders((base as unknown as Record<string, string>)[key] ?? ""));
      }
    }
  });

  it("names every kind of change with words (never only a symbol)", () => {
    for (const locale of COMPARE_LOCALES) expect(Object.values(COMPARE_STRINGS[locale].kinds).every((w) => /\p{L}/u.test(w))).toBe(true);
  });
});

describe("element names", () => {
  const t = COMPARE_STRINGS.en;

  it("writes a name from the type and the numbers of the API label", () => {
    expect(elementName(t, { type: "module", label: "Module 2" })).toBe("Module 2");
    expect(elementName(t, { type: "lesson", label: "Lesson 2.1" })).toBe("Lesson 2.1");
    expect(elementName(t, { type: "block", label: "Lesson 2.1 › block 3" })).toBe("Block 3");
    expect(elementName(t, { type: "objective", label: "Course outcome 1" })).toBe("Outcome 1");
    expect(elementName(t, { type: "question", label: "Lesson 2.1 › Q2" })).toBe("Question 2");
    expect(elementName(t, { type: "question", label: "Lesson 2.1 › check 1" })).toBe("Self-check 1");
    expect(elementName(t, { type: "question", label: "Final test › Q4" })).toBe("Question 4");
    expect(elementName(t, { type: "option", label: "Lesson 2.1 › Q2 › option B" })).toBe("Option B");
    expect(elementName(t, { type: "quiz", label: "Lesson 2.1 › quiz" })).toBe("Quiz");
    expect(elementName(t, { type: "quiz", label: "Final test" })).toBe("Final test");
    expect(elementName(t, { type: "interaction", label: "Lesson 2.1 › activity" })).toBe("Activity");
    expect(elementName(t, { type: "course", label: "Course" })).toBe("Course");
  });

  it("keeps the label of an element it does not know, so nothing is dropped", () => {
    expect(elementName(t, { type: "hologram", label: "Lesson 2.1 › hologram" })).toBe("Lesson 2.1 › hologram");
    expect(elementName(t, { type: null, label: "mod_1" })).toBe("mod_1");
    expect(elementName(t, { type: "lesson", label: "Intro" })).toBe("Intro");
  });

  it("adds the title of a module or lesson and speaks Polish and Chinese", () => {
    expect(elementTitle(t, { type: "lesson", label: "Lesson 2.1", title: "Grind size" })).toBe("Lesson 2.1: Grind size");
    expect(elementTitle(t, { type: "block", label: "Lesson 2.1 › block 3", title: null })).toBe("Block 3");
    expect(elementName(COMPARE_STRINGS.pl, { type: "lesson", label: "Lesson 2.1" })).toBe("Lekcja 2.1");
    expect(elementName(COMPARE_STRINGS.zh, { type: "question", label: "Lesson 2.1 › Q2" })).toBe("题目 2");
    expect(elementName(COMPARE_STRINGS.pl, { type: "quiz", label: "Final test" })).toBe("Test końcowy");
  });
});

describe("kinds", () => {
  it("lists the change itself, then a move, then a reorder", () => {
    expect(kindsOf({ kind: "added", moved: null, reordered: null })).toEqual(["added"]);
    expect(kindsOf({ kind: "changed", moved: { from: { id: null, label: null }, to: { id: "m", label: "Module 1" } }, reordered: null })).toEqual(["changed", "moved"]);
    expect(kindsOf({ kind: "moved", moved: { from: { id: null, label: null }, to: { id: null, label: null } }, reordered: null })).toEqual(["moved"]);
    expect(kindsOf({ kind: "reordered", moved: null, reordered: { parentId: null, from: 1, to: 2 } })).toEqual(["reordered"]);
    expect(kindsOf({ kind: "changed", moved: { from: { id: null, label: null }, to: { id: null, label: null } }, reordered: { parentId: null, from: 1, to: 2 } })).toEqual(["changed", "moved", "reordered"]);
  });

  it("finds the kinds and types that occur, in a stable order", () => {
    expect(kindsPresent(changes)).toEqual(["added", "removed", "changed", "moved", "reordered"]);
    expect(typesPresent(changes)).toEqual(["lesson", "block", "question", "option"]);
    expect(typesPresent([change({ id: "x", type: "hologram", label: "x", kind: "added" })])).toEqual(["hologram"]);
  });
});

describe("the tree", () => {
  const tree = buildTree(changes);
  const names = (list: typeof tree): string[] => list.map((n) => `${n.label}${n.change ? "" : " (context)"}`);

  it("nests every change under its parents, which show as context when they did not change", () => {
    expect(tree.map((n) => n.id)).toEqual(["mod_1", "mod_2"]);
    const m1 = tree[0]!;
    expect(m1.change).toBeNull();
    expect(names(m1.children)).toEqual(["Lesson 1.1", "Lesson 1.3"]);
    const l1 = m1.children[0]!;
    expect(l1.change?.kind).toBe("changed");
    expect(names(l1.children)).toEqual(["Lesson 1.1 › block 1", "Lesson 1.1 › block 2", "Lesson 1.1 › block 3", "Lesson 1.1 › quiz (context)"]);
    const quiz = l1.children[3]!;
    expect(names(quiz.children)).toEqual(["Lesson 1.1 › Q1 (context)", "Lesson 1.1 › Q2"]);
    expect(names(quiz.children[0]!.children)).toEqual(["Lesson 1.1 › Q1 › option A", "Lesson 1.1 › Q1 › option B"]);
    expect(names(tree[1]!.children)).toEqual(["Lesson 2.1", "Lesson 2.3"]);
  });

  it("gives every node its depth, parent and a breadcrumb in the reader's language", () => {
    const option = tree[0]!.children[0]!.children[3]!.children[0]!.children[0]!;
    expect(option.depth).toBe(5);
    expect(option.parent?.id).toBe("q_1");
    expect(breadcrumb(COMPARE_STRINGS.en, option)).toBe("Module 1 › Lesson 1.1 › Quiz › Question 1");
    expect(breadcrumb(COMPARE_STRINGS.pl, option)).toContain("Lekcja 1.1");
    expect(breadcrumb(COMPARE_STRINGS.en, tree[0]!)).toBe("");
  });

  it("keeps the module title on a context node from the path", () => {
    expect(tree[0]!.title).toBe("Water and brewing");
    expect(tree[1]!.title).toBe("Grinding");
  });

  it("still nests a payload without paths by the ids of the parents", () => {
    const old = changes.map((c) => ({ ...c, path: undefined }));
    const t = buildTree(old);
    const ids: string[] = [];
    walk(t, (n) => ids.push(n.id));
    expect(ids).toContain("blk_1");
    expect(t.find((n) => n.id === "mod_1")?.children.some((c) => c.id === "les_1")).toBe(true);
  });

  it("copes with no changes", () => {
    expect(buildTree([])).toEqual([]);
  });
});

describe("filters", () => {
  const tree = buildTree(changes);
  const filters = (kinds: CompareChangeKind[] = [], types: string[] = []) => ({ kinds: new Set(kinds), types: new Set(types) });

  it("lets everything through when no chip is on", () => {
    expect(changes.every((c) => matches(c, NO_FILTERS))).toBe(true);
    expect(visibleIds(tree, NO_FILTERS).shown).toBe(changes.length);
  });

  it("filters by kind (a moved element that also changed matches both) and by type", () => {
    expect(changes.filter((c) => matches(c, filters(["moved"]))).map((c) => c.id)).toEqual(["les_2"]);
    expect(changes.filter((c) => matches(c, filters(["added", "removed"]))).map((c) => c.id)).toEqual(["les_3", "blk_old"]);
    expect(changes.filter((c) => matches(c, filters([], ["option"]))).map((c) => c.id)).toEqual(["o_a", "o_b"]);
    expect(changes.filter((c) => matches(c, filters(["changed"], ["block"]))).map((c) => c.id)).toEqual(["blk_1", "blk_big"]);
  });

  it("keeps the parents of what matches visible, and nothing else", () => {
    const { ids, shown } = visibleIds(tree, filters([], ["option"]));
    expect(shown).toBe(2);
    expect([...ids].sort()).toEqual(["les_1", "mod_1", "o_a", "o_b", "q_1", "qz_1"]);
    expect(visibleIds(tree, filters(["reordered"], ["option"])).shown).toBe(0);
  });
});

describe("figures and sentences", () => {
  it("lists the counts that are not zero as label and number", () => {
    expect(summaryFigures(en, payload().summary)).toEqual([
      { label: "Elements added", value: "1" },
      { label: "Elements removed", value: "1" },
      { label: "Elements changed", value: "5" },
      { label: "Elements moved to another parent", value: "1" },
      { label: "Elements reordered", value: "1" },
      { label: "Citations added", value: "2" },
      { label: "Citations removed", value: "1" },
      { label: "Citations repointed to a newer source revision", value: "1" },
      { label: "Quiz answer keys changed", value: "1" },
      { label: "Questions reworded", value: "1" },
    ]);
  });

  it("always gives the six learner impact figures, zeros included", () => {
    const figures = impactFigures(en, payload().summary);
    expect(figures.map((f) => f.value)).toEqual(["5", "1", "1", "0", "1", "1"]);
    expect(figures[1]!.label).toBe("Lessons that show an “Updated” notice");
    expect(impactFigures(compareFormatterFor("pl"), payload().summary)[1]!.label).toContain("Zaktualizowano");
  });

  it("tells an empty comparison from a full one", () => {
    expect(isEmpty({ changes: [], quiz: [] })).toBe(true);
    expect(isEmpty(payload())).toBe(false);
  });

  it("names fields and values in the reader's language, booleans and authors included", () => {
    const t = COMPARE_STRINGS.pl;
    expect(fieldName(t, { field: "markdown", label: "Content" })).toBe("Treść");
    expect(fieldName(t, { field: "hologram", label: "Hologram" })).toBe("Hologram");
    expect(fieldValue(COMPARE_STRINGS.en, "correct", true)).toBe("yes");
    expect(fieldValue(COMPARE_STRINGS.en, "correct", false)).toBe("no");
    expect(fieldValue(COMPARE_STRINGS.en, "origin", "ai")).toBe("AI");
    expect(fieldValue(COMPARE_STRINGS.en, "title", null)).toBe("(empty)");
    expect(fieldValue(COMPARE_STRINGS.en, "minutes", 6)).toBe("6");
    expect(isTextField("markdown")).toBe(true);
    expect(isTextField("minutes")).toBe(false);
    expect(isTextField("correct")).toBe(false);
  });

  it("describes both sides of the pair", () => {
    expect(sideLine(en, payload().from)).toMatch(/^Version 2 \(release #1\), .*2026/);
    expect(sideLine(en, { ...payload().from, releaseNumber: null, releasedAt: null, createdAt: null })).toBe("Version 2 (never released), –");
  });
});

describe("citations and quiz", () => {
  const data = payload();
  const lesson = changes.find((c) => c.id === "les_1")!;

  it("pairs every citation with its passage, before and after for a repointed one", () => {
    const rows = citationRows(lesson, data.fragments);
    expect(rows.map((r) => r.kind)).toEqual(["added", "removed", "repointed"]);
    expect(rows[0]!.after?.text).toBe("Water between 92 and 96 degrees.");
    expect(rows[1]!.before?.sourceRevision).toBeNull();
    expect(rows[2]!.before?.sourceRevision?.number).toBe(2);
    expect(rows[2]!.after?.sourceRevision?.number).toBe(3);
    expect(citationRows(changes[3]!, data.fragments)).toEqual([]);
  });

  it("copes with a passage the payload does not carry", () => {
    expect(citationRows(lesson, {})[0]).toMatchObject({ kind: "added", after: null });
  });

  it("finds the quiz change of a question and says what changed", () => {
    expect(quizFor(data.quiz, "q_1")?.answerKeyChanged).toBe(true);
    expect(quizFor(data.quiz, "nope")).toBeNull();
    expect(quizHeadline(COMPARE_STRINGS.en, data.quiz[0]!)).toBe("answer key changed");
    expect(quizHeadline(COMPARE_STRINGS.en, data.quiz[1]!)).toBe("wording changed");
    expect(quizHeadline(COMPARE_STRINGS.en, { ...data.quiz[1]!, wording: true, answerKeyChanged: true, type: { before: "single", after: "multiple" } })).toBe("answer key changed, wording changed, question type changed");
  });
});

describe("pickers and links", () => {
  const f = en;
  const v = (n: number, over: Partial<CourseVersion> = {}): CourseVersion => ({ id: `v${n}`, number: n, kind: "author", origin: "author", status: "approved", reason: null, variantId: null, releaseId: null, releaseNumber: null, createdAt: "2026-10-20T09:00:00+00:00", decidedAt: null, ...over });
  const versions = [
    v(9, { variantId: "x1", releaseId: "rx2", releaseNumber: 2 }),
    v(8, { status: "proposed" }),
    v(7, { releaseId: "r3", releaseNumber: 3 }),
    v(6, { variantId: "x1", status: "approved" }),
    v(5, { status: "rejected" }),
    v(4, { releaseId: "r2", releaseNumber: 2 }),
  ];

  it("writes a picker label with the release, the state and the branch", () => {
    expect(versionOptionLabel(f, versions[1]!, [])).toMatch(/^Version 8 · never released · proposed · .*2026/);
    expect(versionOptionLabel(f, versions[2]!, [])).toMatch(/^Version 7 · release #3 · .*2026/);
    const variants = [{ id: "x1", key: "expert", label: "Expert", level: "expert", role: null, status: "approved" as const, courseId: 4 }];
    expect(versionOptionLabel(f, versions[0]!, variants)).toMatch(/· Expert$/);
    expect(versionOptionLabel(f, versions[2]!, variants)).toMatch(/· base course$/);
  });

  it("takes the latest release of a branch, else its newest approved version", () => {
    expect(latestOfBranch(versions, null)?.id).toBe("v7");
    expect(latestOfBranch(versions, "x1")?.id).toBe("v9");
    expect(latestOfBranch([v(3, { status: "proposed" }), v(2)], null)?.id).toBe("v2");
    expect(latestOfBranch([v(3, { status: "proposed" })], null)).toBeNull();
    expect(latestOfBranch(versions, "other")).toBeNull();
  });

  it("pairs the base with a variant, or nothing when either has no version", () => {
    expect(variantPair(versions, "x1")).toEqual({ from: "v7", to: "v9" });
    expect(variantPair(versions, "other")).toBeNull();
    expect(variantPair([v(1, { variantId: "x1" })], "x1")).toBeNull();
  });

  it("puts the older version first whichever was ticked first", () => {
    expect(orderPair({ number: 5 }, { number: 3 })).toEqual([{ number: 3 }, { number: 5 }]);
    expect(orderPair({ number: 3 }, { number: 5 })).toEqual([{ number: 3 }, { number: 5 }]);
    expect(orderPair({ number: 3, id: "a" }, { number: 3, id: "b" })[0].id).toBe("a");
  });

  it("builds the compare path with both ids and the language", () => {
    expect(comparePath(12, "a", "b")).toBe("/studio/courses/12/compare?from=a&to=b");
    expect(comparePath(12, "a b", "b&c", "pl")).toBe("/studio/courses/12/compare?from=a+b&to=b%26c&lang=pl");
  });
});
