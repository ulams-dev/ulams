import { describe, expect, it } from "vitest";
import { insightsTarget, isInsightsCall, isLivingCourseCall, isStudioCall } from "../../src/lib/studio-rules.ts";

describe("Insights BFF allow-list", () => {
  it.each([
    ["GET", "/learner-insights/me"],
    ["GET", "/learner-insights/courses/5/summary"],
    ["GET", "/learner-insights/courses/5/elements"],
    ["GET", "/learner-insights/courses/5/learners"],
    ["GET", "/learner-insights/learners/21/courses/5/timeline"],
  ])("forwards %s %s", (method, path) => {
    expect(isInsightsCall(method, path)).toBe(true);
    expect(isStudioCall(method, path)).toBe(false);
    expect(isLivingCourseCall(method, path)).toBe(false);
  });

  it.each([
    ["POST", "/learner-insights/courses/5/elements/01arz3ndektsv4rrffq69g5fav/improve"],
    ["POST", "/learner-insights/courses/5/elements/01ARZ3NDEKTSV4RRFFQ69G5FAV/improve"],
    ["POST", "/learner-insights/courses/5/elements/question%3A3/improve"],
    ["POST", "/learner-insights/courses/5/elements/topic:14/improve"],
  ])("forwards %s %s (the one write: propose an improvement)", (method, path) => {
    expect(isInsightsCall(method, path)).toBe(true);
    expect(isStudioCall(method, path)).toBe(false);
    expect(isLivingCourseCall(method, path)).toBe(false);
  });

  it.each([
    // the improve call is the only write, with exactly this shape
    ["GET", "/learner-insights/courses/5/elements/01arz3ndektsv4rrffq69g5fav/improve"],
    ["PUT", "/learner-insights/courses/5/elements/01arz3ndektsv4rrffq69g5fav/improve"],
    ["POST", "/learner-insights/courses/5/elements/short/improve"],
    ["POST", "/learner-insights/courses/5/elements/01arz3ndektsv4rrffq69g5fav/improve/x"],
    ["POST", "/learner-insights/courses/5/elements/../improve"],
    ["POST", "/learner-insights/courses/5/elements/other:3/improve"],
    ["POST", "/learner-insights/courses/abc/elements/01arz3ndektsv4rrffq69g5fav/improve"],
    ["POST", "/learner-insights/courses/5/elements/question:3x/improve"],
    ["POST", "/learner-insights/courses/5/elements/question%3A%2F3/improve"],
    ["POST", "/learner-insights/courses/5/elements//improve"],
  ])("refuses %s %s", (method, path) => {
    expect(isInsightsCall(method, path)).toBe(false);
  });

  it.each([
    // nothing else can be written from the browser
    ["POST", "/learner-insights/backfill"],
    ["POST", "/learner-insights/courses/5/summary"],
    ["PUT", "/learner-insights/courses/5/settings"],
    ["DELETE", "/learner-insights/me"],
    ["PUT", "/learner-insights/me/settings"],
    // the learner's own data of the author stays out
    ["GET", "/learner-insights/me/export"],
    ["GET", "/learner-insights/courses/5/presentation"],
    ["GET", "/learner-insights/courses/5/topics/3/support"],
    // admin settings are for the admin app
    ["GET", "/learner-insights/settings"],
    ["GET", "/learner-insights/courses/5/settings"],
    // malformed or traversing paths
    ["GET", "/learner-insights/courses/abc/summary"],
    ["GET", "/learner-insights/courses/5/summary/x"],
    ["GET", "/learner-insights/courses/5/../../users"],
    ["GET", "/learner-insights/courses//summary"],
    ["GET", "/learner-insights/"],
    ["GET", "/learner-insights"],
    ["GET", "/courses/5/summary"],
    ["GET", "/learner-insights/learners/21/courses/5"],
    ["GET", "/learner-insights/learners/21/courses/5/timeline/x"],
  ])("refuses %s %s", (method, path) => {
    expect(isInsightsCall(method, path)).toBe(false);
  });

  it("maps the calls onto the API: analytics under /api/admin, `me` on the learner endpoint", () => {
    expect(insightsTarget("/learner-insights/me")).toBe("/api/learner-insights/me");
    expect(insightsTarget("/learner-insights/courses/5/summary")).toBe("/api/admin/learner-insights/courses/5/summary");
    expect(insightsTarget("/learner-insights/learners/21/courses/5/timeline")).toBe("/api/admin/learner-insights/learners/21/courses/5/timeline");
    expect(insightsTarget("/learner-insights/courses/5/elements/question%3A3/improve")).toBe("/api/admin/learner-insights/courses/5/elements/question%3A3/improve");
  });
});
