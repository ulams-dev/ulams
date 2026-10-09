import { describe, expect, it } from "vitest";
import { buildCsp, contentOriginFor, cspHeaders, originOf, parseEnforce, reportUrl, wantsCsp } from "../../src/lib/csp.ts";

const tenant = { slug: "coffee", apiUrl: "http://coffee.localhost", adminUrl: "http://coffee.admin.localhost" };
const input = { tenant, toolOrigins: [] as string[], contentOrigin: "http://coffee.content.localhost", storageOrigins: ["http://storage.localhost"] };

const directive = (policy: string, name: string) => policy.split("; ").find((d) => d.startsWith(`${name} `))?.slice(name.length + 1).split(" ") ?? [];

describe("front CSP (ADR 0044)", () => {
  it("frames the content origin, uploads and embed providers, plus the tenant's registered tools", () => {
    const policy = buildCsp({ ...input, toolOrigins: ["https://www.geogebra.org", "https://deep.geogebra.org:8443"] });
    const frames = directive(policy, "frame-src");
    expect(frames).toEqual(expect.arrayContaining(["'self'", "http://coffee.content.localhost", "http://storage.localhost", "https://www.youtube-nocookie.com", "https://player.vimeo.com", "https://www.geogebra.org", "https://deep.geogebra.org:8443"]));
    expect(frames).not.toContain("https:");
  });

  it("keeps scripts, connections, objects and base to the page and the tenant API", () => {
    const policy = buildCsp(input);
    expect(directive(policy, "default-src")).toEqual(["'self'"]);
    expect(directive(policy, "connect-src")).toEqual(["'self'", "http://coffee.localhost"]);
    expect(directive(policy, "script-src")).toEqual(["'self'", "'unsafe-inline'", "'unsafe-eval'"]);
    expect(directive(policy, "object-src")).toEqual(["'none'"]);
    expect(directive(policy, "base-uri")).toEqual(["'self'"]);
    expect(directive(policy, "frame-ancestors")).toEqual(["'self'"]);
    expect(directive(policy, "img-src")).toEqual(expect.arrayContaining(["'self'", "data:", "blob:", "http://coffee.localhost", "http://storage.localhost", "https:"]));
    expect(directive(policy, "media-src")).not.toContain("data:");
  });

  it("reports to the tenant's own API through both mechanisms", () => {
    const policy = buildCsp(input);
    expect(reportUrl(tenant)).toBe("http://coffee.localhost/api/csp-report");
    expect(directive(policy, "report-uri")).toEqual(["http://coffee.localhost/api/csp-report"]);
    expect(directive(policy, "report-to")).toEqual(["csp-endpoint"]);
    expect(cspHeaders(input, false)["Reporting-Endpoints"]).toBe('csp-endpoint="http://coffee.localhost/api/csp-report"');
  });

  it("sends the policy enforced or report-only, never both", () => {
    expect(Object.keys(cspHeaders(input, true))).toContain("Content-Security-Policy");
    expect(Object.keys(cspHeaders(input, true))).not.toContain("Content-Security-Policy-Report-Only");
    expect(Object.keys(cspHeaders(input, false))).toContain("Content-Security-Policy-Report-Only");
    expect(Object.keys(cspHeaders(input, false))).not.toContain("Content-Security-Policy");
  });

  it("drops origins that are not plain http(s) origins instead of letting them read as policy syntax", () => {
    const policy = buildCsp({
      ...input,
      toolOrigins: ["https://ok.example.test", "javascript:alert(1)", "https://evil.example.test; script-src *", "ftp://x.test", "not a url", "https://a b.test"],
      storageOrigins: ["http://storage.localhost", "https://x.test'; script-src *"],
    });
    expect(directive(policy, "frame-src")).not.toEqual(expect.arrayContaining(["javascript:alert(1)"]));
    expect(policy).not.toContain("evil.example.test");
    expect(policy).not.toContain("script-src *");
    expect(policy).not.toContain("ftp:");
    expect(directive(policy, "frame-src")).toContain("https://ok.example.test");
    expect(directive(policy, "script-src")).toEqual(["'self'", "'unsafe-inline'", "'unsafe-eval'"]);
  });

  it("has no tool or content sources and no reporting on the platform host or without a content origin", () => {
    const platform = buildCsp({ tenant: null, toolOrigins: [], contentOrigin: null, storageOrigins: [] });
    expect(platform).not.toContain("report-uri");
    expect(directive(platform, "connect-src")).toEqual(["'self'"]);
    expect(directive(platform, "frame-src")).not.toContain("null");
    expect(cspHeaders({ tenant: null, toolOrigins: [], contentOrigin: null, storageOrigins: [] }, true)["Reporting-Endpoints"]).toBeUndefined();
  });

  it("derives origins and the content origin of a tenant", () => {
    expect(originOf("http://coffee.localhost/api/x?y")).toBe("http://coffee.localhost");
    expect(originOf("https://tool.example.test:8443/a")).toBe("https://tool.example.test:8443");
    expect(originOf("javascript:alert(1)")).toBeNull();
    expect(originOf(undefined)).toBeNull();
    expect(contentOriginFor("http://{slug}.content.localhost", "oncall")).toBe("http://oncall.content.localhost");
    expect(contentOriginFor("", "oncall")).toBeNull();
  });

  it("sets the policy on HTML pages of the front only", () => {
    expect(wantsCsp("/learn/1/2", null)).toBe(true);
    expect(wantsCsp("/learn/1/2", "text/html; charset=utf-8")).toBe(true);
    expect(wantsCsp("/bff/api/x", "application/json")).toBe(false);
    expect(wantsCsp("/h5p/embed/play/1", "text/html")).toBe(false);
    expect(wantsCsp("/h5p", null)).toBe(false);
    expect(wantsCsp("/h5pfoo", "text/html")).toBe(true);
  });

  it("enforces by default outside production, and `CSP_ENFORCE` overrides", () => {
    expect(parseEnforce(undefined, true)).toBe(true);
    expect(parseEnforce("", false)).toBe(false);
    expect(parseEnforce("true", false)).toBe(true);
    expect(parseEnforce("1", false)).toBe(true);
    expect(parseEnforce("false", true)).toBe(false);
    expect(parseEnforce("off", true)).toBe(false);
  });
});

