import { afterEach, describe, expect, it, vi } from "vitest";
import {
  DEFAULT_EXPIRY_DAYS,
  PRESETS,
  createToken,
  formatDate,
  isExpired,
  listTokens,
  parseCreateForm,
  revokeToken,
  sortTokens,
  summariseScopes,
  type TokenRow,
} from "../../src/lib/tokens.ts";

const tenant = { slug: "coffee", apiUrl: "http://coffee.localhost", adminUrl: "http://coffee.admin.localhost" };
const form = (entries: Array<[string, string]>) => {
  const f = new FormData();
  for (const [k, v] of entries) f.append(k, v);
  return f;
};
const row = (over: Partial<TokenRow> = {}): TokenRow => ({
  id: "t1",
  name: "laptop",
  scopes: ["courses:write"],
  kind: "cli",
  agent_name: null,
  created_via: "admin",
  created_at: "2026-10-01T10:00:00+00:00",
  expires_at: "2027-01-01T10:00:00+00:00",
  last_used_at: null,
  revoked: false,
  ...over,
});

afterEach(() => vi.unstubAllGlobals());

describe("parseCreateForm", () => {
  it("builds the API input from the form: a preset, a lifetime and a kind", () => {
    expect(parseCreateForm(form([["name", "  nightly   build "], ["access", "ci"], ["days", "30"], ["kind", "ci"]]))).toEqual({
      ok: true,
      input: { name: "nightly build", scopes: ["@ci"], expires_in_days: 30, kind: "ci" },
    });
  });

  it("falls back to the default lifetime and kind for values that are not offered", () => {
    const r = parseCreateForm(form([["name", "x"], ["access", "read-only"], ["days", "5000"], ["kind", "root"]]));
    expect(r).toEqual({ ok: true, input: { name: "x", scopes: ["@read-only"], expires_in_days: DEFAULT_EXPIRY_DAYS, kind: "cli" } });
  });

  it("refuses a missing name, an unknown access level, markup and long names", () => {
    expect(parseCreateForm(form([["access", "ci"]]))).toMatchObject({ ok: false, error: expect.stringContaining("name") });
    expect(parseCreateForm(form([["name", "a"], ["access", "root"]]))).toMatchObject({ ok: false, error: expect.stringContaining("what the token may do") });
    expect(parseCreateForm(form([["name", "<b>x</b>"], ["access", "ci"]]))).toMatchObject({ ok: false });
    expect(parseCreateForm(form([["name", "x".repeat(101)], ["access", "ci"]]))).toMatchObject({ ok: false });
  });

  it("only offers presets the API knows", () => {
    expect(PRESETS.map((p) => p.scope)).toEqual(["@read-only", "@author", "@ci", "@learner", "@admin"]);
  });
});

describe("display helpers", () => {
  it("summarises scopes: writes first, a read that a write covers is not repeated, * means everything", () => {
    expect(summariseScopes(["*"])).toBe("Everything your account can do");
    expect(summariseScopes(["courses:write", "courses:read", "reports:read"])).toBe("Change: courses · Read: reports");
    expect(summariseScopes([])).toBe("No scopes");
  });

  it("formats dates and handles missing ones", () => {
    expect(formatDate(null)).toBe("never");
    expect(formatDate("not a date")).toBe("unknown");
    expect(formatDate("2026-10-09T12:00:00Z")).toMatch(/2026/);
  });

  it("puts active tokens first, newest first, and knows expiry", () => {
    const sorted = sortTokens([row({ id: "old", created_at: "2026-01-01T00:00:00Z" }), row({ id: "gone", revoked: true, created_at: "2026-12-01T00:00:00Z" }), row({ id: "new", created_at: "2026-06-01T00:00:00Z" })]);
    expect(sorted.map((t) => t.id)).toEqual(["new", "old", "gone"]);
    expect(isExpired(row({ expires_at: "2020-01-01T00:00:00Z" }))).toBe(true);
    expect(isExpired(row({ expires_at: null }))).toBe(false);
  });
});

describe("API calls", () => {
  it("lists with the user's bearer token", async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: [row()] }), { status: 200 }));
    vi.stubGlobal("fetch", fetchMock);
    expect(await listTokens(tenant, "tok")).toEqual({ ok: true, tokens: [row()] });
    expect(fetchMock.mock.calls[0]![0]).toBe("http://coffee.localhost/api/auth/tokens");
    expect((fetchMock.mock.calls[0]![1].headers as Record<string, string>).Authorization).toBe("Bearer tok");
  });

  it.each([
    [401, "unauthenticated"],
    [403, "forbidden"],
    [500, "unavailable"],
  ])("maps a list failure %s to %s", async (status, reason) => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response("{}", { status })));
    expect(await listTokens(tenant, "t")).toEqual({ ok: false, reason });
  });

  it("creates a token and returns the secret once", async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: { ...row(), token: "ulams_pat_secret" } }), { status: 201 }));
    vi.stubGlobal("fetch", fetchMock);
    const result = await createToken(tenant, "tok", { name: "laptop", scopes: ["@author"], expires_in_days: 30, kind: "cli" });
    expect(result).toMatchObject({ ok: true, token: { token: "ulams_pat_secret" } });
    expect(JSON.parse(fetchMock.mock.calls[0]![1].body)).toEqual({ name: "laptop", scopes: ["@author"], expires_in_days: 30, kind: "cli" });
  });

  it("passes the API's reason for a refused token and maps throttling", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response(JSON.stringify({ message: "A token cannot grant scopes its own token lacks." }), { status: 422 })));
    expect(await createToken(tenant, "t", { name: "x", scopes: ["@admin"], expires_in_days: 7, kind: "cli" })).toEqual({ ok: false, reason: "invalid", message: "A token cannot grant scopes its own token lacks." });
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response("{}", { status: 429 })));
    expect(await createToken(tenant, "t", { name: "x", scopes: ["@ci"], expires_in_days: 7, kind: "cli" })).toMatchObject({ ok: false, reason: "throttled" });
  });

  it("revokes by id and never sends a malformed id", async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response("{}", { status: 200 }));
    vi.stubGlobal("fetch", fetchMock);
    expect(await revokeToken(tenant, "tok", "abc123")).toEqual({ ok: true });
    expect(fetchMock.mock.calls[0]![0]).toBe("http://coffee.localhost/api/auth/tokens/abc123");
    expect(fetchMock.mock.calls[0]![1].method).toBe("DELETE");
    expect(await revokeToken(tenant, "tok", "../admin")).toEqual({ ok: false, reason: "invalid" });
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it("reports a network failure as unavailable", async () => {
    vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new Error("down")));
    expect(await revokeToken(tenant, "t", "abc")).toEqual({ ok: false, reason: "unavailable" });
    expect(await createToken(tenant, "t", { name: "x", scopes: ["@ci"], expires_in_days: 7, kind: "cli" })).toEqual({ ok: false, reason: "unavailable" });
  });
});
