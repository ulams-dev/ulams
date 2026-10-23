import { describe, expect, it } from "vitest";
import { EntryLookupError } from "../../src/lib/entry.ts";
import { HANDOFF_HEADERS, isHandoffPath, issueCode, parseHostParam, portOf, siblingOrigin } from "../../src/lib/handoff.ts";
import { isSiblingHost } from "../../src/lib/handoff-runtime.ts";

describe("parseHostParam", () => {
  it("reads host and host:port in lower case", () => {
    expect(parseHostParam("Sales.Coffee.Example.Test")).toEqual({ name: "sales.coffee.example.test", port: "" });
    expect(parseHostParam("coffee.app.localhost:4321")).toEqual({ name: "coffee.app.localhost", port: "4321" });
    expect(parseHostParam("sales.example.test.")).toEqual({ name: "sales.example.test", port: "" });
  });

  it("refuses everything that is not a host name", () => {
    for (const bad of [null, undefined, "", "localhost", "10.0.0.1", "10.0.0.1:80", "http://evil.example", "evil.example/path", "user@evil.example", "evil.example:", "evil.example:123456", "a b.example", "//evil.example", "evil.example\\x", "[::1]", "x".repeat(300)]) {
      expect(parseHostParam(bad as string | null | undefined), String(bad)).toBeNull();
    }
  });
});

describe("siblingOrigin", () => {
  const sales = { name: "sales.coffee.example.test", port: "" };

  it("uses the protocol and the port this deployment is served on", () => {
    expect(siblingOrigin(sales, "coffee.app.localhost:4321", false)).toBe("http://sales.coffee.example.test:4321");
    expect(siblingOrigin(sales, "coffee.example.org", true)).toBe("https://sales.coffee.example.test");
  });

  it("refuses a sibling named with another port than this deployment's", () => {
    expect(siblingOrigin({ ...sales, port: "9999" }, "coffee.app.localhost:4321", false)).toBeNull();
    expect(siblingOrigin({ ...sales, port: "4321" }, "coffee.app.localhost:4321", false)).toBe("http://sales.coffee.example.test:4321");
  });

  it("reads the port from a Host header", () => {
    expect(portOf("coffee.app.localhost:4321")).toBe("4321");
    expect(portOf("coffee.example.org")).toBe("");
  });
});

describe("hand-off pages", () => {
  it("answer with a code nobody may cache or pass on", () => {
    expect(HANDOFF_HEADERS).toEqual({ "Cache-Control": "private, no-store", "Referrer-Policy": "no-referrer" });
    expect(isHandoffPath("/handoff/complete")).toBe(true);
    expect(isHandoffPath("/handoff/begin")).toBe(true);
    expect(isHandoffPath("/handoffs")).toBe(false);
    expect(isHandoffPath("/account")).toBe(false);
  });
});

describe("issueCode", () => {
  const tenant = { apiUrl: "http://coffee.localhost" };
  const code = "A".repeat(43);
  const answer = (status: number, body: unknown) => (async () => new Response(JSON.stringify(body), { status })) as unknown as typeof fetch;

  it("sends the learner's token only in the Authorization header, with the host and the challenge", async () => {
    let seen: { url: string; init: RequestInit } | null = null;
    const fetcher = (async (url: URL, init: RequestInit) => {
      seen = { url: String(url), init };
      return new Response(JSON.stringify({ data: { code } }), { status: 200 });
    }) as unknown as typeof fetch;

    expect(await issueCode(tenant, "secret-token", "sales.example.test", "C".repeat(43), fetcher)).toEqual({ ok: true, code });
    expect(seen!.url).toBe("http://coffee.localhost/api/auth/handoff");
    expect((seen!.init.headers as Record<string, string>).Authorization).toBe("Bearer secret-token");
    expect(String(seen!.init.body)).not.toContain("secret-token");
    expect(JSON.parse(String(seen!.init.body))).toEqual({ host: "sales.example.test", challenge: "C".repeat(43) });
  });

  it("never throws: a refusal, nonsense and a network error are all 'no'", async () => {
    expect(await issueCode(tenant, "t", "h.example.test", "C".repeat(43), answer(403, {}))).toEqual({ ok: false });
    expect(await issueCode(tenant, "t", "h.example.test", "C".repeat(43), answer(200, { data: { code: "short" } }))).toEqual({ ok: false });
    expect(await issueCode(tenant, "t", "h.example.test", "C".repeat(43), answer(200, { data: {} }))).toEqual({ ok: false });
    expect(await issueCode(tenant, "t", "h.example.test", "C".repeat(43), (async () => { throw new Error("down"); }) as unknown as typeof fetch)).toEqual({ ok: false });
  });
});

describe("isSiblingHost", () => {
  const found = (tenant: string) => ({ byHost: async () => ({ tenant }) as never });

  it("accepts the academy's own front by the host rules, and refuses another academy's", async () => {
    const never = { byHost: async () => { throw new Error("must not be asked for a host a rule matches"); } };
    expect(await isSiblingHost({ slug: "coffee" }, "coffee.app.localhost", never)).toBe("yes");
    expect(await isSiblingHost({ slug: "coffee" }, "nightsky.app.localhost", never)).toBe("no");
  });

  it("accepts an active entry point of the same academy only", async () => {
    expect(await isSiblingHost({ slug: "coffee" }, "sales.coffee.example.test", found("coffee"))).toBe("yes");
    expect(await isSiblingHost({ slug: "coffee" }, "sales.tea.example.test", found("tea"))).toBe("no");
    expect(await isSiblingHost({ slug: "coffee" }, "nobody.example.test", { byHost: async () => null })).toBe("no");
  });

  it("says 'unknown', not 'yes' or 'no', when the platform cannot answer", async () => {
    const down = { byHost: async () => { throw new EntryLookupError("down"); } };
    expect(await isSiblingHost({ slug: "coffee" }, "sales.coffee.example.test", down)).toBe("unknown");
  });
});
