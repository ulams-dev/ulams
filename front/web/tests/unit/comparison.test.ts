import { describe, expect, it } from "vitest";
import { comparisonData, comparisonModel } from "../../src/lib/comparison.ts";

const VALUES = ["Yes", "No", "Partial", "Via plugin", "Paid add-on", "Not documented", "Coming"];
const FREE_TEXT_ROWS = new Set(["licence", "pricing"]);

describe("comparison data (public claims about other products)", () => {
  const rowsOf = (group: string) => comparisonData.rows.filter((r) => !r.groups || r.groups.includes(group));

  it("has every row of its groups for every system", () => {
    for (const system of comparisonData.systems) {
      for (const group of system.groups) {
        for (const row of rowsOf(group)) expect(system.cells[row.key], `${system.name} · ${row.key}`).toBeDefined();
      }
    }
  });

  it("covers the enterprise suites and the enterprise rows", () => {
    const names = comparisonData.systems.filter((s) => s.groups.includes("enterprise") && !s.ours).map((s) => s.name);
    expect(names).toEqual(["Articulate 360", "Docebo", "Cornerstone", "SAP SuccessFactors Learning", "Absorb LMS", "360Learning"]);
    const enterpriseOnly = ["data_residency", "sso", "scim", "authoring_tool", "content_library"];
    for (const key of enterpriseOnly) {
      expect(comparisonData.rows.find((r) => r.key === key)?.groups, key).toEqual(["enterprise"]);
      for (const name of names) expect(comparisonData.systems.find((s) => s.name === name)?.cells[key], `${name} · ${key}`).toBeDefined();
    }
    // keeps every existing row for the enterprise group too
    for (const key of ["self_hosting", "licence", "headless_api", "mcp", "ai_generation", "scorm", "lti13", "pricing"]) {
      expect(rowsOf("enterprise").map((r) => r.key)).toContain(key);
    }
  });

  it("keeps ulams in every group, first, and honest about unbuilt enterprise rows", () => {
    const ulams = comparisonData.systems.find((s) => s.ours)!;
    expect(ulams.groups).toEqual(comparisonData.groups.map((g) => g.key));
    expect(ulams.cells.scim?.value).toBe("Coming");
    expect(ulams.cells.sso?.value).not.toBe("Yes");
    for (const key of ["sso", "scim", "authoring_tool", "content_library", "data_residency"]) {
      expect(ulams.cells[key]?.source, key).toMatch(/^https:\/\/github\.com\/ulams-dev\/ulams\/blob\/main\//);
    }
  });

  it("gives every cell an https source and an ISO checkedAt date", () => {
    for (const system of comparisonData.systems) {
      for (const [row, cell] of Object.entries(system.cells)) {
        expect(cell.source, `${system.name} · ${row}`).toMatch(/^https:\/\/[^\s]+$/);
        expect(cell.checkedAt, `${system.name} · ${row}`).toMatch(/^\d{4}-\d{2}-\d{2}$/);
        expect(Number.isNaN(Date.parse(cell.checkedAt))).toBe(false);
      }
    }
  });

  it("uses only the neutral values outside licence and pricing", () => {
    for (const system of comparisonData.systems) {
      for (const [row, cell] of Object.entries(system.cells)) {
        if (!FREE_TEXT_ROWS.has(row)) expect(VALUES, `${system.name} · ${row}: ${cell.value}`).toContain(cell.value);
      }
    }
  });

  it("uses Coming only for ulams and keeps competitor notes short", () => {
    for (const system of comparisonData.systems.filter((s) => !s.ours)) {
      for (const [row, cell] of Object.entries(system.cells)) {
        expect(cell.value, `${system.name} · ${row}`).not.toBe("Coming");
        expect((cell.note ?? "").length, `${system.name} · ${row}`).toBeLessThanOrEqual(120);
      }
    }
  });

  it("builds one table per group with ulams first and every source listed", () => {
    const model = comparisonModel();
    expect(model.groups.map((g) => g.label)).toEqual(["Open source & creator platforms", "Enterprise suites"]);
    for (const group of model.groups) {
      expect(group.columns[0]).toMatchObject({ label: "ulams", highlight: true });
      expect(group.sections.every((sec) => sec.rows.every((r) => r.cells.length === group.columns.length))).toBe(true);
    }
    expect(model.groups[0]!.columns).toHaveLength(7);
    expect(model.groups[1]!.columns).toHaveLength(7);
    const count = (g: (typeof model.groups)[number]) => g.sections.reduce((n, sec) => n + sec.rows.length, 0);
    expect(count(model.groups[0]!)).toBe(22);
    expect(count(model.groups[1]!)).toBe(27);
    const urls = new Set(comparisonData.systems.flatMap((s) => Object.values(s.cells).map((c) => c.source)));
    expect(new Set(model.sources.map((s) => s.href))).toEqual(urls);
  });

  it("leads both groups with Developer & headless, then AI, content standards and business", () => {
    expect(comparisonData.sections.map((x) => x.label)).toEqual(["Developer & headless", "AI", "Content standards", "Business"]);
    for (const group of comparisonModel().groups) {
      expect(group.sections.map((x) => x.label)).toEqual(["Developer & headless", "AI", "Content standards", "Business"]);
      expect(group.sections[0]!.rows.map((r) => r.label)).toEqual([
        "REST API",
        "Headless course management",
        "Published OpenAPI spec",
        "Typed SDK (TypeScript)",
        "CLI for authors and developers",
        "MCP server",
        "Webhooks / events",
        "Course-as-code / Git sync",
        "Self-hosting",
        "Generative UI",
      ]);
    }
    const sectionKeys = new Set(comparisonData.sections.map((x) => x.key));
    for (const row of comparisonData.rows) expect(sectionKeys.has(row.section), row.key).toBe(true);
  });

  it("links every ulams cell to the repository on origin/main and keeps roadmap items honest", () => {
    const ulams = comparisonData.systems.find((s) => s.ours)!;
    for (const [row, cell] of Object.entries(ulams.cells)) {
      expect(cell.source, row).toMatch(/^https:\/\/github\.com\/ulams-dev\/ulams\/blob\/main\//);
    }
    expect(ulams.cells.mcp?.value).toBe("Yes");
    expect(ulams.cells.webhooks?.value).toBe("Coming");
    expect(ulams.cells.course_as_code?.value).toBe("Coming");
    expect(ulams.cells.cli?.value).toBe("Yes");
    expect(ulams.cells.generative_ui?.value).toBe("Partial");
  });
});
