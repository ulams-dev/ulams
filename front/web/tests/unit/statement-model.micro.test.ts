import { describe, it, expect } from "vitest";
import { statementModel } from "../../src/lib/accessibility-statement.ts";

describe("statementModel", () => {
  it("returns name equal to slug and nulls and [] notes with null settings", () => {
    const result = statementModel(null, "test-slug");
    expect(result).toEqual({
      name: "test-slug",
      email: null,
      enforcement: null,
      notes: [],
      updated: null,
    });
  });

  it("uses companyName if provided", () => {
    const settings = { global: { companyName: "Test Company" } };
    const result = statementModel(settings, "test-slug");
    expect(result.name).toBe("Test Company");
  });

  it("invalid email becomes null", () => {
    const settings = { global: { accessibilityEmail: "invalid-email" } };
    const result = statementModel(settings, "test-slug");
    expect(result.email).toBeNull();
  });

  it("notes split on blank lines into paragraphs", () => {
    const settings = { global: { accessibilityNotes: "First paragraph\n\nSecond paragraph" } };
    const result = statementModel(settings, "test-slug");
    expect(result.notes).toEqual(["First paragraph", "Second paragraph"]);
  });

  it("CRLF normalised", () => {
    const settings = { global: { accessibilityNotes: "First paragraph\r\n\r\nSecond paragraph" } };
    const result = statementModel(settings, "test-slug");
    expect(result.notes).toEqual(["First paragraph", "Second paragraph"]);
  });

  it("non-string values ignored", () => {
    const settings = { global: { accessibilityNotes: 12345 } };
    const result = statementModel(settings, "test-slug");
    expect(result.notes).toEqual([]);
  });

  it("long notes truncated to 2000 characters", () => {
    const longNotes = "a".repeat(2100);
    const settings = { global: { accessibilityNotes: longNotes } };
    const result = statementModel(settings, "test-slug");
    expect(result.notes[0]!.length).toBe(2000);
  });

  it("enforcement replaces the default sentence", () => {
    const settings = { global: { accessibilityEnforcement: "Custom enforcement" } };
    const result = statementModel(settings, "test-slug");
    expect(result.enforcement).toBe("Custom enforcement");
  });

  it("updated date is correctly handled", () => {
    const settings = { global: { accessibilityUpdated: "2023-10-01" } };
    const result = statementModel(settings, "test-slug");
    expect(result.updated).toBe("2023-10-01");
  });
});
