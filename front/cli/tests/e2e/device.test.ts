// Opt-in end-to-end test of device login against a running stack (ULAMS_E2E=1 yarn workspace ulams test:e2e).
// The terminal is this test, the person is a Playwright step (approve-device.mjs) that opens
// /cli/authorize as the demo admin. Creates and revokes its own tokens; leaves nothing behind.
import { afterAll, beforeAll, describe, expect, it } from "vitest";
import { spawn, spawnSync } from "node:child_process";
import { existsSync, mkdtempSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import { join, resolve } from "node:path";

const enabled = process.env.ULAMS_E2E === "1";
const URL_ = process.env.ULAMS_E2E_URL ?? "http://coffee.localhost";
const root = resolve(__dirname, "../..");
const dist = resolve(root, "dist/ulams.mjs");
const approve = resolve(__dirname, "approve-device.mjs");

let dir = "";
const env = () => ({ ...process.env, ULAMS_CONFIG_DIR: dir, ULAMS_URL: "", ULAMS_TOKEN: "" });

function ulams(args: string[], extraEnv: Record<string, string> = {}) {
  const res = spawnSync("node", [dist, ...args, "--json"], { encoding: "utf8", env: { ...env(), ...extraEnv } });
  const out = res.stdout.trim() ? (JSON.parse(res.stdout.trim().split("\n")[0] as string) as Record<string, any>) : {}; // eslint-disable-line @typescript-eslint/no-explicit-any
  return { code: res.status ?? -1, out, stderr: res.stderr };
}

describe.skipIf(!enabled)("e2e: device login on the coffee tenant", () => {
  beforeAll(() => {
    if (!existsSync(dist)) spawnSync("npx", ["tsup"], { cwd: root, stdio: "inherit" });
    dir = mkdtempSync(join(tmpdir(), "ulams-e2e-device-"));
  });
  afterAll(() => rmSync(dir, { recursive: true, force: true }));

  it("starts a device login, approves it in the browser, calls whoami, creates and revokes a token, logs out with --revoke", async () => {
    const child = spawn("node", [dist, "login", "--url", URL_, "--device", "--scopes", "@author,tokens:write", "--json"], { env: env() });
    let stderr = "";
    let stdout = "";
    child.stderr.on("data", (c) => (stderr += String(c)));
    child.stdout.on("data", (c) => (stdout += String(c)));
    const finished = new Promise<number>((r) => child.on("close", (code) => r(code ?? -1)));

    const link = await new Promise<string>((resolveLink, reject) => {
      const t0 = Date.now();
      const tick = setInterval(() => {
        const m = /(https?:\/\/\S+\/cli\/authorize\?code=\S+)/.exec(stderr);
        if (m) {
          clearInterval(tick);
          resolveLink(m[1] as string);
        } else if (Date.now() - t0 > 20_000) {
          clearInterval(tick);
          reject(new Error(`no approval link on stderr: ${stderr}`));
        }
      }, 200);
    });

    const person = spawnSync("node", [approve, link, URL_, "approve"], { encoding: "utf8" });
    expect(person.status, person.stderr).toBe(0);

    expect(await finished).toBe(0);
    const login = JSON.parse(stdout.trim().split("\n")[0] as string);
    expect(login.data).toMatchObject({ method: "device", user: expect.stringMatching(/^admin@/) });
    expect(login.data.scopes).toEqual(expect.arrayContaining(["courses:write", "builder:write", "tokens:write"]));
    expect(stdout + stderr).not.toContain("ulams_pat_");

    const who = ulams(["whoami"]);
    expect(who.out.data.auth).toMatchObject({ scoped: true, kind: "cli" });
    expect(who.out.data.user.roles).toContain("admin");

    const created = ulams(["tokens", "create", "--name", `e2e ${Date.now()}`, "--scopes", "courses:read", "--expires-in-days", "1", "--kind", "agent"]);
    expect(created.code, JSON.stringify(created.out)).toBe(0);
    const id = created.out.data.id as string;
    expect(created.out.data.token).toMatch(/^ulams_pat_/);
    expect(ulams(["tokens", "list"]).out.data.map((t: { id: string }) => t.id)).toContain(id);

    // the courses:read token reads but cannot write
    const readOnly = { ULAMS_URL: URL_, ULAMS_TOKEN: created.out.data.token as string };
    expect(ulams(["courses", "list", "--per-page", "1"], readOnly).code).toBe(0);
    const denied = ulams(["courses", "create", "--title", "must not exist"], readOnly);
    expect(denied.code).toBe(4);
    expect(denied.out.error.code).toBe("SCOPE_MISSING");

    expect(ulams(["tokens", "revoke", id, "--yes"]).code).toBe(0);
    expect(ulams(["whoami"], readOnly).code).toBe(3);

    const out = ulams(["logout", "--revoke"]);
    expect(out.out.data).toMatchObject({ removed: true, revoked: true });
  }, 300_000);

  it("a denied approval makes the CLI exit 4", async () => {
    const child = spawn("node", [dist, "login", "--url", URL_, "--device", "--json"], { env: env() });
    let stderr = "";
    child.stderr.on("data", (c) => (stderr += String(c)));
    const finished = new Promise<number>((r) => child.on("close", (code) => r(code ?? -1)));
    await new Promise<void>((r) => {
      const tick = setInterval(() => /authorize\?code=/.test(stderr) && (clearInterval(tick), r()), 200);
    });
    const link = /(https?:\/\/\S+\/cli\/authorize\?code=\S+)/.exec(stderr)?.[1] as string;
    expect(spawnSync("node", [approve, link, URL_, "deny"], { encoding: "utf8" }).status).toBe(0);
    expect(await finished).toBe(4);
  }, 200_000);
});
