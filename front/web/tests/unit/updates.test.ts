import { describe, expect, it } from "vitest";
import { detail, item, question } from "./updates-fixtures.ts";
import { validate } from "@ulams/ui/schema";
import { builderCatalogue } from "@ulams/ui/builder/catalogue.ts";
import {
  analyseLabel,
  applyLabel,
  applyModel,
  applySummary,
  canDecide,
  conflictsText,
  fieldsFor,
  groupProgress,
  impactProps,
  itemProps,
  progressOf,
  progressText,
  rejectAllText,
  stepRows,
  usdText,
  withItem,
} from "../../src/studio/updates.ts";


describe("fields of an element", () => {
  it("lists the text that differs for a block", () => {
    expect(fieldsFor(item())).toEqual([{ path: "markdown", label: "Text", before: "Use 15 g of coffee.", after: "Use 16 g of coffee." }]);
  });

  it("pairs question options by id and by position, and marks the correct one", () => {
    const fields = fieldsFor(question());
    expect(fields).toEqual([
      { path: "options.0", label: "Option A (correct)", before: "1:15", after: "1:16" },
      { path: "options.2", label: "Option C", after: "1:18" },
    ]);
  });

  it("notes a change of the correct option and a removed option", () => {
    const base = question();
    const moved = item({
      ...base,
      before: { ...base.before!, options: [{ id: "o1", text: "A", correct: true }, { id: "o2", text: "B", correct: false }] },
      after: { ...base.after!, options: [{ id: "o1", text: "A", correct: false }] },
    });
    const labels = fieldsFor(moved).map((f) => f.label);
    expect(labels).toContain("Option A (no longer correct)");
    expect(labels).toContain("Option B removed");
  });

  it("shows what goes away on a removal and nothing for the other kinds", () => {
    expect(fieldsFor(item({ kind: "remove", after: null }))).toEqual([{ path: "markdown", label: "Text", before: "Use 15 g of coffee." }]);
    for (const kind of ["no_change", "manual", "citation_remap", "uncovered"] as const) expect(fieldsFor(item({ kind }))).toEqual([]);
    expect(fieldsFor(item({ after: null }))).toEqual([]);
  });

  it("handles objectives, lessons and the course title", () => {
    const objective = item({ type: "objective", before: { id: "o", text: "Old" }, after: { id: "o", text: "New" } });
    expect(fieldsFor(objective)[0]).toMatchObject({ label: "Learning objective", before: "Old", after: "New" });
    expect(fieldsFor(item({ type: "lesson", before: { id: "l", title: "A" }, after: { id: "l", title: "B" } }))[0]?.label).toBe("Lesson title");
    expect(fieldsFor(item({ type: "course", before: { id: "c", title: "A" }, after: { id: "c", title: "B" } }))[0]?.label).toBe("Course title");
  });
});

describe("catalogue props", () => {
  const ctx = { sessionId: "s1", canDecide: true, canRegenerate: true };

  it("builds UpdateItem props that validate against the schema", () => {
    for (const row of [item(), question(), item({ kind: "uncovered", type: "section", before: null, after: null }), item({ kind: "manual", after: null }), item({ kind: "citation_remap" })]) {
      const props = itemProps(row, ctx);
      expect(validate(builderCatalogue.UpdateItem.props, props).issues, JSON.stringify(props)).toEqual([]);
    }
    const props = itemProps(question(), ctx);
    expect(props).toMatchObject({ answerChanged: true, answerCheck: true, flags: ["Possibly unsupported: filtered water"], signals: ["number"], sources: "Based on §3.2 Ratios", href: "/studio/s/s1/workspace", maxRegenerations: 3 });
  });

  it("builds ImpactSummary props and leaves learner impact to the extension point", () => {
    const props = impactProps(detail([item()]));
    expect(validate(builderCatalogue.ImpactSummary.props, props).issues).toEqual([]);
    expect(props).toMatchObject({ elements: 2, answerChecks: 1, remaps: 1, lessons: 1, estimatedCostMicroUsd: 420000, costMicroUsd: 130000 });
    expect(props).not.toHaveProperty("learnerImpact");
  });
});

