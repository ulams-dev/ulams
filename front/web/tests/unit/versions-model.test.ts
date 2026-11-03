import { describe, expect, it } from "vitest";
import type { CourseRelease, CourseVariantRef, CourseVersion, ReleaseSummary } from "@ulams/sdk";
import { fill, VERSIONS_LOCALES, VERSIONS_STRINGS } from "../../src/studio/versions-i18n.ts";
import { changePhrases, pagerFor, releaseRows, triggerName, versionRows, versionsFormatterFor } from "../../src/studio/versions-model.ts";

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
    expect(Object.keys(t.statuses).sort()).toEqual(Object.keys(en.statuses).sort());
    expect(Object.keys(t.kinds).sort()).toEqual(Object.keys(en.kinds).sort());
    for (const [key, value] of Object.entries(t)) {
      if (typeof value === "string") {
        expect(value, key).not.toBe("");
        expect(placeholders(value), key).toEqual(placeholders((en as unknown as Record<string, string>)[key] ?? ""));
      }
    }
  });

  it("has a sentence for every trigger the API can send", () => {
    expect(Object.keys(en.triggers).sort()).toEqual(["author", "chat", "generate", "import", "insights", "rebase", "restore", "style", "update"]);
  });
});

describe("versions model", () => {
  const f = versionsFormatterFor("en");

  it("counts moved elements in the release phrases and keeps the ids a row needs for comparing", () => {
    expect(changePhrases(f, summary({ moved: 2, lessonsMoved: 1 }))).toEqual(["Elements moved: 2"]);
    expect(changePhrases(f, summary({ moved: 0 }))).toEqual(["No content change"]);
    const [row] = releaseRows(f, [release({ previousReleaseId: "01r00000000000000000000000" })]);
    expect(row).toMatchObject({ n: 3, versionId: "01v00000000000000000000003", versionNumber: 7, previousReleaseId: "01r00000000000000000000000" });
  });

  const version = (over: Partial<CourseVersion> = {}): CourseVersion => ({
    id: "01v00000000000000000000009", number: 9, kind: "patch", origin: "ai", status: "proposed", reason: null, variantId: null, releaseId: null, releaseNumber: null, createdAt: "2026-10-22T10:30:00+00:00", decidedAt: null, ...over,
  });
  const expert: CourseVariantRef = { id: "01w00000000000000000000001", key: "expert", label: "Expert", level: "expert", role: null, status: "approved", courseId: 4 };

  it("lists a version with its state and the release that made it live", () => {
    const rows = versionRows(f, [version(), version({ id: "b", number: 8, status: "approved", kind: "content", releaseNumber: 2, releaseId: "r" })], []);
    expect(rows[0]).toMatchObject({ n: 9, version: "9", kind: "Element chat", state: "Proposed, not applied", branch: null, released: "Never released" });
    expect(rows[1]).toMatchObject({ kind: "Generated lessons", state: "Approved", released: "Release 2" });
  });

  it("names the branch only when the course has variants, and keeps an unknown kind or state visible", () => {
    const rows = versionRows(f, [version({ variantId: expert.id }), version({ id: "c", kind: "odd", status: "weird" as CourseVersion["status"] })], [expert]);
    expect(rows[0]!.branch).toBe("Expert");
    expect(rows[1]).toMatchObject({ branch: "Base course", kind: "odd", state: "weird" });
    expect(versionRows(versionsFormatterFor("pl"), [version({ status: "rejected" })], [])[0]!.state).toBe("Odrzucona");
  });

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
