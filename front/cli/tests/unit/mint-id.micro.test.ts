import { describe, it, expect } from "vitest";
import { mintId, ID_RE } from "../../src/course/ids.ts";

describe("mintId", () => {
  it("should return an id of length 26", () => {
    const id = mintId();
    expect(id.length).toBe(26);
  });

  it("should return an id that matches ID_RE", () => {
    const id = mintId();
    expect(ID_RE.test(id)).toBe(true);
  });

  it("should return a lowercase id", () => {
    const id = mintId();
    expect(id).toBe(id.toLowerCase());
  });

  it("should be deterministic for the same inputs", () => {
    const now = Date.now();
    const random = new Uint8Array([0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
    const id1 = mintId(now, random);
    const id2 = mintId(now, random);
    expect(id1).toBe(id2);
  });

  it("should return a tail of 16 zeros '0' with all-zero random array", () => {
    const now = Date.now();
    const random = new Uint8Array([0, 0, 0, 0, 0, 0, 0, 0, 0, 0]);
    const id = mintId(now, random);
    expect(id.slice(10)).toBe("0000000000000000");
  });

  it("should return different heads for different now values", () => {
    const id1 = mintId(Date.now() - 1000);
    const id2 = mintId(Date.now());
    expect(id1.slice(0, 10)).not.toBe(id2.slice(0, 10));
  });

  it("should sort ids in increasing order based on now", () => {
    const id1 = mintId(Date.now() - 1000);
    const id2 = mintId(Date.now());
    expect(id1 < id2).toBe(true);
  });

  it("should handle edge case where now is 0", () => {
    const id = mintId(0);
    expect(id.slice(0, 10)).toBe("0000000000");
  });

  it("should handle edge case where random array is all 255", () => {
    const now = Date.now();
    const random = new Uint8Array([255, 255, 255, 255, 255, 255, 255, 255, 255, 255]);
    const id = mintId(now, random);
    expect(id.slice(10)).toBe("zzzzzzzzzzzzzzzz");
  });
});
