import { describe, expect, it } from "vitest";
import { SANDBOX_H5P, SANDBOX_LIASCRIPT, SANDBOX_OPAQUE, SANDBOX_SCORM, SANDBOX_THIRD_PARTY, frameTargetOrigin, isTrustedFrameMessage } from "../src/frames.ts";

describe("sandbox policies", () => {
  it("the opaque policy never has allow-same-origin or top navigation", () => {
    expect(SANDBOX_OPAQUE).toContain("allow-scripts");
    expect(SANDBOX_OPAQUE).not.toContain("allow-same-origin");
  });
  it.each([SANDBOX_SCORM, SANDBOX_LIASCRIPT, SANDBOX_H5P, SANDBOX_THIRD_PARTY, SANDBOX_OPAQUE])("%s never allows top navigation", (policy) => {
    expect(policy).not.toMatch(/allow-top-navigation/);
    expect(policy).not.toMatch(/allow-popups-to-escape-sandbox.*allow-top/);
  });
});

describe("isTrustedFrameMessage", () => {
  const frame = {} as Window;
  it("needs the embedded frame's window and the exact origin", () => {
    expect(isTrustedFrameMessage({ source: frame, origin: "https://acme.api.ulams.app" }, { frame, origin: "https://acme.api.ulams.app" })).toBe(true);
    expect(isTrustedFrameMessage({ source: frame, origin: "https://acme.content.ulams.app" }, { frame, origin: "https://acme.api.ulams.app" })).toBe(false);
    expect(isTrustedFrameMessage({ source: {}, origin: "https://acme.api.ulams.app" }, { frame, origin: "https://acme.api.ulams.app" })).toBe(false);
    expect(isTrustedFrameMessage({ source: null, origin: "null" }, { frame: null, origin: "null" })).toBe(false);
  });
  it("accepts the opaque origin only when the frame itself is the source", () => {
    expect(isTrustedFrameMessage({ source: frame, origin: "null" }, { frame, origin: "null" })).toBe(true);
    expect(isTrustedFrameMessage({ source: {}, origin: "null" }, { frame, origin: "null" })).toBe(false);
  });
  it("posts to '*' only for an opaque frame", () => {
    expect(frameTargetOrigin("null")).toBe("*");
    expect(frameTargetOrigin("https://acme.api.ulams.app")).toBe("https://acme.api.ulams.app");
  });
});
