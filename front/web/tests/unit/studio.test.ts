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
    ["GET", `/sessions/${S}/publish-check`],
    ["GET", `/sessions/${S}/citations`],
    ["GET", `/sessions/${S}/critiques`],
    ["POST", `/sessions/${S}/outline`],
    ["POST", `/sessions/${S}/global-edit`],
    ["POST", `/sessions/${S}/elements/${S}/variants`],
    ["POST", `/sessions/${S}/new-site`],
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
    ["POST", `/living-course/proposals/${S}/analyse`],
    ["POST", `/living-course/proposals/${S}/accept-all`],
    ["POST", `/living-course/proposals/${S}/reject`],
    ["POST", `/living-course/proposals/${S}/apply`],
    ["POST", `/living-course/proposals/${S}/items/${S}/accept`],
    ["POST", `/living-course/proposals/${S}/items/${S}/reject`],
    ["POST", `/living-course/proposals/${S}/items/${S}/reset`],
    ["POST", `/living-course/proposals/${S}/items/${S}/regenerate`],
    ["GET", `/living-course/sessions/${S}/audit`],
    ["GET", `/living-course/sessions/${S}/audit/verify`],
    ["GET", `/living-course/sessions/${S}/audit/export`],
    ["PUT", `/living-course/proposals/${S}/learner-note`],
    ["PUT", `/living-course/connections/${S}`],
    ["GET", "/living-course/connectors"],
    ["POST", `/living-course/sessions/${S}/sources/connect`],
    ["POST", `/living-course/connections/${S}/check`],
    ["POST", `/living-course/connections/${S}/webhook-secret`],
    ["DELETE", `/living-course/connections/${S}`],
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
    ["POST", "/living-course/connectors"],
    ["GET", `/living-course/sessions/${S}/sources/connect`],
    ["PUT", `/living-course/sessions/${S}/sources/connect`],
    ["GET", `/living-course/connections/${S}/check`],
    ["GET", `/living-course/connections/${S}/webhook-secret`],
    ["PUT", `/living-course/connections/${S}/webhook-secret`],
    ["POST", `/living-course/connections/${S}/secrets`],
    ["POST", `/living-course/connections/${S}/check/../../x`],
    ["DELETE", `/living-course/connections/${S}/check`],
    ["GET", `/sources/${S}/revisions`],
    ["GET", "/living-course/"],
    ["POST", `/living-course/sessions/${S}/staleness`],
    ["POST", `/living-course/sessions/${S}/proposals`],
    ["DELETE", `/living-course/proposals/${S}`],
    ["GET", `/living-course/proposals/${S}/items`],
    ["GET", `/living-course/proposals/${S}/../x`],
    ["GET", `/living-course/proposals/${S}/apply`],
    ["PUT", `/living-course/proposals/${S}/apply`],
    ["POST", `/living-course/proposals/${S}/learner-note`],
    ["POST", `/living-course/proposals/${S}/items/${S}/delete`],
    ["POST", `/living-course/proposals/${S}/items/${S}`],
    ["POST", `/living-course/proposals/${S}/items/../apply`],
    ["DELETE", `/living-course/proposals/${S}/items/${S}/accept`],
    // the audit trail is append-only and read from here
    ["POST", `/living-course/sessions/${S}/audit`],
    ["PUT", `/living-course/sessions/${S}/audit`],
    ["DELETE", `/living-course/sessions/${S}/audit`],
    ["POST", `/living-course/sessions/${S}/audit/verify`],
    ["GET", `/living-course/sessions/${S}/audit/other`],
    ["GET", `/living-course/sessions/${S}/audit/export/x`],
    ["GET", `/living-course/sessions/${S}/audit/../staleness/x`],
    // a connection cannot be read or created from the browser BFF; the note cannot be read or deleted
    ["POST", `/living-course/connections/${S}`],
    ["PUT", `/living-course/connections/${S}/secret`],
    ["GET", `/living-course/proposals/${S}/learner-note`],
    ["DELETE", `/living-course/proposals/${S}/learner-note`],
    // the academy-wide trail is for admins in the admin app
    ["GET", "/living-course/audit/export"],
    ["GET", "/living-course/audit/verify"],
  ])("refuses living course %s %s", (method, path) => {
    expect(isLivingCourseCall(method, path)).toBe(false);
  });

  it("has a label for every session status", () => {
    expect(Object.keys(STATUS_LABEL)).toHaveLength(10);
  });
});
