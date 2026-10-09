import { describe, expect, it } from "vitest";
import { existsSync, readFileSync, statSync } from "node:fs";
import { join } from "node:path";
import { LOGIN, ME, runCli } from "../helpers.ts";

const URL_ = "http://coffee.localhost";
const routes = {
  "POST /api/demo/login": { body: LOGIN },
  "POST /api/auth/login": { body: LOGIN },
  "GET /api/profile/me": { body: ME },
};

describe("login", () => {
  it("demo login saves a 0600 credentials file and never prints the token", async () => {
    const r = await runCli(["login", "--url", URL_, "--demo", "admin", "--json"], { routes });
    expect(r.code).toBe(0);
    const out = r.json();
    expect(out).toMatchObject({ ok: true, contract: 1, command: "login", data: { profile: "coffee", url: URL_, user: "admin@coffee.ulams.app" } });
    expect(r.stdout + r.stderr).not.toContain("secret");
    const cred = join(r.configDir, "credentials.json");
    expect(existsSync(cred)).toBe(true);
    expect(statSync(cred).mode & 0o777).toBe(0o600);
    expect(JSON.parse(readFileSync(cred, "utf8")).coffee.token).toBe(LOGIN.data.token);
    r.cleanup();
  });

  it("password login warns that the token is unscoped", async () => {
    const r = await runCli(["login", "--url", URL_, "--email", "a@b.c", "--password-stdin", "--json"], { routes, stdin: "pw\n" });
    expect(r.code).toBe(0);
    expect(r.requests[0]?.body).toEqual({ email: "a@b.c", password: "pw", remember_me: 1 });
    expect((r.json().warnings as Array<{ code: string }>)[0]?.code).toBe("UNSCOPED_TOKEN");
    r.cleanup();
  });

  it("token login verifies the token with /api/profile/me", async () => {
    const r = await runCli(["login", "--url", URL_, "--token-stdin", "--json"], { routes, stdin: "pasted-token-value\n" });
    expect(r.code).toBe(0);
    expect(r.requests[0]?.headers.authorization).toBe("Bearer pasted-token-value");
    r.cleanup();
  });

  it("a rejected token exits 3 and is not saved", async () => {
    const r = await runCli(["login", "--url", URL_, "--token-stdin", "--json"], {
      routes: { "GET /api/profile/me": { status: 401, body: { message: "Unauthenticated." } } },
      stdin: "bad\n",
    });
    expect(r.code).toBe(3);
    expect(existsSync(join(r.configDir, "credentials.json"))).toBe(false);
    r.cleanup();
  });

  it("--device reports that it is not available on this server", async () => {
    const r = await runCli(["login", "--url", URL_, "--device", "--json"], { routes: { "GET /api/meta": { status: 404, body: { message: "Not found" } } } });
    expect(r.code).toBe(12);
    expect((r.json().error as { code: string; message: string }).message).toContain("not available on this server");
    r.cleanup();
  });

  it("without a method it lists the choices (exit 2)", async () => {
    const r = await runCli(["login", "--url", URL_, "--json"], { routes: { "GET /api/meta": { status: 404, body: {} } } });
    expect(r.code).toBe(2);
    r.cleanup();
  });
});

describe("whoami and sessions", () => {
  it("whoami uses the saved profile", async () => {
    const first = await runCli(["login", "--url", URL_, "--demo", "admin", "--json"], { routes });
    const r = await runCli(["whoami", "--json"], { routes, configDir: first.configDir });
    expect(r.code).toBe(0);
    expect(r.json()).toMatchObject({ ok: true, data: { user: { email: "admin@coffee.ulams.app" }, instance: URL_, auth: { source: "profile" } } });
    expect(r.requests[0]?.headers["x-ulams-client"]).toBe("cli");
    expect(r.requests[0]?.headers["x-request-id"]).toMatch(/^[0-9A-Z]{26}$/);
    expect(r.stdout).not.toContain("eyJ");
    first.cleanup();
  });

  it("works with ULAMS_URL and ULAMS_TOKEN only (no files)", async () => {
    const r = await runCli(["whoami", "--json"], { routes, env: { ULAMS_URL: URL_, ULAMS_TOKEN: "env-token" } });
    expect(r.code).toBe(0);
    expect(r.json()).toMatchObject({ data: { auth: { source: "env" } } });
    r.cleanup();
  });

  it("exits 3 without credentials", async () => {
    const r = await runCli(["whoami", "--json"], { routes });
    expect(r.code).toBe(3);
    expect(r.json()).toMatchObject({ ok: false, error: { code: "AUTH_REQUIRED" } });
    r.cleanup();
  });

  it("never sends a profile token to a different --url", async () => {
    const first = await runCli(["login", "--url", URL_, "--demo", "admin", "--json"], { routes });
    const r = await runCli(["whoami", "--url", "http://other.localhost", "--json"], { routes, configDir: first.configDir });
    expect(r.code).toBe(3);
    expect(r.requests).toHaveLength(0);
    first.cleanup();
  });

  it("logout removes the credentials", async () => {
    const first = await runCli(["login", "--url", URL_, "--demo", "admin", "--json"], { routes });
    const out = await runCli(["logout", "--json"], { routes, configDir: first.configDir });
    expect(out.code).toBe(0);
    const after = await runCli(["whoami", "--json"], { routes, configDir: first.configDir });
    expect(after.code).toBe(3);
    first.cleanup();
  });

  it("profiles list/use/delete", async () => {
    const a = await runCli(["login", "--url", URL_, "--demo", "admin", "--json"], { routes });
    await runCli(["login", "--url", "http://oncall.localhost", "--demo", "admin", "--json", "--no-make-default"], { routes, configDir: a.configDir });
    const list = await runCli(["profiles", "list", "--json"], { routes, configDir: a.configDir });
    expect((list.json().data as Array<{ name: string; default: boolean }>).map((p) => [p.name, p.default])).toEqual([
      ["coffee", true],
      ["oncall", false],
    ]);
    expect((await runCli(["profiles", "use", "oncall", "--json"], { routes, configDir: a.configDir })).code).toBe(0);
    expect((await runCli(["profiles", "delete", "nope", "--json"], { routes, configDir: a.configDir })).code).toBe(5);
    a.cleanup();
  });
});

