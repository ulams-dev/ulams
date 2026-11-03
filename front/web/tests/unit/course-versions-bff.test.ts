import { describe, expect, it } from "vitest";
import { courseVersionsTarget, isCourseVersionsCall, isInsightsCall, isLivingCourseCall, isStudioCall } from "../../src/lib/studio-rules.ts";

describe("Course versions BFF allow-list", () => {
  it.each([
    ["GET", "/course-versions/courses/5/releases"],
    ["GET", "/course-versions/courses/1234567890/releases"],
    ["GET", "/course-versions/releases/01arz3ndektsv4rrffq69g5fav"],
    ["GET", "/course-versions/courses/5/versions"],
    ["GET", "/course-versions/courses/5/compare"],
    ["GET", "/course-versions/courses/1234567890/compare"],
  ])("forwards %s %s", (method, path) => {
    expect(isCourseVersionsCall(method, path)).toBe(true);
    expect(isStudioCall(method, path)).toBe(false);
    expect(isLivingCourseCall(method, path)).toBe(false);
    expect(isInsightsCall(method, path)).toBe(false);
  });

  it.each([
    // read-only: a release is written by the apply step
    ["POST", "/course-versions/courses/5/releases"],
    ["PUT", "/course-versions/releases/01arz3ndektsv4rrffq69g5fav"],
    ["DELETE", "/course-versions/releases/01arz3ndektsv4rrffq69g5fav"],
    // malformed or traversing paths
    ["GET", "/course-versions/courses/abc/releases"],
    ["GET", "/course-versions/courses/12345678901/releases"],
    ["GET", "/course-versions/courses/5/releases/x"],
    ["GET", "/course-versions/courses/5/../../users"],
    ["GET", "/course-versions/courses//releases"],
    ["GET", "/course-versions/releases/short"],
    ["GET", "/course-versions/releases/01arz3ndektsv4rrffq69g5fav/x"],
    ["GET", "/course-versions/"],
    ["GET", "/course-versions"],
    ["GET", "/courses/5/releases"],
    // compare and the version list are reads too
    ["POST", "/course-versions/courses/5/compare"],
    ["PUT", "/course-versions/courses/5/versions"],
    ["DELETE", "/course-versions/courses/5/versions"],
    ["GET", "/course-versions/courses/abc/compare"],
    ["GET", "/course-versions/courses/12345678901/versions"],
    ["GET", "/course-versions/courses/5/compare/x"],
    ["GET", "/course-versions/courses/5/versions/x"],
    ["GET", "/course-versions/courses/5/compare/../releases"],
    // variants are not open yet
    ["GET", "/course-versions/courses/5/variants"],
  ])("refuses %s %s", (method, path) => {
    expect(isCourseVersionsCall(method, path)).toBe(false);
  });

  it("maps the calls onto the API", () => {
    expect(courseVersionsTarget("/course-versions/courses/5/releases")).toBe("/api/admin/course-versions/courses/5/releases");
    expect(courseVersionsTarget("/course-versions/courses/5/versions")).toBe("/api/admin/course-versions/courses/5/versions");
    expect(courseVersionsTarget("/course-versions/courses/5/compare")).toBe("/api/admin/course-versions/courses/5/compare");
    expect(courseVersionsTarget("/course-versions/releases/01arz3ndektsv4rrffq69g5fav")).toBe("/api/admin/course-versions/releases/01arz3ndektsv4rrffq69g5fav");
  });
});
