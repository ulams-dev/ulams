import { describe, expect, it } from "vitest";
import { authoringMetricsTarget, isAuthoringMetricsCall, isCourseVersionsCall, isInsightsCall, isLivingCourseCall, isStudioCall } from "../../src/lib/studio-rules.ts";

describe("Authoring metrics BFF allow-list", () => {
  it.each([
    ["GET", "/authoring-metrics/courses/5"],
    ["GET", "/authoring-metrics/courses/1234567890"],
  ])("forwards %s %s", (method, path) => {
    expect(isAuthoringMetricsCall(method, path)).toBe(true);
    expect(isStudioCall(method, path)).toBe(false);
    expect(isLivingCourseCall(method, path)).toBe(false);
    expect(isInsightsCall(method, path)).toBe(false);
    expect(isCourseVersionsCall(method, path)).toBe(false);
  });

  it.each([
    // one course at a time and read-only: the list and the export are for administrators, in the admin
    ["GET", "/authoring-metrics/courses"],
    ["GET", "/authoring-metrics/courses/export"],
    ["GET", "/authoring-metrics/summary"],
    ["POST", "/authoring-metrics/courses/5"],
    ["PUT", "/authoring-metrics/courses/5"],
    ["DELETE", "/authoring-metrics/courses/5"],
    ["POST", "/authoring-metrics/activity"],
    // malformed or traversing paths
    ["GET", "/authoring-metrics/courses/abc"],
    ["GET", "/authoring-metrics/courses/12345678901"],
    ["GET", "/authoring-metrics/courses/5/x"],
    ["GET", "/authoring-metrics/courses/5/../../users"],
    ["GET", "/authoring-metrics/courses/"],
    ["GET", "/authoring-metrics/"],
    ["GET", "/authoring-metrics"],
    ["GET", "/courses/5"],
  ])("refuses %s %s", (method, path) => {
    expect(isAuthoringMetricsCall(method, path)).toBe(false);
  });

  it("maps the call onto the API", () => {
    expect(authoringMetricsTarget("/authoring-metrics/courses/5")).toBe("/api/admin/authoring-metrics/courses/5");
  });
});