describe("tool origins from the API", () => {
  const answer = (status: number, body: unknown) => (async () => new Response(JSON.stringify(body), { status })) as unknown as typeof fetch;

  it("reads the origins and ignores anything that is not a string", async () => {
    const { fetchFrameOrigins } = await import("../../src/lib/frame-origins.ts");
    expect(await fetchFrameOrigins("http://coffee.localhost/", answer(200, { data: ["https://a.test", 5, null, "https://b.test"] }))).toEqual(["https://a.test", "https://b.test"]);
    expect(await fetchFrameOrigins("http://coffee.localhost", answer(200, { data: "nope" }))).toEqual([]);
  });

  it("throws on a failed request so the cache keeps its last good answer", async () => {
    const { fetchFrameOrigins } = await import("../../src/lib/frame-origins.ts");
    await expect(fetchFrameOrigins("http://coffee.localhost", answer(500, {}))).rejects.toThrow("500");
  });

  it("is cached for five minutes by the SWR cache and serves the old value when a refresh fails", async () => {
    const { SwrCache } = await import("../../src/lib/cache.ts");
    const { fetchFrameOrigins } = await import("../../src/lib/frame-origins.ts");
    let now = 0;
    const cache = new SwrCache({ now: () => now });
    let calls = 0;
    let fail = false;
    const fetcher = (async () => {
      calls++;
      return fail ? new Response("{}", { status: 503 }) : new Response(JSON.stringify({ data: ["https://t.test"] }), { status: 200 });
    }) as unknown as typeof fetch;
    const read = () => cache.get("coffee|lti:frame-origins", () => fetchFrameOrigins("http://coffee.localhost", fetcher), { ttlMs: 300_000 });

    expect(await read()).toEqual(["https://t.test"]);
    now = 299_000;
    expect(await read()).toEqual(["https://t.test"]);
    expect(calls).toBe(1);
    now = 301_000;
    fail = true;
    expect(await read()).toEqual(["https://t.test"]); // stale, refreshed in the background (fails)
    await new Promise((resolve) => setTimeout(resolve, 10));
    expect(calls).toBe(2);
    expect(await read()).toEqual(["https://t.test"]);
  });
});
