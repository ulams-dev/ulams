import { describe, expect, it } from "vitest";
import { matchBffRule } from "../../src/lib/bff.ts";

describe("BFF allow-list for course variants (learner)", () => {
  it.each([
    ["GET", "/api/courses/5/variant"],
    ["PUT", "/api/courses/5/variant"],
    ["POST", "/api/courses/5/variant/hints/12/dismiss"],
    ["GET", "/api/courses/5/placement"],
    ["POST", "/api/courses/5/placement/attempts"],
    ["put", "/api/courses/1234567890/variant"],
  ])("forwards %s %s", (method, path) => {
    expect(matchBffRule(method, path)).not.toBeNull();
  });

  it("drops the cached progress after a switch, which can carry completion over", () => {
    expect(matchBffRule("PUT", "/api/courses/5/variant")?.writesProgress).toBe(true);
    expect(matchBffRule("GET", "/api/courses/5/variant")?.writesProgress).toBeUndefined();
  });

  it.each([
    // the public picker data is read on the server, not through the session proxy
    ["GET", "/api/courses/5/variants"],
    // the verbs the API does not have
    ["POST", "/api/courses/5/variant"],
    ["PATCH", "/api/courses/5/variant"],
    ["DELETE", "/api/courses/5/variant"],
    ["PUT", "/api/courses/5/placement"],
    ["GET", "/api/courses/5/placement/attempts"],
    ["POST", "/api/courses/5/variant/hints/12"],
    ["GET", "/api/courses/5/variant/hints/12/dismiss"],
    // malformed or traversing paths
    ["GET", "/api/courses/abc/variant"],
    ["GET", "/api/courses//variant"],
    ["PUT", "/api/courses/5/variant/x"],
    ["POST", "/api/courses/5/variant/hints/x/dismiss"],
    ["POST", "/api/courses/5/variant/hints/12/dismiss/x"],
    ["GET", "/api/courses/5/../6/variant"],
    // the administrator's families are not for the learner proxy
    ["GET", "/api/admin/course-variant-groups"],
    ["PUT", "/api/admin/course-variant-groups/1/assignments"],
  ])("refuses %s %s", (method, path) => {
    expect(matchBffRule(method, path)).toBeNull();
  });
});