describe("decisions and apply", () => {
  const rows = [item(), question(), item({ id: "i3", kind: "citation_remap", status: "accepted", elementId: "b2" }), item({ id: "i4", kind: "no_change", status: "pending", elementId: "b3" }), item({ id: "i5", status: "conflict", elementId: "b4" })];

  it("counts what will be applied, what is undecided and what conflicts", () => {
    const model = applyModel(detail(rows));
    expect(model).toEqual({ applicable: 1, undecided: 3, conflicts: 1, rejected: 0, canApply: true });
    expect(applySummary(model)).toBe("1 accepted · 3 undecided · 0 rejected · 1 in conflict");
    expect(applyModel(detail([item()])).canApply).toBe(false);
    expect(applyModel(detail(rows, { status: "applying" })).canApply).toBe(false);
  });

  it("words the apply button", () => {
    expect(applyLabel(0)).toBe("Apply accepted changes");
    expect(applyLabel(1)).toBe("Apply 1 accepted change");
    expect(applyLabel(4)).toBe("Apply 4 accepted changes");
  });

  it("counts decided items per group and swaps an item with fresh decision counts", () => {
    const d = detail(rows);
    expect(groupProgress(d.groups[0]!)).toBe("0 of 4 decided");
    const next = withItem(d, { ...rows[0]!, status: "accepted" });
    expect(next.items[0]!.status).toBe("accepted");
    expect(next.groups[0]!.items[0]!.status).toBe("accepted");
    expect(next.decisions).toMatchObject({ accepted: 2, pending: 2, conflict: 1 });
    expect(groupProgress(next.groups[0]!)).toBe("1 of 4 decided");
  });

  it("opens decisions only when they mean something", () => {
    expect(canDecide("ready", true)).toBe(true);
    expect(canDecide("awaiting_analysis", true)).toBe(false);
    expect(canDecide("awaiting_analysis", false)).toBe(true);
    expect(canDecide("budget_blocked", true)).toBe(true);
    expect(canDecide("analysing", true)).toBe(false);
    expect(canDecide("applied", true)).toBe(false);
  });
});

describe("analysis state", () => {
  it("reads progress from the steps and prefers the live event", () => {
    const d = detail([item()], {
      status: "analysing",
      steps: [{ id: "s1", groupKey: "lesson:l1", status: "done", error: null }, { id: "s2", groupKey: "lesson:l2", status: "failed", error: "Timed out" }, { id: "s3", groupKey: "course", status: "running", error: null }],
    });
    expect(stepRows(d).map((r) => [r.label, r.status])).toEqual([["Lesson 1.1: Ratios", "done"], ["Lesson group 2", "failed"], ["Course", "running"]]);
    expect(progressText(progressOf(d))).toBe("1 of 3 lessons analysed · 1 failed · $0.13 so far");
    expect(progressText(progressOf(d, { total: 8, done: 5, failed: 0, costMicroUsd: 90000 }))).toBe("5 of 8 lessons analysed · $0.09 so far");
    expect(progressText({ total: 0, done: 0, failed: 0 })).toBe("Starting the analysis…");
  });

  it("knows failed groups that have no step to retry", () => {
    const d = detail([item()], { counts: { items: 1, elements: 1, remaps: 0, uncovered: 0, major: 0, answerChecks: 0, groups: 1, failedGroups: ["lesson:l1"] } });
    expect(stepRows(d)).toEqual([{ id: null, groupKey: "lesson:l1", label: "Lesson 1.1: Ratios", status: "failed", error: null }]);
  });
});

describe("texts", () => {
  it("formats cost and button labels", () => {
    expect(usdText(420000)).toBe("$0.42");
    expect(usdText(1200)).toBe("less than $0.01");
    expect(usdText(0)).toBe("$0.00");
    expect(analyseLabel(420000)).toBe("Analyse (about $0.42)");
    expect(analyseLabel(null)).toBe("Analyse");
  });

  it("explains reject all and conflicts", () => {
    expect(rejectAllText(2)).toContain("marks source revision 2 as reviewed");
    expect(conflictsText(1)).toBe("1 element was edited after the analysis and is marked as conflict. Ask for a new version of it, or reject it, then apply again.");
    expect(conflictsText(3)).toContain("3 elements were edited");
  });
});
