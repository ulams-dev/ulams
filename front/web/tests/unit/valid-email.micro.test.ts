import { describe, it, expect } from "vitest";
import { validEmail } from "../../src/lib/accessibility-statement.ts";

describe("validEmail", () => {
  it("returns the email unchanged if it is valid", () => {
    expect(validEmail("test@example.com")).toBe("test@example.com");
  });

  it("returns null if the input is null", () => {
    expect(validEmail(null)).toBe(null);
  });

  it("returns null if the input is an empty string", () => {
    expect(validEmail("")).toBe(null);
  });

  it("returns null if the email contains spaces", () => {
    expect(validEmail("test example.com")).toBe(null);
  });

  it("returns null if the email is missing a dot in the domain", () => {
    expect(validEmail("test@examplecom")).toBe(null);
  });

  it("returns null if the email contains two @ symbols", () => {
    expect(validEmail("test@@example.com")).toBe(null);
  });

  it("returns null if the email contains a comma", () => {
    expect(validEmail("test@example,com")).toBe(null);
  });

  it("returns null if the email contains invalid characters", () => {
    expect(validEmail("test@ex<ample.com")).toBe(null);
  });

  it("returns null if the email contains invalid characters in the domain", () => {
    expect(validEmail("test@example.c<m")).toBe(null);
  });
});
