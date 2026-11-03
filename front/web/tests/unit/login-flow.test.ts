import { describe, expect, it, vi } from "vitest";
import { beginLogin, finishLogin, isSpeculative } from "../../src/lib/login-flow.ts";
import { SOCIAL_COOKIE_BASE, encodeCookie, newVerifier } from "../../src/lib/social-login.ts";

const tenant = { apiUrl: "http://coffee.localhost" };
const redirect = (to: string, status = 302) => new Response(null, { status, headers: { Location: to } });

/** Records what the handlers ask of Astro's cookie jar: `delete` must carry the path the cookie was set with. */
function ctx(path: string, headers: Record<string, string> = {}, saved?: string) {
  const request = new Request(`http://coffee.app.localhost:4392${path}`, { headers: { host: "coffee.app.localhost:4392", ...headers } });
  const set = vi.fn();
  const del = vi.fn();
  const cookies = { get: (name: string) => (saved !== undefined && name === SOCIAL_COOKIE_BASE ? { value: saved } : undefined), set, delete: del };
  return { c: { request, url: new URL(request.url), cookies, locals: { tenant, secure: false }, redirect } as never as Parameters<typeof beginLogin>[0], set, del };
}

describe("speculative requests", () => {
  it("recognises prefetch and prerender marks", () => {
    expect(isSpeculative(new Headers({ "sec-fetch-dest": "prefetch" }))).toBe(true);
    expect(isSpeculative(new Headers({ "sec-purpose": "prefetch;prerender" }))).toBe(true);
    expect(isSpeculative(new Headers({ purpose: "prefetch" }))).toBe(true);
    expect(isSpeculative(new Headers({ "sec-fetch-dest": "document" }))).toBe(false);
    expect(isSpeculative(new Headers())).toBe(false);
  });

  it("does not start a login, so no verifier cookie can land after the callback", () => {
    const { c, set } = ctx("/auth/sso/acme?next=%2F", { "sec-fetch-dest": "prefetch" });
    const response = beginLogin(c, "/auth/sso/callback", () => "http://coffee.localhost/start");
    expect(response.status).toBe(204);
    expect(response.headers.get("location")).toBeNull();
    expect(set).not.toHaveBeenCalled();
  });

  it("starts a real navigation with one httpOnly Lax cookie on Path=/", () => {
    const { c, set } = ctx("/auth/sso/acme?next=%2Fcourses%2F1", { "sec-fetch-dest": "document" });
    expect(beginLogin(c, "/auth/sso/callback", () => "http://coffee.localhost/start").status).toBe(302);
    expect(set).toHaveBeenCalledTimes(1);
    expect(set.mock.calls[0]![0]).toBe(SOCIAL_COOKIE_BASE);
    expect(set.mock.calls[0]![2]).toMatchObject({ httpOnly: true, sameSite: "lax", path: "/", maxAge: 600, secure: false });
  });
});

describe("callback", () => {
  const state = () => encodeCookie({ verifier: newVerifier(), next: "/courses/1" });

  it("expires the state cookie with the name, path and secure flag it was set with, on success", async () => {
    const { c, del } = ctx("/auth/sso/callback?code=abc", {}, state());
    const redeem = vi.fn(async () => ({ ok: true as const, complete: true as const, token: "t", expiresAt: 1_900_000_000_000 }));
    expect((await finishLogin(c, redeem as never)).status).toBe(303);
    expect(del).toHaveBeenCalledWith(SOCIAL_COOKIE_BASE, { path: "/", secure: false });
  });

  it("expires it on an error and when the code is missing as well", async () => {
    for (const path of ["/auth/sso/callback?error=denied", "/auth/sso/callback"]) {
      const { c, del } = ctx(path, {}, state());
      expect((await finishLogin(c, vi.fn() as never)).status).toBe(303);
      expect(del).toHaveBeenCalledWith(SOCIAL_COOKIE_BASE, { path: "/", secure: false });
    }
  });
});
