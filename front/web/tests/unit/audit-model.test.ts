import { describe, expect, it } from "vitest";
import type { AuditEntry } from "@ulams/sdk";
import { validate } from "@ulams/ui/schema";
import { builderCatalogue } from "@ulams/ui/builder/catalogue.ts";
import {
  actionLabel,
  actorName,
  chainText,
  dataParts,
  detailRows,
  filtersFrom,
  pageCount,
  rangeError,
  rangeText,
  summaryOf,
  tableProps,
} from "../../src/studio/audit-model.ts";

const entry = (over: Partial<AuditEntry> = {}): AuditEntry => ({
  id: 12,
  at: "2026-10-20T10:15:00+00:00",
  action: "proposal.applied",
  actor: { type: "user", id: 3, name: "Ada Lovelace", onBehalfOf: null },
  subject: { type: "proposal", id: "01p" },
  sourceId: "01src",
  revisionId: "01rev2",
  originRef: "upload",
  versionFrom: 3,
  versionTo: 4,
  aiCallIds: [41, 42],
  data: { reason: "Section 3.2 changed the ratio.", counts: { accepted: 5, rejected: 1 } },
  hash: "9f2c1d",
  prevHash: "03ab44",
  ...over,
});

describe("audit words", () => {
  it("names known actions in plain words and spells out unknown ones", () => {
    expect(actionLabel("proposal.applied")).toBe("Update applied");
    expect(actionLabel("item.rejected")).toBe("Change rejected");
    expect(actionLabel("connection.secret_rotated")).toBe("Access secret replaced");
    expect(actionLabel("agent.did_something")).toBe("Agent did something");
    expect(actionLabel("")).toBe("Unknown action");
  });

  it("names who acted, whatever the API knows about them", () => {
    expect(actorName(entry())).toBe("Ada Lovelace");
    expect(actorName(entry({ actor: { type: "user", id: 9, name: null, onBehalfOf: null } }))).toBe("User #9");
    expect(actorName(entry({ actor: { type: "system", id: null, name: null, onBehalfOf: null } }))).toBe("System");
    expect(actorName(entry({ actor: { type: "agent", id: null, name: null, onBehalfOf: 3 } }))).toBe("Agent");
  });

  it("summarises with the reason, else with the recorded facts, else with the subject", () => {
    expect(summaryOf(entry())).toBe("Section 3.2 changed the ratio.");
    expect(summaryOf(entry({ data: { learners: 12, notices: { topic_updated: 12, question_reattempt: 2 } } }))).toBe(
      "learners: 12; notices: topic updated 12, question reattempt 2"
    );
    expect(summaryOf(entry({ data: {} }))).toBe("proposal 01p");
    expect(summaryOf(entry({ data: {}, subject: { type: null, id: null } }))).toBe("");
    expect(dataParts({ ids: [1, 2, 3], empty: "", gone: null })).toEqual(["ids: 3"]);
  });

  it("lists the full record, leaving out what is empty", () => {
    const rows = detailRows(entry());
    const get = (label: string) => rows.find((r) => r.label === label)?.value;
    expect(get("Entry")).toBe("#12");
    expect(get("Time")).toBe("2026-10-20 10:15:00 UTC");
    expect(get("Who")).toBe("Ada Lovelace (person)");
    expect(get("Source revision")).toBe("01rev2");
    expect(get("Course versions")).toBe("v3 to v4");
    expect(get("AI calls")).toBe("2 (41, 42)");
    expect(get("Hash")).toBe("9f2c1d");
    expect(get("Previous hash")).toBe("03ab44");
    const bare = detailRows(entry({ revisionId: null, sourceId: null, originRef: null, versionFrom: null, versionTo: null, aiCallIds: [], data: {}, prevHash: null, actor: { type: "agent", id: null, name: null, onBehalfOf: 3 } }));
    const labels = bare.map((r) => r.label);
    expect(labels).not.toContain("Source revision");
    expect(labels).not.toContain("Course versions");
    expect(bare.find((r) => r.label === "AI calls")?.value).toBe("None");
    expect(bare.find((r) => r.label === "On behalf of")?.value).toBe("User #3");
    expect(bare.find((r) => r.label === "Previous hash")?.value).toBe("none (first entry)");
  });
});

describe("audit filters and paging", () => {
  it("sends whole days and leaves unset filters out", () => {
    expect(filtersFrom({ group: "proposal.", actorType: "", from: "2026-10-01", to: "2026-10-09" })).toEqual({
      action: "proposal.",
      actorType: undefined,
      from: "2026-10-01 00:00:00",
      to: "2026-10-09 23:59:59",
    });
    expect(Object.values(filtersFrom({ group: "", actorType: "", from: "", to: "" })).every((v) => v === undefined)).toBe(true);
    expect(filtersFrom({ group: "", actorType: "", from: "yesterday", to: "" }).from).toBeUndefined();
  });

  it("refuses an end date before the start date", () => {
    expect(rangeError({ group: "", actorType: "", from: "2026-10-09", to: "2026-10-01" })).toBe("The end date is before the start date.");
    expect(rangeError({ group: "", actorType: "", from: "2026-10-01", to: "2026-10-01" })).toBeNull();
    expect(rangeError({ group: "", actorType: "", from: "", to: "2026-10-01" })).toBeNull();
  });

  it("counts pages and words the range", () => {
    expect(pageCount(0, 25)).toBe(1);
    expect(pageCount(51, 25)).toBe(3);
    expect(rangeText(2, 25, 51)).toBe("Showing 26 to 50 of 51 entries.");
    expect(rangeText(3, 25, 51)).toBe("Showing 51 to 51 of 51 entries.");
    expect(rangeText(1, 25, 1)).toBe("Showing 1 to 1 of 1 entry.");
    expect(rangeText(1, 25, 0)).toBe("No entries.");
  });
});

describe("chain status", () => {
  it("says the chain is verified, with the count", () => {
    expect(chainText({ ok: true, checked: 120, brokenId: null, reason: null })).toBe("Chain verified: 120 entries checked, none has been changed.");
    expect(chainText({ ok: true, checked: 1, brokenId: null, reason: null })).toContain("1 entry checked");
  });

  it("names the first broken entry and why", () => {
    expect(chainText({ ok: false, checked: 7, brokenId: 7, reason: "hash does not match the row" })).toBe(
      "Chain broken at entry 7: hash does not match the row. Entries after it cannot be trusted until this is investigated."
    );
    expect(chainText({ ok: false, checked: 0, brokenId: null, reason: null })).toContain("Chain broken at an entry.");
  });
});

describe("table props", () => {
  it("are accepted by the AuditTable schema", () => {
    const props = tableProps([entry(), entry({ id: 11, action: "item.accepted", data: {}, actor: { type: "system", id: null, name: null, onBehalfOf: null } })], "Audit trail, page 1 of 1");
    const result = validate(builderCatalogue.AuditTable.props, props);
    expect(result.issues).toEqual([]);
    expect((props.entries as Array<{ actionLabel: string }>)[0]?.actionLabel).toBe("Update applied");
  });

  it("stay within the schema limits for a long record", () => {
    const long = "x".repeat(5000);
    const props = tableProps([entry({ data: { reason: long, note: long } })], "c");
    expect(validate(builderCatalogue.AuditTable.props, props).issues).toEqual([]);
  });
});
