import { describe, expect, it } from "vitest";
import { statementModel, validEmail } from "../../src/lib/accessibility-statement.ts";

describe("statementModel", () => {
  it("works with no settings at all and names the tenant by its slug", () => {
    expect(statementModel(null, "coffee")).toEqual({ name: "coffee", email: null, enforcement: null, notes: [], updated: null });
    expect(statementModel({ global: {} }, "coffee").name).toBe("coffee");
  });

  it("reads the operator's facts from the global group", () => {
    const model = statementModel(
      { global: { companyName: "The Coffee Atlas", accessibilityEmail: " access@coffee.test ", accessibilityEnforcement: "Rzecznik Praw Obywatelskich", accessibilityNotes: "One.\r\n\r\nTwo.\n\n\n\nThree.", accessibilityUpdated: "2026-10-01" } },
      "coffee"
    );
    expect(model).toEqual({ name: "The Coffee Atlas", email: "access@coffee.test", enforcement: "Rzecznik Praw Obywatelskich", notes: ["One.", "Two.", "Three."], updated: "2026-10-01" });
  });

  it("ignores values of the wrong type and blank strings", () => {
    const model = statementModel({ global: { companyName: 12, accessibilityEmail: ["a@b.co"], accessibilityNotes: "   ", accessibilityEnforcement: "" } }, "oncall");
    expect(model).toMatchObject({ name: "oncall", email: null, enforcement: null, notes: [] });
  });

  it("limits the length of every text", () => {
    expect(statementModel({ global: { accessibilityNotes: "x".repeat(5000) } }, "a").notes[0]).toHaveLength(2000);
  });
});

describe("validEmail", () => {
  it("accepts one plain address", () => {
    expect(validEmail("a.b+c@example.org")).toBe("a.b+c@example.org");
  });
  it("refuses anything that is not a single address, so it never becomes a mailto link", () => {
    for (const bad of ["", null, "no-at.example.org", "a@b", "a b@c.de", "a@b.co, c@d.co", "<a@b.co>", 'a@b.co"onclick=1', "javascript:alert(1)@x.co?subject=1\n"]) {
      expect(validEmail(bad as string | null), String(bad)).toBeNull();
    }
  });
});
