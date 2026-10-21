import { describe, expect, it } from "vitest";
import { labelGeneratedCourses, showAiAssistedLabel } from "../../src/lib/ai-label.ts";

describe("AI-assisted lesson label", () => {
  it("is on unless the tenant switched the setting off", () => {
    expect(labelGeneratedCourses(null)).toBe(true);
    expect(labelGeneratedCourses({})).toBe(true);
    expect(labelGeneratedCourses({ ai: {} })).toBe(true);
    expect(labelGeneratedCourses({ ai: { label_generated_courses: true } })).toBe(true);
    expect(labelGeneratedCourses({ ai: { label_generated_courses: "1" } })).toBe(true);
    expect(labelGeneratedCourses({ ai: { label_generated_courses: false } })).toBe(false);
    expect(labelGeneratedCourses({ ai: { label_generated_courses: 0 } })).toBe(false);
    expect(labelGeneratedCourses({ ai: { label_generated_courses: "false" } })).toBe(false);
    expect(labelGeneratedCourses({ ai: { label_generated_courses: "0" } })).toBe(false);
  });

  it("shows only for topics the API marks as built with AI assistance", () => {
    expect(showAiAssistedLabel({ ai_assisted: true }, null)).toBe(true);
    expect(showAiAssistedLabel({ ai_assisted: false }, null)).toBe(false);
    expect(showAiAssistedLabel({}, null)).toBe(false);
    expect(showAiAssistedLabel({ ai_assisted: true }, { ai: { label_generated_courses: false } })).toBe(false);
  });
});
