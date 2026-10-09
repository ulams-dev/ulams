import { describe, expect, it } from "vitest";
import { h5pSearch, h5pUsesSession } from "../../src/lib/h5p-proxy.ts";

describe("h5p proxy session rules (ADR 0045)", () => {
  it("adds the session to the learner's own player calls", () => {
    expect(h5pUsesSession("GET", "embed/play/12")).toBe(true);
    expect(h5pUsesSession("GET", "contents/12/play")).toBe(true);
    expect(h5pUsesSession("GET", "contentUserData/12/state/0")).toBe(true);
    expect(h5pUsesSession("POST", "contentUserData/12/state/0")).toBe(true);
    expect(h5pUsesSession("POST", "finishedData")).toBe(true);
    expect(h5pUsesSession("post", "finishedData")).toBe(true);
  });

  it("never adds it to other routes or methods", () => {
    for (const [method, path] of [
      ["GET", "contents"],
      ["POST", "contents"],
      ["DELETE", "contents/12"],
      ["GET", "contents/12"],
      ["POST", "contents/12/play"],
      ["GET", "libraries"],
      ["POST", "libraries"],
      ["GET", "ajax"],
      ["POST", "ajax"],
      ["GET", "embed/edit/12"],
      ["GET", "embed/edit/new"],
      ["GET", "embed/assets/player.js"],
      ["GET", "content-type-cache"],
      ["DELETE", "contentUserData/12/state/0"],
      ["PUT", "finishedData"],
      ["GET", "finishedData"],
      ["GET", "download/12"],
      ["GET", ""],
    ] as const) {
      expect(h5pUsesSession(method, path), `${method} ${path}`).toBe(false);
    }
  });

  it("refuses paths that only look like the allowed ones", () => {
    for (const path of [
      "embed/play/0",
      "embed/play/abc",
      "embed/play/12/extra",
      "embed/play/12/",
      "/embed/play/12",
      "embed//play/12",
      "embed/play/../edit/12",
      "contents/12/../12/play",
      "contentUserData/12/state/..",
      "contentUserData/12/state/0/more",
      "contentUserData/12/sta te/0",
      "finishedData/extra",
      "finishedData/..",
    ]) {
      expect(h5pUsesSession("GET", path) || h5pUsesSession("POST", path), path).toBe(false);
    }
  });

  it("drops a _token the caller put in the query when the session is used", () => {
    expect(h5pSearch("?_token=abc&language=en")).toBe("?language=en");
    expect(h5pSearch("?_token=abc")).toBe("");
    expect(h5pSearch("")).toBe("");
    expect(h5pSearch("?contextId=a")).toBe("?contextId=a");
  });
});
