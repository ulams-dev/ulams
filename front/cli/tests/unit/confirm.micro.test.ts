import { describe, it, expect, beforeEach } from "vitest";
import { ConfirmTokens } from "../../src/mcp/confirm";

describe("ConfirmTokens", () => {
  let confirmTokens: ConfirmTokens;

  beforeEach(() => {
    confirmTokens = new ConfirmTokens();
  });

  it("should issue a valid token", () => {
    const token = confirmTokens.issue("id1", { key: "value" });
    expect(token).toMatch(/^\d+\.[a-f0-9]+$/);
  });

  it("should consume a valid token", () => {
    const token = confirmTokens.issue("id1", { key: "value" });
    expect(confirmTokens.consume(token, "id1", { key: "value" })).toBe(true);
  });

  it("should reject a reused token", () => {
    const token = confirmTokens.issue("id1", { key: "value" });
    expect(confirmTokens.consume(token, "id1", { key: "value" })).toBe(true);
    expect(confirmTokens.consume(token, "id1", { key: "value" })).toBe(false);
  });

  it("should reject a token with a different id", () => {
    const token = confirmTokens.issue("id1", { key: "value" });
    expect(confirmTokens.consume(token, "id2", { key: "value" })).toBe(false);
  });

  it("should reject a token with different input", () => {
    const token = confirmTokens.issue("id1", { key: "value" });
    expect(confirmTokens.consume(token, "id1", { key: "different" })).toBe(false);
  });


  it("should reject a tampered token", () => {
    const token = confirmTokens.issue("id1", { key: "value" });
    const [exp, sig] = token.split(".");
    const tamperedToken = `${exp}.${sig.slice(0, -1)}`;
    expect(confirmTokens.consume(tamperedToken, "id1", { key: "value" })).toBe(false);
  });

  it("should handle empty input", () => {
    const token = confirmTokens.issue("id1", null);
    expect(confirmTokens.consume(token, "id1", null)).toBe(true);
  });
});