describe("error mapping and redaction", () => {
  const cases: Array<[number, unknown, number, string]> = [
    [401, { message: "Unauthenticated." }, 3, "AUTH_EXPIRED"],
    [403, { message: "nope" }, 4, "FORBIDDEN"],
    [403, { message: "no scope", error: "scope_missing", required: ["courses:write"] }, 4, "SCOPE_MISSING"],
    [404, { message: "gone" }, 5, "NOT_FOUND"],
    [409, { message: "dup" }, 6, "CONFLICT"],
    [422, { message: "bad", errors: { title: ["required"] } }, 7, "VALIDATION_FAILED"],
  ];
  for (const [status, body, exit, code] of cases) {
    it(`${status} -> exit ${exit} ${code}`, async () => {
      const r = await runCli(["api", "GET", "/api/x", "--json"], { routes: { "GET /api/x": { status, body } }, env: { ULAMS_URL: URL_, ULAMS_TOKEN: "tok-123456" } });
      expect(r.code).toBe(exit);
      expect(r.json()).toMatchObject({ ok: false, error: { code, status } });
      r.cleanup();
    });
  }

  it("422 carries the field errors in details", async () => {
    const r = await runCli(["api", "GET", "/api/x", "--json"], {
      routes: { "GET /api/x": { status: 422, body: { message: "bad", errors: { title: ["required"] } } } },
      env: { ULAMS_URL: URL_, ULAMS_TOKEN: "tok-123456" },
    });
    expect((r.json().error as { details: unknown }).details).toEqual({ fields: { title: ["required"] } });
  });

  it("429 retries GET then exits 8", async () => {
    let n = 0;
    const r = await runCli(["api", "GET", "/api/x", "--json"], {
      routes: { "GET /api/x": () => (n++, { status: 429, body: { message: "slow" }, headers: { "retry-after": "0" } }) },
      env: { ULAMS_URL: URL_, ULAMS_TOKEN: "tok-123456" },
    });
    expect(r.code).toBe(8);
    expect(n).toBe(4);
  });

  it("does not retry a POST without an idempotency key", async () => {
    let n = 0;
    const r = await runCli(["api", "POST", "/api/x", "--json"], {
      routes: { "POST /api/x": () => (n++, { status: 503, body: { message: "down" } }) },
      env: { ULAMS_URL: URL_, ULAMS_TOKEN: "tok-123456" },
    });
    expect(r.code).toBe(9);
    expect(n).toBe(1);
  });

  it("an unreachable instance exits 9 NETWORK", async () => {
    const r = await runCli(["api", "GET", "/api/x", "--json"], {
      routes: { "GET /api/x": () => { throw new TypeError("fetch failed"); } },
      env: { ULAMS_URL: URL_, ULAMS_TOKEN: "tok-123456" },
    });
    expect(r.code).toBe(9);
    expect(r.json()).toMatchObject({ error: { code: "NETWORK", retryable: true } });
  });

  it("a token echoed in an error body is redacted", async () => {
    const r = await runCli(["api", "GET", "/api/x", "--json"], {
      routes: { "GET /api/x": { status: 500, body: { message: "boom tok-123456 and Bearer abcdefghijkl" } } },
      env: { ULAMS_URL: URL_, ULAMS_TOKEN: "tok-123456" },
    });
    expect(r.stdout).not.toContain("tok-123456");
    expect(r.stdout).not.toContain("abcdefghijkl");
  });
});
