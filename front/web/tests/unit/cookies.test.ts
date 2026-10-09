import { describe, expect, it } from "vitest";
import { AUTHOR_BASE, SESSION_BASE, cookieName, isSecureRequest, parseSecureMode, sessionCookieOptions } from "../../src/lib/cookies.ts";

describe("session cookie names", () => {
  it("uses the __Host- prefix over https, for the learner and the author session", () => {
    expect(cookieName(SESSION_BASE, true)).toBe("__Host-ulams_session");
    expect(cookieName(AUTHOR_BASE, true)).toBe("__Host-ulams_author");
    // the configured dev fallback never applies to a secure request
    expect(cookieName(SESSION_BASE, true, "dev-")).toBe("__Host-ulams_session");
  });
  it("falls back to a configurable name over plain http (development)", () => {
    expect(cookieName(SESSION_BASE, false)).toBe("ulams_session");
    expect(cookieName(SESSION_BASE, false, "dev-")).toBe("dev-ulams_session");
  });
});

describe("session cookie options", () => {
  it("is httpOnly, Path=/, Lax, and never carries a Domain (a __Host- requirement)", () => {
    const options = sessionCookieOptions(true, 1_900_000_000_000);
    expect(options).toMatchObject({ httpOnly: true, secure: true, path: "/", sameSite: "lax" });
    expect(Object.keys(options)).not.toContain("domain");
    expect(options.expires.getTime()).toBe(1_900_000_000_000);
  });
});

describe("secure detection", () => {
  const h = (headers: Record<string, string> = {}) => new Headers(headers);
  it("trusts x-forwarded-proto from the TLS proxy, then the request protocol", () => {
    expect(isSecureRequest(h({ "x-forwarded-proto": "https" }), "http:")).toBe(true);
    expect(isSecureRequest(h({ "x-forwarded-proto": "http" }), "https:")).toBe(false);
    expect(isSecureRequest(h({ "x-forwarded-proto": "https, http" }), "http:")).toBe(true);
    expect(isSecureRequest(h(), "https:")).toBe(true);
    expect(isSecureRequest(h(), "http:")).toBe(false);
  });
  it("can be forced for proxies that do not forward the protocol", () => {
    expect(isSecureRequest(h(), "http:", "true")).toBe(true);
    expect(isSecureRequest(h({ "x-forwarded-proto": "https" }), "https:", "false")).toBe(false);
    expect(parseSecureMode("TRUE")).toBe("true");
    expect(parseSecureMode("nonsense")).toBe("auto");
    expect(parseSecureMode(undefined)).toBe("auto");
  });
});
