import { describe, it, expect } from "vitest";
import * as frames from "../src/frames.ts";

describe("Sandbox Constants", () => {
  it("SANDBOX_SCORM is a non-empty space-separated string of allow-* tokens without duplicates", () => {
    expect(frames.SANDBOX_SCORM).toBe("allow-scripts allow-forms allow-popups allow-downloads allow-same-origin");
  });

  it("SANDBOX_LIASCRIPT is a non-empty space-separated string of allow-* tokens without duplicates", () => {
    expect(frames.SANDBOX_LIASCRIPT).toBe("allow-scripts allow-forms allow-popups allow-downloads allow-same-origin");
  });

  it("SANDBOX_H5P is a non-empty space-separated string of allow-* tokens without duplicates", () => {
    expect(frames.SANDBOX_H5P).toBe("allow-scripts allow-forms allow-popups allow-downloads allow-same-origin allow-modals allow-presentation allow-popups-to-escape-sandbox");
  });

  it("SANDBOX_THIRD_PARTY is a non-empty space-separated string of allow-* tokens without duplicates", () => {
    expect(frames.SANDBOX_THIRD_PARTY).toBe("allow-scripts allow-forms allow-popups allow-downloads allow-same-origin allow-modals allow-presentation allow-popups-to-escape-sandbox");
  });

  it("SANDBOX_INTERACTIVE is a non-empty space-separated string of allow-* tokens without duplicates", () => {
    expect(frames.SANDBOX_INTERACTIVE).toBe("allow-scripts allow-popups allow-popups-to-escape-sandbox");
  });

  it("SANDBOX_CODE equals 'allow-scripts'", () => {
    expect(frames.SANDBOX_CODE).toBe("allow-scripts");
  });

  it("SANDBOX_INTERACTIVE does not contain allow-same-origin", () => {
    expect(frames.SANDBOX_INTERACTIVE).not.toContain("allow-same-origin");
  });

  it("SANDBOX_OPAQUE is a non-empty space-separated string of allow-* tokens without duplicates", () => {
    expect(frames.SANDBOX_OPAQUE).toBe("allow-scripts allow-forms allow-popups allow-downloads allow-modals allow-presentation");
  });

  it("isTrustedFrameMessage correctly checks if the message is from the trusted frame", () => {
    const frame = { postMessage: () => {} } as Window;
    const origin = "https://example.com";
    const expected: frames.FrameMessageCheck = { frame, origin };
    const event = { source: frame, origin };

    expect(frames.isTrustedFrameMessage(event, expected)).toBe(true);
  });
});
