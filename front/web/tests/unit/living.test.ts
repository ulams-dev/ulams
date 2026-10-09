import { describe, expect, it } from "vitest";
import type { FragmentChangeRow, LivingSource, SourceRevision } from "@ulams/sdk";
import { validate } from "@ulams/ui/schema";
import { builderCatalogue } from "@ulams/ui/builder/catalogue.ts";
import {
  cardProps,
  changeProps,
  cosmeticToggleLabel,
  countsSentence,
  emptyChangesText,
  splitChanges,
  syncState,
  timelineProps,
  uploadMessage,
} from "../../src/studio/living.ts";

const counts = { changed: 3, moved: 1, removed: 1, added: 2, trivial: 0, minor: 4, substantive: 3, total: 7 };

const source = (over: Partial<LivingSource> = {}, conn: Partial<NonNullable<LivingSource["connection"]>> = {}): LivingSource => ({
  id: "01src",
  name: "handbook.md",
  status: "ready",
  kind: "markdown",
  size: 100,
  tokens: 20,
  fragments: 4,
  title: null,
  revisionCount: 2,
  connection: {
    id: "01con",
    connector: "upload",
    schedule: "manual",
    status: "active",
    autoAnalyse: false,
    settings: {},
    config: {},
    syncedRevision: { id: "01r1", number: 1 },
    latestRevision: { id: "01r2", number: 2, status: "ingested" },
    lastCheckedAt: "2026-10-20T09:30:00+00:00",
    nextCheckAt: null,
    lastChangeAt: null,
    failureCount: 0,
    lastError: null,
    secretsSet: [],
    ...conn,
  },
  ...over,
});

const revision = (over: Partial<SourceRevision> = {}): SourceRevision => ({
  id: "01r2",
  sourceId: "01src",
  number: 2,
  origin: "upload",
  originRef: null,
  trigger: "manual",
  triggeredBy: 1,
  status: "ingested",
  fragmentCount: 4,
  tokens: 20,
  title: null,
  name: "handbook.md",
  files: null,
  counts,
  error: null,
  detectedAt: "2026-10-20T09:30:00+00:00",
  synced: false,
  latest: true,
  ...over,
});

describe("sources page helpers", () => {
  it("derives the sync state from the connection", () => {
    expect(syncState(source())).toBe("new_version");
    expect(syncState(source({}, { syncedRevision: { id: "01r2", number: 2 } }))).toBe("up_to_date");
    expect(syncState(source({}, { syncedRevision: null }))).toBe("new_version");
    expect(syncState(source({}, { lastError: "boom" }))).toBe("failed");
    expect(syncState(source({}, { latestRevision: { id: "x", number: 3, status: "failed" } }))).toBe("failed");
    expect(syncState(source({}, { status: "paused" }))).toBe("paused");
    expect(syncState(source({ status: "processing" }))).toBe("processing");
    expect(syncState(source({ status: "failed" }))).toBe("failed");
    expect(syncState(source({ connection: null }))).toBe("up_to_date");
  });

  it("builds props the catalogue accepts", () => {
    const card = cardProps(source());
    expect(validate(builderCatalogue.SourceConnectionCard.props, card).issues).toEqual([]);
    expect(card).toMatchObject({ connector: "upload", state: "new_version", syncedRevision: 1, latestRevision: 2, canCheck: false, canUpload: true });
    expect(validate(builderCatalogue.SourceConnectionCard.props, cardProps(source({ connection: null }))).issues).toEqual([]);

    const timeline = timelineProps("01src", [revision(), revision({ id: "01r1", number: 1, counts: null, error: null, synced: true, latest: false })], "01r2");
    expect(validate(builderCatalogue.RevisionTimeline.props, timeline).issues).toEqual([]);
  });

  it("builds FragmentChange props from an API row, including rows without text", () => {
    const frag = { fragmentId: "frg_aaaaaaaaaaa1", label: "§2.3 Ratios", section: "2.3", headingPath: ["Brewing", "Ratios"], file: null, text: "Use 15 g.", pages: null };
    const row: FragmentChangeRow = {
      id: 7,
      kind: "changed",
      magnitude: "substantive",
      similarity: 0.8,
      signals: ["number"],
      wordDiff: [["=", "Use "], ["-", "15"], ["+", "16"], ["=", " g."]],
      old: frag,
      new: { ...frag, fragmentId: "frg_aaaaaaaaaaa2", text: "Use 16 g." },
    };
    const props = changeProps(row);
    expect(validate(builderCatalogue.FragmentChange.props, props).issues).toEqual([]);
    expect(props.section).toBe("Brewing › Ratios");
    const removed = changeProps({ ...row, id: 8, kind: "removed", wordDiff: null, new: null, signals: [] });
    expect(validate(builderCatalogue.FragmentChange.props, removed).issues).toEqual([]);
    expect(removed).not.toHaveProperty("new");
    expect(removed).not.toHaveProperty("wordDiff");
  });

  it("hides cosmetic changes by default and labels the toggle", () => {
    const base: FragmentChangeRow = { id: 1, kind: "changed", magnitude: "minor", similarity: 1, signals: [], wordDiff: null, old: null, new: null };
    const rows: FragmentChangeRow[] = [
      { ...base, id: 1, magnitude: "trivial" },
      { ...base, id: 2, magnitude: "minor" },
      { ...base, id: 3, magnitude: "trivial" },
    ];
    const { shown, cosmetic } = splitChanges(rows);
    expect(shown.map((r) => r.id)).toEqual([2]);
    expect(cosmetic).toHaveLength(2);
    expect(cosmeticToggleLabel(2, false)).toBe("Show 2 cosmetic changes");
    expect(cosmeticToggleLabel(1, false)).toBe("Show 1 cosmetic change");
    expect(cosmeticToggleLabel(2, true)).toBe("Hide 2 cosmetic changes");
  });

  it("words the upload result", () => {
    expect(countsSentence(counts)).toBe("3 changed, 1 removed, 2 added, 1 moved");
    expect(uploadMessage({ unchanged: false, created: true, revision: revision() })).toBe("Revision 2 added: 3 changed, 1 removed, 2 added, 1 moved. The changes are shown below.");
    expect(uploadMessage({ unchanged: false, created: true, revision: revision({ counts: null }) })).toBe("Revision 2 added. No changes to the content were found.");
    expect(uploadMessage({ unchanged: true, created: false, revision: revision() })).toContain("already the latest revision");
  });

  it("explains the empty state per connector", () => {
    expect(emptyChangesText("upload")).toContain("when you upload them");
    expect(emptyChangesText("git")).toBe("No source changes yet. We check daily.");
  });
});
