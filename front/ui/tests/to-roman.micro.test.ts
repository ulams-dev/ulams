import { describe, it, expect } from "vitest";
import { toRoman } from "../src/lib/format.ts";

describe("toRoman", () => {
  it("converts 1 to I", () => {
    expect(toRoman(1)).toBe("I");
  });

  it("converts 4 to IV", () => {
    expect(toRoman(4)).toBe("IV");
  });

  it("converts 9 to IX", () => {
    expect(toRoman(9)).toBe("IX");
  });

  it("converts 14 to XIV", () => {
    expect(toRoman(14)).toBe("XIV");
  });

  it("converts 40 to XL", () => {
    expect(toRoman(40)).toBe("XL");
  });

  it("converts 49 to XLIX", () => {
    expect(toRoman(49)).toBe("XLIX");
  });

  it("returns empty string for 0", () => {
    expect(toRoman(0)).toBe("");
  });

  it("returns empty string for negative numbers", () => {
    expect(toRoman(-5)).toBe("");
  });

  it("floors 3.7 to III", () => {
    expect(toRoman(3.7)).toBe("III");
  });
});
