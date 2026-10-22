import { describe, expect, it, vi } from "vitest";
import { exchangeSsoCode, isSsoSlug, listProviders, ssoStartUrl } from "../../src/lib/sso-login.ts";
import { isSocialPath } from "../../src/lib/social-login.ts";

const tenant = { apiUrl: "http://coffee.localhost" };
const reply = (status: number, body: unknown) => vi.fn(async () => new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json" } })) as unknown as typeof fetch;

describe("isSsoSlug", () => {
  it("accepts what the API accepts as a slug", () => {
    for (const slug of ["a", "acme", "acme-keycloak", "a1-b2", "0abc"]) expect(isSsoSlug(slug)).toBe(true);
  });
  it("refuses anything that could change the path or the host", () => {
    for (const slug of [undefined, "", "-a", "a-", "A", "a_b", "a/b", "a b", "../x", "a%2fb", "a".repeat(65)]) expect(isSsoSlug(slug)).toBe(false);
  });
});

describe("listProviders", () => {
  it("returns the enabled providers with a slug and a name", async () => {
    const fetcher = reply(200, { data: [{ slug: "acme", name: " Acme staff ", protocol: "oidc" }, { slug: "other", name: "Other", protocol: "oidc" }] });
    expect(await listProviders(tenant, fetcher)).toEqual([
      { slug: "acme", name: "Acme staff" },
      { slug: "other", name: "Other" },
    ]);
    const [url] = (fetcher as unknown as ReturnType<typeof vi.fn>).mock.calls[0] as [URL];
    expect(String(url)).toBe("http://coffee.localhost/api/identity/providers");
  });

  it("drops rows that are not what the API sends: other protocols, bad slugs, empty names", async () => {
    const rows = [
      { slug: "saml", name: "SAML", protocol: "saml" },
      { slug: "Bad Slug", name: "Bad", protocol: "oidc" },
      { slug: "ok", name: "  ", protocol: "oidc" },
      { slug: 5, name: "Num", protocol: "oidc" },
      null,
      { slug: "fine", name: "Fine", protocol: "oidc" },
    ];
    expect(await listProviders(tenant, reply(200, { data: rows }))).toEqual([{ slug: "fine", name: "Fine" }]);
  });

  it("keeps at most ten and cuts long names", async () => {
    const rows = Array.from({ length: 15 }, (_, i) => ({ slug: `p${i}`, name: "N".repeat(300), protocol: "oidc" }));
    const result = await listProviders(tenant, reply(200, { data: rows }));
    expect(result).toHaveLength(10);
    expect(result[0]!.name).toHaveLength(120);
  });

  it("shows no buttons when the API is down, slow, or answers something else", async () => {
    expect(await listProviders(tenant, reply(500, {}))).toEqual([]);
    expect(await listProviders(tenant, reply(200, { data: "nope" }))).toEqual([]);
    expect(await listProviders(tenant, reply(200, {}))).toEqual([]);
    expect(await listProviders(tenant, (async () => { throw new Error("down"); }) as unknown as typeof fetch)).toEqual([]);
    expect(await listProviders(tenant, (async () => new Response("<html>", { status: 200 })) as unknown as typeof fetch)).toEqual([]);
  });
});

describe("ssoStartUrl", () => {
  it("points at the tenant API with the return URL and only the challenge", () => {
    const url = new URL(ssoStartUrl(tenant, "acme", "http://coffee.app.localhost:4321/auth/sso/callback", "C".repeat(43)));
    expect(url.origin).toBe("http://coffee.localhost");
    expect(url.pathname).toBe("/api/identity/sso/acme/start");
    expect(url.searchParams.get("return_to")).toBe("http://coffee.app.localhost:4321/auth/sso/callback");
    expect(url.searchParams.get("challenge")).toBe("C".repeat(43));
    expect([...url.searchParams.keys()].sort()).toEqual(["challenge", "return_to"]);
  });
});

describe("exchangeSsoCode", () => {
  it("posts the code and the verifier to the identity exchange endpoint", async () => {
    const fetcher = reply(200, { success: true, data: { token: "T", expires_at: "2030-01-01T00:00:00Z" } });
    const result = await exchangeSsoCode(tenant, "CODE", "VERIFIER", fetcher);

    expect(result).toEqual({ ok: true, complete: true, token: "T", expiresAt: Date.parse("2030-01-01T00:00:00Z") });
    const [url, init] = (fetcher as unknown as ReturnType<typeof vi.fn>).mock.calls[0] as unknown as [URL, RequestInit];
    expect(String(url)).toBe("http://coffee.localhost/api/identity/sso/exchange");
    expect(JSON.parse(String(init.body))).toEqual({ code: "CODE", verifier: "VERIFIER" });
  });

  it("reports a refusal as a plain failure", async () => {
    expect(await exchangeSsoCode(tenant, "C", "V", reply(400, { message: "invalid" }))).toEqual({ ok: false });
  });
});

describe("headers", () => {
  it("treats the single sign-on pages like the social ones: never cached, no referrer", () => {
    expect(isSocialPath("/auth/sso/callback")).toBe(true);
    expect(isSocialPath("/auth/sso/acme")).toBe(true);
    expect(isSocialPath("/login")).toBe(false);
  });
});
