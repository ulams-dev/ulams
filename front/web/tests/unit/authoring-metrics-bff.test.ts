import { describe, expect, it } from "vitest";
import { authoringMetricsTarget, isAuthoringMetricsCall, isCourseVersionsCall, isInsightsCall, isLivingCourseCall, isStudioCall } from "../../src/lib/studio-rules.ts";

describe("Authoring metrics BFF allow-list", () => {
  it.each([
    ["GET", "/authoring-metrics/courses/5"],
    ["GET", "/authoring-metrics/courses/1234567890"],
    ["GET", "/authoring-metrics/activity/status"],
    ["POST", "/authoring-metrics/activity"],
  ])("forwards %s %s", (method, path) => {
    expect(isAuthoringMetricsCall(method, path)).toBe(true);
    expect(isStudioCall(method, path)).toBe(false);
    expect(isLivingCourseCall(method, path)).toBe(false);
    expect(isInsightsCall(method, path)).toBe(false);
    expect(isCourseVersionsCall(method, path)).toBe(false);
  });

  it.each([
    // the list and the export are for administrators, in the admin; deleting recorded review time is an admin setting
    ["GET", "/authoring-metrics/courses"],
    ["GET", "/authoring-metrics/courses/export"],
    ["GET", "/authoring-metrics/summary"],
    ["POST", "/authoring-metrics/courses/5"],
    ["PUT", "/authoring-metrics/courses/5"],
    ["DELETE", "/authoring-metrics/courses/5"],
    ["DELETE", "/authoring-metrics/activity"],
    ["GET", "/authoring-metrics/activity"],
    ["POST", "/authoring-metrics/activity/status"],
    ["POST", "/authoring-metrics/activity/x"],
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
