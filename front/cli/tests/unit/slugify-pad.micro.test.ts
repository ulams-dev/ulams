import { describe, it, expect } from "vitest";
import { slugify, pad, KEY_RE } from "../../src/course/ids.ts";

describe("slugify", () => {
  it("converts spaces to dashes", () => {
    expect(slugify("Hello World")).toBe("hello-world");
  });

  it("removes accents", () => {
    expect(slugify("Café Au Lait")).toBe("cafe-au-lait");
  });

  it("trims leading and trailing dashes", () => {
    expect(slugify(" -Hello World- ")).toBe("hello-world");
  });

  it("returns 'untitled' for empty input", () => {
    expect(slugify("")).toBe("untitled");
  });

  it("returns 'untitled' for symbol-only input", () => {
    expect(slugify("!@#$%^&*()")).toBe("untitled");
  });

  it("truncates to max length 48", () => {
    const longString = "a".repeat(50);
    expect(slugify(longString)).toBe("a".repeat(48));
  });
});

describe("pad", () => {
  it("pads to default width 2", () => {
    expect(pad(5)).toBe("05");
  });

  it("pads to custom width", () => {
    expect(pad(5, 4)).toBe("0005");
  });

  it("leaves larger number unchanged", () => {
    expect(pad(1234, 3)).toBe("1234");
  });
});

describe("KEY_RE", () => {
  it("accepts valid key 'a-b1'", () => {
    expect(KEY_RE.test("a-b1")).toBe(true);
  });

  it("rejects invalid key '-a'", () => {
    expect(KEY_RE.test("-a")).toBe(false);
  });

  it("rejects invalid key 'A'", () => {
    expect(KEY_RE.test("A")).toBe(false);
  });
});
