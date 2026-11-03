import { describe, expect, it, vi } from "vitest";
import {
  SOCIAL_HEADERS,
  challengeFor,
  decodeCookie,
  enabledProviders,
  encodeCookie,
  errorMessage,
  exchangeCode,
  isSocialPath,
  newVerifier,
  safeNext,
  startUrl,
} from "../../src/lib/social-login.ts";

const tenant = { apiUrl: "http://coffee.localhost" };

describe("verifier and challenge", () => {
  it("makes a 43 character base64url verifier and the S256 challenge of RFC 7636", () => {
    expect(newVerifier()).toMatch(/^[A-Za-z0-9_-]{43}$/);
    expect(newVerifier()).not.toBe(newVerifier());
    // RFC 7636 appendix B
    expect(challengeFor("dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk")).toBe("E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM");
    expect(challengeFor(newVerifier())).toMatch(/^[A-Za-z0-9_-]{43}$/);
  });
});

describe("enabled providers", () => {
  it("keeps known providers once, lower-cased, and drops the rest", () => {
    expect(enabledProviders("google, Facebook,google,twitter,,")).toEqual(["google", "facebook"]);
    expect(enabledProviders("")).toEqual([]);
    expect(enabledProviders(undefined)).toEqual([]);
  });
});

describe("safeNext", () => {
  it("keeps same-site paths", () => {
    expect(safeNext("/courses/1?x=y")).toBe("/courses/1?x=y");
  });
  it("drops everything that could leave the site", () => {
    for (const next of [null, undefined, "", "https://evil.example", "//evil.example", "/\\evil.example", "evil", "/a\nb"]) {
      expect(safeNext(next)).toBe("/");
    }
  });
});

describe("the verifier cookie", () => {
  const verifier = newVerifier();
  it("round-trips the verifier and the next path", () => {
    expect(decodeCookie(encodeCookie({ verifier, next: "/account" }))).toEqual({ verifier, next: "/account" });
  });
  it("re-checks the next path on the way out", () => {
    expect(decodeCookie(encodeCookie({ verifier, next: "//evil.example" }))?.next).toBe("/");
  });
  it("refuses anything it did not write", () => {
    for (const raw of [undefined, "", "not base64 json", Buffer.from("{}").toString("base64url"), Buffer.from(JSON.stringify({ verifier: "short", next: "/" })).toString("base64url"), "x".repeat(5000)]) {
      expect(decodeCookie(raw)).toBeNull();
    }
  });
});

describe("startUrl", () => {
  it("points at the tenant API with the return URL and only the challenge", () => {
    const url = new URL(startUrl(tenant, "google", "http://coffee.app.localhost:4321/auth/social/callback", "C".repeat(43)));
    expect(url.origin).toBe("http://coffee.localhost");
    expect(url.pathname).toBe("/api/auth/social/google");
    expect(url.searchParams.get("return_url")).toBe("http://coffee.app.localhost:4321/auth/social/callback");
    expect(url.searchParams.get("challenge")).toBe("C".repeat(43));
    expect([...url.searchParams.keys()].sort()).toEqual(["challenge", "return_url"]);
  });
});

describe("messages and headers", () => {
  it("explains the reasons of the API and treats anything else as a generic failure", () => {
    expect(errorMessage("link_required")).toContain("password");
    expect(errorMessage("provider_denied")).toContain("cancelled");
    expect(errorMessage("<img src=x>")).toBe("Sign-in failed. Try again.");
    expect(errorMessage(null)).toBe("Sign-in failed. Try again.");
  });
  it("never caches the login pages and never sends a referrer", () => {
    expect(SOCIAL_HEADERS["Referrer-Policy"]).toBe("no-referrer");
    expect(SOCIAL_HEADERS["Cache-Control"]).toContain("no-store");
    expect(isSocialPath("/auth/social/callback")).toBe(true);
    expect(isSocialPath("/auth/social/google")).toBe(true);
    expect(isSocialPath("/login")).toBe(false);
  });
});

describe("exchangeCode", () => {
  const reply = (status: number, body: unknown) => vi.fn(async () => new Response(JSON.stringify(body), { status, headers: { "Content-Type": "application/json" } }));

  it("posts the code and the verifier, and returns the token", async () => {
    const fetcher = reply(200, { success: true, data: { complete: true, token: "T", expires_at: "2030-01-01T00:00:00Z" } });
    const result = await exchangeCode(tenant, "CODE", "VERIFIER", fetcher as unknown as typeof fetch);

    expect(result).toEqual({ ok: true, complete: true, token: "T", expiresAt: Date.parse("2030-01-01T00:00:00Z") });
    const [url, init] = fetcher.mock.calls[0] as unknown as [URL, RequestInit];
    expect(String(url)).toBe("http://coffee.localhost/api/auth/social/exchange");
    expect(init.method).toBe("POST");
    expect(JSON.parse(String(init.body))).toEqual({ code: "CODE", verifier: "VERIFIER" });
  });

  it("falls back to a day when the API sends no usable expiry", async () => {
    const result = await exchangeCode(tenant, "C", "V", reply(200, { data: { complete: true, token: "T", expires_at: "soon" } }) as unknown as typeof fetch);
    expect(result.ok && result.complete && result.expiresAt).toBeGreaterThan(Date.now() + 80_000_000);
  });

  it("tells a sign-up that needs more data apart from a login", async () => {
    expect(await exchangeCode(tenant, "C", "V", reply(200, { data: { complete: false, pre_user_token: "P" } }) as unknown as typeof fetch)).toEqual({ ok: true, complete: false });
  });

  it("reports refusals, empty answers and network errors as a plain failure", async () => {
    expect(await exchangeCode(tenant, "C", "V", reply(400, { message: "invalid" }) as unknown as typeof fetch)).toEqual({ ok: false });
    expect(await exchangeCode(tenant, "C", "V", reply(200, { data: {} }) as unknown as typeof fetch)).toEqual({ ok: false });
    expect(await exchangeCode(tenant, "C", "V", (async () => { throw new Error("down"); }) as unknown as typeof fetch)).toEqual({ ok: false });
    expect(await exchangeCode(tenant, "C", "V", (async () => new Response("<html>", { status: 200 })) as unknown as typeof fetch)).toEqual({ ok: false });
  });
});
