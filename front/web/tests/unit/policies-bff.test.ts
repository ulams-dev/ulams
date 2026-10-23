import { describe, expect, it } from "vitest";
import { isCourseVersionsCall, isInsightsCall, isLivingCourseCall, isPoliciesCall, isStudioCall, policiesTarget } from "../../src/lib/studio-rules.ts";

const P = "01arz3ndektsv4rrffq69g5fav";

describe("Policies BFF allow-list", () => {
  it.each([
    ["GET", "/policies"],
    ["GET", "/policies/"],
    ["POST", "/policies"],
    ["GET", `/policies/${P}`],
    ["GET", `/policies/${P}/versions`],
    ["POST", `/policies/${P}/versions`],
    ["GET", `/policies/${P}/versions/2`],
    ["POST", `/policies/${P}/versions/2/approve`],
    ["POST", `/policies/${P}/versions/2/reject`],
    ["GET", `/policies/${P}/variants`],
    ["POST", `/policies/${P}/variants/generate`],
    ["GET", `/policies/${P}/dashboard`],
    ["GET", `/policies/${P}/dashboard/export`],
  ])("forwards %s %s", (method, path) => {
    expect(isPoliciesCall(method, path)).toBe(true);
    expect(isStudioCall(method, path)).toBe(false);
    expect(isLivingCourseCall(method, path)).toBe(false);
    expect(isInsightsCall(method, path)).toBe(false);
    expect(isCourseVersionsCall(method, path)).toBe(false);
  });

  it.each([
    ["DELETE", `/policies/${P}`],
    ["PUT", `/policies/${P}`],
    ["POST", `/policies/${P}/dashboard`],
    ["POST", `/policies/${P}/versions/2`],
    ["GET", `/policies/${P}/versions/2/approve`],
    ["GET", `/policies/${P}/variants/generate`],
    ["POST", `/policies/${P}/dashboard/export`],
    ["GET", "/policies/short"],
    ["GET", `/policies/${P}/versions/abc`],
    ["GET", `/policies/${P}/versions/1234567`],
    ["GET", `/policies/${P}/../../users`],
    ["GET", `/policies/${P}/other`],
    ["GET", `/policies/${P}/versions/2/x`],
    ["GET", "/policies2"],
    ["GET", "/policy"],
  ])("refuses %s %s", (method, path) => {
    expect(isPoliciesCall(method, path)).toBe(false);
  });

  it("maps the calls onto the API", () => {
    expect(policiesTarget("/policies")).toBe("/api/admin/policies");
    expect(policiesTarget(`/policies/${P}/dashboard`)).toBe(`/api/admin/policies/${P}/dashboard`);
    expect(policiesTarget(`/policies/${P}/versions/2/approve`)).toBe(`/api/admin/policies/${P}/versions/2/approve`);
  });
});
