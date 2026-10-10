import { describe, expect, it } from "vitest";
import { estimateText, usd, valueChoices } from "../../src/studio/global-edit.ts";

describe("whole-course edit form", () => {
  it("offers languages, levels and tones, and none for a free instruction", () => {
    expect(valueChoices("translate").map(([v]) => v)).toContain("pl");
    expect(valueChoices("change_level").map(([v]) => v)).toEqual(["beginner", "intermediate", "advanced"]);
    expect(valueChoices("change_tone")).toHaveLength(4);
    expect(valueChoices("custom")).toEqual([]);
  });
  it("words the estimate", () => {
    expect(usd(840_000)).toBe("$0.84");
    expect(estimateText(12, 840_000)).toBe("12 model calls, about $0.84");
    expect(estimateText(1, 10_000)).toBe("1 model call, about $0.01");
  });
});
