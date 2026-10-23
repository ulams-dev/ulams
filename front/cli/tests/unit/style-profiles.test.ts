import { describe, expect, it } from "vitest";
import { runCli, type Handler } from "../helpers.ts";

const env = { ULAMS_URL: "http://coffee.localhost", ULAMS_TOKEN: "tok-123456" };
const P = "/api/admin/course-builder/style-profiles";
const ID = "01j9z3k8m2x4q7r5t6v8w0y1ab";
const ok = (data: unknown, status = 200): Handler => ({ status, body: { success: true, data, message: "OK" } });
const profile = { id: ID, name: "Northwind", status: "suggested", profile: { schemaVersion: 1, voice: { person: "you", citations: ["frg_aaaaaaaaaaaa"] } }, warnings: [] };

const data = (r: { json(): Record<string, unknown> }) => r.json().data as Record<string, any>; // eslint-disable-line @typescript-eslint/no-explicit-any

describe("style-profiles", () => {
  it("list filters by status and sends the token", async () => {
    const r = await runCli(["style-profiles", "list", "--status", "approved", "--json"], { env, routes: { [`GET ${P}`]: ok([profile]) } });
    expect(r.code).toBe(0);
    expect(r.requests[0]?.query).toEqual({ status: "approved" });
    expect(r.requests[0]?.headers.authorization).toBe("Bearer tok-123456");
    expect(data(r)).toHaveLength(1);
    r.cleanup();
  });

  it("propose --file uploads the style guide as multipart", async () => {
    const r = await runCli(["style-profiles", "propose", "--file", "guide.md", "--name", "Northwind", "--json"], {
      env,
      files: { "guide.md": "# Guide\n\nWrite to the learner as \"you\".\n" },
      routes: { [`POST ${P}`]: ok(profile, 201) },
    });
    expect(r.code).toBe(0);
    const form = r.requests[0]?.form;
    expect(form?.get("file")).toBeInstanceOf(File);
    expect(form?.get("name")).toBe("Northwind");
    expect(data(r)).toMatchObject({ id: ID, status: "suggested" });
    r.cleanup();
  });

  it("propose --from with values sends JSON, and refuses mixing a file with values", async () => {
    const r = await runCli(["style-profiles", "propose", "--from", ID, "--profile", '{"voice":{"person":"we"}}', "--json"], { env, routes: { [`POST ${P}`]: ok(profile, 201) } });
    expect(r.code).toBe(0);
    expect(r.requests[0]?.body).toEqual({ profile: { voice: { person: "we" } }, from: ID });
    r.cleanup();

    const bad = await runCli(["style-profiles", "propose", "--file", "guide.md", "--from", ID, "--json"], { env, files: { "guide.md": "x" }, routes: {} });
    expect(bad.code).toBe(2);
    expect(bad.json()).toMatchObject({ error: { code: "INPUT_INVALID" } });
    expect(bad.requests).toHaveLength(0);
    bad.cleanup();

    const none = await runCli(["style-profiles", "propose", "--json"], { env, routes: {} });
    expect(none.code).toBe(2);
    none.cleanup();
  });

  it("an unreadable style guide is an input error", async () => {
    const r = await runCli(["style-profiles", "propose", "--file", "missing-guide.md", "--json"], { env, routes: {} });
    expect(r.code).toBe(2);
    expect(r.json()).toMatchObject({ error: { code: "INPUT_INVALID" } });
    r.cleanup();
  });

  it("get, edit and approve call the profile routes; --dry-run sends nothing", async () => {
    const routes = {
      [`GET ${P}/${ID}`]: ok({ ...profile, diff: [], fragments: {} }),
      [`PATCH ${P}/${ID}`]: ok(profile),
      [`POST ${P}/${ID}/approve`]: ok({ ...profile, status: "approved" }),
    };
    const get = await runCli(["style-profiles", "get", ID, "--json"], { env, routes });
    expect(data(get)).toMatchObject({ id: ID, diff: [] });
    get.cleanup();

    const edit = await runCli(["style-profiles", "edit", ID, "--name", "v2", "--json"], { env, routes });
    expect(edit.requests[0]?.body).toEqual({ name: "v2" });
    edit.cleanup();

    const empty = await runCli(["style-profiles", "edit", ID, "--json"], { env, routes });
    expect(empty.code).toBe(2);
    empty.cleanup();

    const approve = await runCli(["style-profiles", "approve", ID, "--json"], { env, routes });
    expect(data(approve).status).toBe("approved");
    approve.cleanup();

    const dry = await runCli(["style-profiles", "approve", ID, "--dry-run", "--json"], { env, routes });
    expect(dry.code).toBe(0);
    expect(dry.requests).toHaveLength(0);
    dry.cleanup();
  });

  it("delete needs --yes", async () => {
    const routes = { [`DELETE ${P}/${ID}`]: ok({ id: ID }) };
    const refused = await runCli(["style-profiles", "delete", ID, "--json"], { env, routes });
    expect(refused.code).not.toBe(0);
    expect(refused.requests).toHaveLength(0);
    refused.cleanup();

    const done = await runCli(["style-profiles", "delete", ID, "--yes", "--json"], { env, routes });
    expect(done.code).toBe(0);
    expect(done.requests[0]?.method).toBe("DELETE");
    done.cleanup();
  });
});

describe("style profile scope and the brief", () => {
  it("propose --scope brief sends scopeType and scopeId, as a form for a file too", async () => {
    const json = await runCli(["style-profiles", "propose", "--profile", '{"voice":{"person":"we"}}', "--name", "Expert track", "--scope", "brief", "--scope-id", "expert-track", "--json"], { env, routes: { [`POST ${P}`]: ok(profile, 201) } });
    expect(json.requests[0]?.body).toMatchObject({ name: "Expert track", scopeType: "brief", scopeId: "expert-track" });
    json.cleanup();
    const file = await runCli(["style-profiles", "propose", "--file", "guide.md", "--scope", "brief", "--name", "Expert", "--json"], { env, files: { "guide.md": "x" }, routes: { [`POST ${P}`]: ok(profile, 201) } });
    expect(file.requests[0]?.form?.get("scopeType")).toBe("brief");
    file.cleanup();
  });

  it("builder brief set --style-profile-id names a profile, and none clears it", async () => {
    const S = "01j9z3k8m2x4q7r5t6v8w0y1ab";
    const route = `PUT /api/admin/course-builder/sessions/${S}/brief`;
    const set = await runCli(["builder", "brief", "set", S, "--style-profile-id", ID, "--json"], { env, routes: { [route]: ok({ brief: {}, stale: false }) } });
    expect(set.requests[0]?.body).toEqual({ brief: { styleProfileId: ID } });
    set.cleanup();
    const clear = await runCli(["builder", "brief", "set", S, "--style-profile-id", "none", "--json"], { env, routes: { [route]: ok({ brief: {}, stale: false }) } });
    expect(clear.requests[0]?.body).toEqual({ brief: { styleProfileId: null } });
    clear.cleanup();
  });
});
