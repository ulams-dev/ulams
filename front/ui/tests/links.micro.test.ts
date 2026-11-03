import { describe, it, expect } from "vitest";
import { isExternal, linkTarget } from "../src/lib/links.ts";

describe("isExternal", () => {
  it("returns false for undefined", () => {
    expect(isExternal(undefined)).toBe(false);
  });

  it("returns false for relative path", () => {
    expect(isExternal("/relative/path")).toBe(false);
  });

  it("returns true for external https://github.com/x", () => {
    expect(isExternal("https://github.com/x")).toBe(true);
  });

  it("returns false for internal https://docs.ulams.app/", () => {
    expect(isExternal("https://docs.ulams.app/")).toBe(false);
  });

  it("returns false for internal https://localhost", () => {
    expect(isExternal("https://localhost")).toBe(false);
  });

  it("returns false for mailto:", () => {
    expect(isExternal("mailto:example@example.com")).toBe(false);
  });

  it("returns false for malformed http URL", () => {
    expect(isExternal("http:/malformed")).toBe(false);
  });
});

describe("linkTarget", () => {
  it("returns {} for undefined", () => {
    expect(linkTarget(undefined)).toEqual({});
  });

  it("returns {} for relative path", () => {
    expect(linkTarget("/relative/path")).toEqual({});
  });

  it("returns { target: '_blank', rel: 'noopener noreferrer' } for external https://github.com/x", () => {
    expect(linkTarget("https://github.com/x")).toEqual({ target: "_blank", rel: "noopener noreferrer" });
  });

  it("returns {} for internal https://docs.ulams.app/", () => {
    expect(linkTarget("https://docs.ulams.app/")).toEqual({});
  });

  it("returns {} for internal https://localhost", () => {
    expect(linkTarget("https://localhost")).toEqual({});
  });

  it("returns {} for mailto:", () => {
    expect(linkTarget("mailto:example@example.com")).toEqual({});
  });

  it("returns {} for malformed http URL", () => {
    expect(linkTarget("http:/malformed")).toEqual({});
  });
});
