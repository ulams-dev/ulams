import { afterEach, describe, expect, it, vi } from "vitest";
import {
  CLI_HEADERS,
  answerRequest,
  describeScope,
  isCliPath,
  lookupRequest,
  normalizeUserCode,
  parseDecision,
} from "../../src/lib/cli-authorize.ts";
import { refuseCrossSite } from "../../src/lib/bff.ts";

const tenant = { slug: "coffee", apiUrl: "http://coffee.localhost", adminUrl: "http://coffee.admin.localhost" };
const request = { requested_scopes: ["courses:write", "builder:write", "reports:read"], expiry_options_days: [7, 30, 90], default_expires_in_days: 90 };
const form = (entries: Array<[string, string]>) => {
  const f = new FormData();
  for (const [k, v] of entries) f.append(k, v);
  return f;
};

describe("normalizeUserCode", () => {
  it("accepts any case, spaces and the dash", () => {
    for (const input of ["BDWP-HQPK", "bdwp-hqpk", "bdwphqpk", " bdwp hqpk ", "BDWP--HQPK"]) expect(normalizeUserCode(input)).toBe("BDWP-HQPK");
  });
  it("rejects wrong length and characters outside the code alphabet", () => {
    for (const input of ["", null, undefined, "BDWP", "BDWP-HQPKX", "AAAA-AAAA", "BDW1-HQPK", "<script>", "BDWP-HQP!"]) expect(normalizeUserCode(input as string)).toBeNull();
  });
});

describe("describeScope", () => {
  it("explains areas and flags sensitive writes", () => {
    expect(describeScope("courses:write")).toMatchObject({ level: "write", sensitive: false, label: expect.stringContaining("read and change") });
    expect(describeScope("courses:read")).toMatchObject({ level: "read", sensitive: false, label: expect.stringContaining("read only") });
    expect(describeScope("users:write").sensitive).toBe(true);
    expect(describeScope("users:read").sensitive).toBe(false);
    expect(describeScope("*")).toMatchObject({ level: "all", sensitive: true });
    expect(describeScope("tokens:write").sensitive).toBe(true);
  });
});

describe("parseDecision", () => {
  it("keeps only requested scopes that stayed ticked", () => {
    const d = parseDecision(
      form([["decision", "approve"], ["scope", "courses:write"], ["scope", "users:write"], ["days", "30"]]),
      request
    );
    expect(d).toEqual({ decision: "approve", scopes: ["courses:write"], days: 30 });
  });
  it("falls back to the default lifetime for an unoffered value and ignores unknown decisions", () => {
    expect(parseDecision(form([["decision", "approve"], ["days", "9999"]]), request).days).toBe(90);
    expect(parseDecision(form([["decision", "maybe"]]), request).decision).toBeNull();
    expect(parseDecision(form([]), request)).toEqual({ decision: null, scopes: [], days: 90 });
  });
});

describe("response headers", () => {
  it("covers /cli paths only and forbids framing, caching and referrers", () => {
    expect(isCliPath("/cli/authorize")).toBe(true);
    expect(isCliPath("/cli")).toBe(true);
    expect(isCliPath("/client")).toBe(false);
    expect(CLI_HEADERS["X-Frame-Options"]).toBe("DENY");
    expect(CLI_HEADERS["Content-Security-Policy"]).toContain("frame-ancestors 'none'");
    // never "no-referrer": that makes browsers send `Origin: null` on the approval POST
    expect(CLI_HEADERS["Referrer-Policy"]).toBe("same-origin");
    expect(CLI_HEADERS["Cache-Control"]).toContain("no-store");
  });
  it("approval POSTs obey the exact-origin rule of the site", () => {
    const post = (headers: Record<string, string>) => new Request("https://coffee.app.ulams.app/cli/authorize", { method: "POST", headers });
    expect(refuseCrossSite(post({ origin: "https://coffee.app.ulams.app" }), "coffee.app.ulams.app", true)).toBeNull();
    for (const origin of ["https://coffee.content.ulams.app", "https://evil.example", "null", "http://coffee.app.ulams.app"]) {
      expect(refuseCrossSite(post({ origin }), "coffee.app.ulams.app", true)?.status).toBe(403);
    }
    expect(refuseCrossSite(post({}), "coffee.app.ulams.app", true)?.status).toBe(403);
  });
});

describe("API calls", () => {
  afterEach(() => vi.unstubAllGlobals());

  it("looks a request up with the user's bearer token", async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response(JSON.stringify({ data: { user_code: "BDWP-HQPK", client_name: "cli" } }), { status: 200 }));
    vi.stubGlobal("fetch", fetchMock);
    const result = await lookupRequest(tenant, "tok", "BDWP-HQPK");
    expect(result.ok).toBe(true);
    const [url, init] = fetchMock.mock.calls[0]!;
    expect(url).toBe("http://coffee.localhost/api/auth/device/requests/BDWP-HQPK");
    expect((init.headers as Record<string, string>).Authorization).toBe("Bearer tok");
  });

  it.each([
    [401, "unauthenticated"],
    [404, "unknown"],
    [429, "throttled"],
    [403, "forbidden"],
    [500, "unavailable"],
  ])("maps HTTP %s to %s", async (status, reason) => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response("{}", { status })));
    expect(await lookupRequest(tenant, "t", "BDWP-HQPK")).toEqual({ ok: false, reason });
  });

  it("reports a network failure as unavailable", async () => {
    vi.stubGlobal("fetch", vi.fn().mockRejectedValue(new Error("down")));
    expect(await lookupRequest(tenant, "t", "BDWP-HQPK")).toEqual({ ok: false, reason: "unavailable" });
  });

  it("posts the approved scopes and lifetime, and nothing for a denial", async () => {
    const fetchMock = vi.fn().mockResolvedValue(new Response("{}", { status: 200 }));
    vi.stubGlobal("fetch", fetchMock);
    await answerRequest(tenant, "tok", "BDWP-HQPK", "approve", ["courses:write"], 30);
    await answerRequest(tenant, "tok", "BDWP-HQPK", "deny", [], 30);
    expect(fetchMock.mock.calls[0]![0]).toBe("http://coffee.localhost/api/auth/device/requests/BDWP-HQPK/approve");
    expect(JSON.parse(fetchMock.mock.calls[0]![1].body)).toEqual({ scopes: ["courses:write"], expires_in_days: 30 });
    expect(fetchMock.mock.calls[1]![0]).toMatch(/\/deny$/);
    expect(JSON.parse(fetchMock.mock.calls[1]![1].body)).toEqual({});
  });

  it("passes the API's message on a refused approval", async () => {
    vi.stubGlobal("fetch", vi.fn().mockResolvedValue(new Response(JSON.stringify({ message: "None of the approved scopes was requested by the client." }), { status: 422 })));
    expect(await answerRequest(tenant, "t", "BDWP-HQPK", "approve", ["users:read"], 90)).toMatchObject({ ok: false, message: expect.stringContaining("requested") });
  });
});
