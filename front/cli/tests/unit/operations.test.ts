import { describe, expect, it } from "vitest";
import { runCli } from "../helpers.ts";

const env = { ULAMS_URL: "http://coffee.localhost", ULAMS_TOKEN: "tok-123456" };
const states = (state: string) => ({ "GET /api/admin/video/states": { body: { success: true, data: [{ topic_id: 7, state }] } } });

describe("operations", () => {
  it("get reports the state and wait returns when finished", async () => {
    const got = await runCli(["operations", "get", "video:7", "--json"], { env, routes: states("coding") });
    expect(got.json()).toMatchObject({ data: { handle: "video:7", status: "running" } });
    const done = await runCli(["operations", "wait", "video:7", "--json"], { env, routes: states("finished") });
    expect(done.json()).toMatchObject({ data: { status: "succeeded" } });
  });

  it("wait exits 10 with the handle on timeout", async () => {
    const r = await runCli(["operations", "wait", "video:7", "--timeout", "0", "--json"], { env, routes: states("coding") });
    expect(r.code).toBe(10);
    expect(r.json()).toMatchObject({ error: { code: "TIMEOUT", details: { operation: "video:7" } } });
  });

  it("a failed operation is an error with the handle", async () => {
    const r = await runCli(["operations", "wait", "video:7", "--json"], { env, routes: states("error") });
    expect(r.code).toBe(9);
    expect(r.json()).toMatchObject({ error: { details: { operation: "video:7" } } });
  });

  it("bad handles and unknown kinds are exit 2", async () => {
    expect((await runCli(["operations", "get", "nope", "--json"], { env })).code).toBe(2);
    expect((await runCli(["operations", "get", "x:1", "--json"], { env })).code).toBe(2);
    const kinds = await runCli(["operations", "kinds", "--json"], {});
    expect(kinds.json()).toMatchObject({ data: [{ kind: "video" }] });
  });
});
