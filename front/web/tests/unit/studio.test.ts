import { describe, expect, it } from "vitest";
import { isLivingCourseCall, isStudioCall } from "../../src/lib/studio-rules.ts";
import { STATUS_LABEL } from "../../src/studio/labels.ts";

const S = "01m4fj5zsgfxhzh3cantwevncw";

describe("studio BFF allow-list", () => {
  it.each([
    ["GET", "/sessions"],
    ["POST", "/sessions"],
    ["GET", `/sessions/${S}`],
    ["POST", `/sessions/${S}/sources`],
    ["POST", `/sessions/${S}/runs`],
    ["GET", `/sessions/${S}/events`],
    ["GET", "/fragments/frg_abcdefgh2345"],
    ["POST", `/versions/${S}/approve`],
    ["POST", `/versions/${S}/restore`],
    ["POST", `/sessions/${S}/undo`],
    ["POST", `/sessions/${S}/publish`],
    ["POST", `/runs/${S}/steps/${S}/retry`],
  ])("forwards %s %s", (method, path) => {
    expect(isStudioCall(method, path)).toBe(true);
  });

  it.each([
    ["DELETE", "/sessions"],
    ["GET", "/sessions/../../api/users"],
    ["POST", `/sessions/${S}/events`],
    ["GET", "/fragments/frg_x"],
    ["PUT", `/versions/${S}/approve`],
    ["GET", "/admin/users"],
    ["POST", `/sessions/${S.toUpperCase()}/runs`],
  ])("refuses %s %s", (method, path) => {
    expect(isStudioCall(method, path)).toBe(false);
  });

  it.each([
    ["GET", `/living-course/sessions/${S}/sources`],
    ["GET", `/living-course/sources/${S}/revisions`],
    ["POST", `/living-course/sources/${S}/revisions`],
    ["GET", `/living-course/revisions/${S}`],
    ["GET", `/living-course/revisions/${S}/changes`],
    ["GET", `/living-course/sessions/${S}/staleness`],
    ["GET", `/living-course/sessions/${S}/proposals`],
    ["GET", `/living-course/proposals/${S}`],
  ])("forwards living course %s %s", (method, path) => {
    expect(isLivingCourseCall(method, path)).toBe(true);
    expect(isStudioCall(method, path)).toBe(false);
  });

  it.each([
    ["DELETE", `/living-course/sources/${S}/revisions`],
    ["PUT", `/living-course/revisions/${S}`],
    ["POST", `/living-course/revisions/${S}/changes`],
    ["POST", `/living-course/sessions/${S}/sources`],
    ["GET", `/living-course/revisions/${S}/../../users`],
    ["GET", `/living-course/revisions/${S.toUpperCase()}`],
    ["GET", `/living-course/connections/${S}`],
    ["GET", `/sources/${S}/revisions`],
    ["GET", "/living-course/"],
    ["POST", `/living-course/sessions/${S}/staleness`],
    ["POST", `/living-course/sessions/${S}/proposals`],
    ["DELETE", `/living-course/proposals/${S}`],
    ["GET", `/living-course/proposals/${S}/items`],
    ["GET", `/living-course/proposals/${S}/../x`],
  ])("refuses living course %s %s", (method, path) => {
    expect(isLivingCourseCall(method, path)).toBe(false);
  });

  it("has a label for every session status", () => {
    expect(Object.keys(STATUS_LABEL)).toHaveLength(10);
  });
});
