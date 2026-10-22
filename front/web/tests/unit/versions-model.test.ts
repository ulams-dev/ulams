import { describe, expect, it } from "vitest";
import type { CourseRelease, ReleaseSummary } from "@ulams/sdk";
import { fill, VERSIONS_LOCALES, VERSIONS_STRINGS } from "../../src/studio/versions-i18n.ts";
import { changePhrases, pagerFor, releaseRows, triggerName, versionsFormatterFor } from "../../src/studio/versions-model.ts";

const none = { added: 0, changed: 0, removed: 0 };
const summary = (over: Partial<ReleaseSummary> = {}): ReleaseSummary => ({ first: false, counts: none, lessons: none, questions: none, citationsChanged: 0, answerKeysChanged: 0, ...over });
const release = (over: Partial<CourseRelease> = {}): CourseRelease => ({
  id: "01r00000000000000000000001",
  number: 3,
  trigger: "update",
  versionId: "01v00000000000000000000003",
  versionNumber: 7,
  reason: null,
  previousReleaseId: null,
  courseId: 12,
  approvedBy: { id: 5, name: "Jane Doe" },
  sourceRevisions: null,
  summary: summary(),
  releasedAt: "2026-10-22T10:30:00+00:00",
  ...over,
});

describe("versions strings", () => {
  const en = VERSIONS_STRINGS.en;
  const placeholders = (s: string) => [...s.matchAll(/\{(\w+)\}/g)].map((m) => m[1]).sort();

  it.each(VERSIONS_LOCALES.filter((l) => l !== "en"))("%s has every key of English, none empty, with the same placeholders and triggers", (locale) => {
    const t = VERSIONS_STRINGS[locale];
    expect(Object.keys(t).sort()).toEqual(Object.keys(en).sort());
    expect(Object.keys(t.triggers).sort()).toEqual(Object.keys(en.triggers).sort());
    for (const [key, value] of Object.entries(t)) {
      if (typeof value === "string") {
        expect(value, key).not.toBe("");
        expect(placeholders(value), key).toEqual(placeholders((en as unknown as Record<string, string>)[key] ?? ""));
      }
    }
  });

  it("has a sentence for every trigger the API can send", () => {
    expect(Object.keys(en.triggers).sort()).toEqual(["author", "chat", "generate", "import", "insights", "rebase", "restore", "update"]);
  });
});

describe("versions model", () => {
  const f = versionsFormatterFor("en");

  it("names a trigger, and keeps an unknown one visible", () => {
    expect(triggerName(f.t, "chat")).toBe("Changed in the element chat");
    expect(triggerName(f.t, "teleport")).toBe("teleport");
  });

  it("says what changed in counts, skipping zeros", () => {
    expect(changePhrases(f, summary())).toEqual(["No content change"]);
    expect(changePhrases(f, summary({ counts: { added: 3, changed: 3, removed: 1 }, lessons: { added: 2, changed: 1, removed: 1 }, answerKeysChanged: 1, citationsChanged: 2 }))).toEqual([
      "Lessons added: 2",
      "Lessons removed: 1",
      "Lessons changed: 1",
      "Other elements added, changed or removed: 3",
      "Elements with changed citations: 2",
      "Quiz answer keys changed: 1",
    ]);
  });

  it("describes the first release by its lessons", () => {
    expect(changePhrases(f, summary({ first: true, counts: { added: 30, changed: 0, removed: 0 }, lessons: { added: 1234, changed: 0, removed: 0 } }))).toEqual(["First release (lessons: 1,234)"]);
  });

  it("builds a row per release with the approver, version and date", () => {
    const [row] = releaseRows(f, [release()]);
    expect(row).toMatchObject({ number: "#3", startedBy: "Source update accepted", approvedBy: "Jane Doe", version: "7", changes: ["No content change"] });
    expect(row!.released).toMatch(/2026/);
  });

  it("copes with a missing approver, version and date", () => {
    const [row] = releaseRows(f, [release({ approvedBy: null, versionNumber: null, releasedAt: "not a date" })]);
    expect(row).toMatchObject({ approvedBy: "Not recorded", version: "–", released: "–" });
  });

  it("speaks Polish and Chinese", () => {
    expect(triggerName(versionsFormatterFor("pl").t, "author")).toBe("Edycja autora");
    expect(changePhrases(versionsFormatterFor("zh"), summary({ counts: { added: 0, changed: 1, removed: 0 }, lessons: { added: 0, changed: 1, removed: 0 } }))).toEqual(["修改课时：1"]);
    expect(fill(VERSIONS_STRINGS.pl.pageStatus, { page: 1, last: 2, total: 30 })).toBe("Strona 1 z 2 (wydania: 30)");
  });

  it("works out the pager", () => {
    expect(pagerFor({ page: 1, perPage: 25, total: 0 })).toEqual({ page: 1, last: 1, total: 0, hasNewer: false, hasOlder: false });
    expect(pagerFor({ page: 1, perPage: 25, total: 26 })).toMatchObject({ last: 2, hasNewer: false, hasOlder: true });
    expect(pagerFor({ page: 2, perPage: 25, total: 26 })).toMatchObject({ last: 2, hasNewer: true, hasOlder: false });
  });
});
