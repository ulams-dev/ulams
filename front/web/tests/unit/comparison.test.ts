import { describe, expect, it } from "vitest";
import { comparisonData, comparisonModel } from "../../src/lib/comparison.ts";

const VALUES = ["Yes", "No", "Partial", "Via plugin", "Paid add-on", "Not documented", "Coming"];
const FREE_TEXT_ROWS = new Set(["licence", "pricing"]);

describe("comparison data (public claims about other products)", () => {
  it("has every row for every system", () => {
    for (const system of comparisonData.systems) {
      for (const row of comparisonData.rows) expect(system.cells[row.key], `${system.name} · ${row.key}`).toBeDefined();
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

  it("builds table props with ulams first and every source listed", () => {
    const model = comparisonModel();
    expect(model.columns[0]).toMatchObject({ label: "ulams", highlight: true });
    expect(model.rows.every((r) => r.cells.length === model.columns.length)).toBe(true);
    const urls = new Set(comparisonData.systems.flatMap((s) => Object.values(s.cells).map((c) => c.source)));
    expect(new Set(model.sources.map((s) => s.href))).toEqual(urls);
  });
});
