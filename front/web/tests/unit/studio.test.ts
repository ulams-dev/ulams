import { describe, expect, it } from "vitest";
import { isStudioCall } from "../../src/lib/studio-rules.ts";
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

  it("has a label for every session status", () => {
    expect(Object.keys(STATUS_LABEL)).toHaveLength(10);
  });
});
