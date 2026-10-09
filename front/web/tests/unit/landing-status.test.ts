import { describe, expect, it } from "vitest";
import type { UiNode } from "@ulams/ui/render-core";
import { validateDocument } from "@ulams/ui/render-core";
import { landingDocs } from "../../src/lib/docs.ts";
import { applyComparisonStatus, applyLandingStatus, applyWorkflowStatus, parseLandingStatus } from "../../src/lib/landing-status.ts";
import { comparisonData, comparisonModel } from "../../src/lib/comparison.ts";
import { workflowsData, workflowsModel } from "../../src/lib/workflows.ts";

const doc = landingDocs.platform!;
const json = (value: unknown) => JSON.stringify(value);

describe("parseLandingStatus", () => {
  it("defaults to final and only 'actual' switches the honest labels back on", () => {
    expect(parseLandingStatus(undefined)).toBe("final");
    expect(parseLandingStatus("")).toBe("final");
    expect(parseLandingStatus("nonsense")).toBe("final");
    expect(parseLandingStatus(" Actual ")).toBe("actual");
  });
});

describe("platform landing in actual mode", () => {
  const actual = applyLandingStatus(doc, "actual");
  it("keeps every roadmap label", () => {
    expect(json(actual)).toContain('"status":"coming"');
        expect(json(actual)).not.toContain('"final"');
  });
});

describe("platform landing in final mode", () => {
  const final = applyLandingStatus(doc, "final");
  it("has no Coming or Preview status and no roadmap wording", () => {
    const text = json(final);
    expect(text).not.toMatch(/"status":"(coming|preview)"/);
    expect(text).not.toMatch(/roadmap/i);
    expect(text).not.toContain('"final"');
  });
  it("does not change the source document", () => {
    expect(json(doc)).toContain('"status":"coming"');
    expect(json(doc)).toContain('"final"');
  });
  it("applies the text overrides", () => {
    expect(json(final)).toContain("The killer feature: when your docs change");
    expect(json(final)).toContain("LTI 1.3 works too");
  });
  it("is valid against the catalogue in both modes", () => {
    for (const mode of ["final", "actual"] as const) {
      const demo = { title: "Demo", theme: "coffee", facts: [], primary: { label: "Open", href: "/learn/1" }, secondary: { label: "Admin", href: "/admin" } };
      const data = { demos: [demo], comparison: comparisonModel(applyComparisonStatus(comparisonData, mode)), workflows: workflowsModel(mode) };
      const problems = validateDocument(applyLandingStatus(doc, mode) as UiNode, data);
      expect(problems, mode).toEqual([]);
    }
  });
});

describe("comparison cells", () => {
  const ours = (mode: "final" | "actual") => applyComparisonStatus(comparisonData, mode).systems.find((s) => s.ours)!;
  it("actual: unchanged", () => {
    expect(applyComparisonStatus(comparisonData, "actual")).toBe(comparisonData);
    expect(ours("actual").cells.scim?.value).toBe("Coming");
  });
  it("final: ulams Coming and Partial read Yes, roadmap notes are dropped, other cells stay", () => {
    const cells = ours("final").cells;
    expect(cells.scim?.value).toBe("Yes");
    expect(cells.source_sync?.value).toBe("Yes");
    expect(cells.sso?.value).toBe("Yes");
    expect(cells.scim?.note).toBeUndefined();
    expect(cells.content_library?.value).toBe("No");
    expect(cells.licence?.value).toBe(ours("actual").cells.licence?.value);
    for (const cell of Object.values(cells)) expect(cell.value).not.toBe("Coming");
  });
  it("never changes a competitor cell", () => {
    const before = comparisonData.systems.filter((s) => !s.ours);
    const after = applyComparisonStatus(comparisonData, "final").systems.filter((s) => !s.ours);
    expect(after).toEqual(before);
  });
});

describe("workflow tabs", () => {
  it("every tab that is not available carries a status badge, in the data", () => {
    for (const tab of workflowsData.tabs) {
      if (tab.status !== "available") expect(["preview", "coming"], tab.key).toContain(tab.status);
    }
    const planned = workflowsData.tabs.filter((t) => ["claude-code", "claude-mcp", "cli"].includes(t.key));
    expect(planned).toHaveLength(3);
    for (const tab of planned) expect(tab.status).toBe("coming");
    for (const key of ["api", "studio"]) expect(workflowsData.tabs.find((t) => t.key === key)?.status).toBe("available");
  });
  it("actual keeps badges, notes and the footnote; final removes them", () => {
    const actual = workflowsModel("actual");
    expect(actual.footnote).toMatch(/planned interface/);
    expect(actual.tabs.filter((t) => t.status).length).toBe(workflowsData.tabs.length);
    const final = applyWorkflowStatus(workflowsData, "final");
    expect(final.footnote).toBeUndefined();
    for (const tab of final.tabs) {
      expect(tab.status).toBeUndefined();
      expect(tab.note).toBeUndefined();
    }
  });
});
