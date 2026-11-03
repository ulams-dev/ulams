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
