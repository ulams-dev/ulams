import { describe, expect, it } from "vitest";
import { SwrCache } from "../../src/lib/cache.ts";
import { hostForTenant, isPlatformHost, tenantForHost, tenantFrontUrl } from "../../src/lib/tenant.ts";
import { isSameOrigin, matchBffRule } from "../../src/lib/bff.ts";

const cfg = { tenantHosts: "{slug}.app.localhost=>http://{slug}.localhost", adminUrl: "http://{slug}.admin.localhost", defaultTenant: "coffee" };

describe("tenantForHost", () => {
  it("resolves <slug>.app.localhost with the dev port", () => {
    expect(tenantForHost("oncall.app.localhost:4321", cfg)).toEqual({
      slug: "oncall",
      apiUrl: "http://oncall.localhost",
      adminUrl: "http://oncall.admin.localhost",
    });
  });
  it("uses the default tenant on plain localhost, none when disabled", () => {
    expect(tenantForHost("localhost:4321", cfg)?.slug).toBe("coffee");
    expect(tenantForHost("localhost:4321", { ...cfg, defaultTenant: "" })).toBeNull();
  });
  it("builds sibling tenant hosts for the demo switcher", () => {
    expect(hostForTenant("coffee.app.localhost:4321", "coffee", "nightsky")).toBe("nightsky.app.localhost:4321");
    expect(hostForTenant("localhost:4321", "coffee", "nightsky")).toBeNull();
  });
});

describe("platform host", () => {
  const platform = ["app.localhost", "localhost", "127.0.0.1"];
  it("recognises the platform hosts with or without a port", () => {
    expect(isPlatformHost("app.localhost", platform)).toBe(true);
    expect(isPlatformHost("localhost:4321", platform)).toBe(true);
    expect(isPlatformHost("coffee.app.localhost:4321", platform)).toBe(false);
    expect(isPlatformHost(undefined, platform)).toBe(false);
  });
  it("builds tenant front URLs on the current protocol and port", () => {
    expect(tenantFrontUrl("oncall", cfg.tenantHosts, new URL("http://localhost:4321/"))).toBe("http://oncall.app.localhost:4321");
    expect(tenantFrontUrl("oncall", cfg.tenantHosts, new URL("http://app.localhost/"))).toBe("http://oncall.app.localhost");
  });
});

describe("SwrCache", () => {
  it("serves fresh, then stale while revalidating, and dedupes requests", async () => {
    let now = 0;
    let calls = 0;
    const cache = new SwrCache({ now: () => now });
    const fetcher = async () => ++calls;
    const opts = { ttlMs: 1000, maxStaleMs: 5000 };
    const [a, b] = await Promise.all([cache.get("k", fetcher, opts), cache.get("k", fetcher, opts)]);
    expect([a, b, calls]).toEqual([1, 1, 1]);
    now = 500;
    expect(await cache.get("k", fetcher, opts)).toBe(1);
    now = 2000; // stale: old value now, refresh in background
    expect(await cache.get("k", fetcher, opts)).toBe(1);
    await new Promise((r) => setTimeout(r, 0));
    expect(await cache.get("k", fetcher, opts)).toBe(2);
    now = 100_000; // too old: waits for a fresh value
    expect(await cache.get("k", fetcher, opts)).toBe(3);
  });

  it("keeps serving the stale value when a refresh fails", async () => {
    let now = 0;
    const cache = new SwrCache({ now: () => now });
    await cache.get("k", async () => "ok", { ttlMs: 10 });
    now = 20;
    expect(await cache.get("k", async () => Promise.reject(new Error("down")), { ttlMs: 10 })).toBe("ok");
  });
});

describe("BFF rules", () => {
  it("allows only the learner calls the islands need", () => {
    expect(matchBffRule("PUT", "/api/courses/progress/5/ping")).not.toBeNull();
    expect(matchBffRule("PATCH", "/api/courses/progress/1")?.writesProgress).toBe(true);
    expect(matchBffRule("POST", "/api/quiz-answers")).not.toBeNull();
    expect(matchBffRule("GET", "/api/admin/users")).toBeNull();
    expect(matchBffRule("DELETE", "/api/courses/progress/1")).toBeNull();
    expect(matchBffRule("GET", "/api/courses/progress/1/../../admin")).toBeNull();
  });
  it("forwards the learner notice routes and nothing else of the Living Course", () => {
    expect(matchBffRule("GET", "/api/living-course/courses/5/notices")).not.toBeNull();
    expect(matchBffRule("POST", "/api/living-course/notices/12/dismiss")).not.toBeNull();
    expect(matchBffRule("GET", "/api/living-course/courses/5/freshness")).not.toBeNull();
    expect(matchBffRule("GET", "/api/living-course/notices/12/dismiss")).toBeNull();
    expect(matchBffRule("POST", "/api/living-course/courses/5/notices")).toBeNull();
    expect(matchBffRule("DELETE", "/api/living-course/notices/12/dismiss")).toBeNull();
    expect(matchBffRule("GET", "/api/living-course/courses/x/notices")).toBeNull();
    expect(matchBffRule("GET", "/api/living-course/courses/5/notices/../../admin")).toBeNull();
    // author routes are only reachable through the studio BFF
    expect(matchBffRule("GET", "/api/admin/living-course/sessions/01hzzzzzzzzzzzzzzzzzzzzzzz/audit")).toBeNull();
  });
  it("refuses cross-site writes", () => {
    const req = (headers: Record<string, string>, method = "POST") => new Request("http://coffee.app.localhost:4321/bff/x", { method, headers });
    expect(isSameOrigin(req({ origin: "http://coffee.app.localhost:4321" }), "http://coffee.app.localhost:4321")).toBe(true);
    expect(isSameOrigin(req({ origin: "http://evil.example" }), "http://coffee.app.localhost:4321")).toBe(false);
    expect(isSameOrigin(req({ "sec-fetch-site": "cross-site" }), "http://coffee.app.localhost:4321")).toBe(false);
    expect(isSameOrigin(req({}, "GET"), "http://coffee.app.localhost:4321")).toBe(true);
  });
});
