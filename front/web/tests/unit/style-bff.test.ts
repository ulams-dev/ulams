import { describe, expect, it } from "vitest";
import { isCourseVersionsCall, isInsightsCall, isLivingCourseCall, isStudioCall } from "../../src/lib/studio-rules.ts";

const ID = "01arz3ndektsv4rrffq69g5fav";

describe("Style profile BFF allow-list", () => {
  it.each([
    ["GET", "/style-profiles"],
    ["POST", "/style-profiles"],
    ["GET", `/style-profiles/${ID}`],
    ["PATCH", `/style-profiles/${ID}`],
    ["DELETE", `/style-profiles/${ID}`],
    ["POST", `/style-profiles/${ID}/approve`],
    ["GET", `/style-profiles/${ID}/check`],
  ])("forwards %s %s to the course builder API", (method, path) => {
    expect(isStudioCall(method, path)).toBe(true);
    expect(isLivingCourseCall(method, path)).toBe(false);
    expect(isInsightsCall(method, path)).toBe(false);
    expect(isCourseVersionsCall(method, path)).toBe(false);
  });

  it.each([
    ["PUT", "/style-profiles"],
    ["DELETE", "/style-profiles"],
    ["PATCH", "/style-profiles"],
    ["PUT", `/style-profiles/${ID}`],
    ["GET", `/style-profiles/${ID}/approve`],
    ["POST", `/style-profiles/${ID}/check`],
    ["GET", `/style-profiles/${ID}/check/x`],
    ["POST", `/style-profiles/${ID}/reject`],
    ["POST", `/style-profiles/${ID}/approve/x`],
    ["GET", "/style-profiles/short"],
    ["GET", "/style-profiles/../sessions"],
    ["GET", `/style-profiles/${ID}/../../users`],
    ["GET", "/style-profiles/"],
    ["GET", "/style-profile"],
  ])("refuses %s %s", (method, path) => {
    expect(isStudioCall(method, path)).toBe(false);
  });
});

describe("Restyle BFF allow-list", () => {
  it("forwards the restyle of a session to Living Course and nothing else of that shape", () => {
    expect(isLivingCourseCall("POST", `/living-course/sessions/${ID}/style-proposals`)).toBe(true);
    expect(isStudioCall("POST", `/living-course/sessions/${ID}/style-proposals`)).toBe(false);
    for (const [method, path] of [
      ["GET", `/living-course/sessions/${ID}/style-proposals`],
      ["PUT", `/living-course/sessions/${ID}/style-proposals`],
      ["DELETE", `/living-course/sessions/${ID}/style-proposals`],
      ["POST", `/living-course/sessions/${ID}/style-proposals/x`],
      ["POST", "/living-course/sessions/short/style-proposals"],
      ["POST", `/sessions/${ID}/style-proposals`],
    ] as const) {
      expect(isLivingCourseCall(method, path) || isStudioCall(method, path), `${method} ${path}`).toBe(false);
    }
  });
});
