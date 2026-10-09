import { describe, expect, it } from "vitest";
import { isSameOrigin, ownOrigins, refuseCrossSite } from "../../src/lib/bff.ts";

const APP = "acme.app.ulams.app";
const CONTENT = "https://acme.content.ulams.app";
const post = (path: string, headers: Record<string, string> = {}, method = "POST") =>
  new Request(`https://${APP}${path}`, { method, headers });

describe("exact-origin check on state-changing requests", () => {
  const WRITES = ["POST", "PUT", "PATCH", "DELETE"];
  const ROUTES = ["/bff/api/courses/progress/1/ping", "/studio/api/sessions", "/h5p/ajax/x", "/logout", "/login", "/lti/launch"];

  it.each(WRITES.flatMap((m) => ROUTES.map((r) => [m, r])))("%s %s from the content origin is refused with 403", async (method, route) => {
    const response = refuseCrossSite(post(route, { origin: CONTENT }, method), APP, true);
    expect(response?.status).toBe(403);
    expect(await response?.json()).toEqual({ message: "Cross-site request refused" });
  });

  it("refuses a sibling subdomain of the same site, null origins and header-less writes", () => {
    for (const origin of ["https://acme.content.ulams.app", "https://content.ulams.app", "https://ulams.app", "https://evil.acme.app.ulams.app", "null", "http://acme.app.ulams.app"]) {
      expect(refuseCrossSite(post("/bff/x", { origin }), APP, true)?.status).toBe(403);
    }
    expect(refuseCrossSite(post("/bff/x"), APP, true)?.status).toBe(403);
  });

  it("without an Origin, only Sec-Fetch-Site: same-origin passes (same-site is the content origin)", () => {
    expect(refuseCrossSite(post("/bff/x", { "sec-fetch-site": "same-origin" }), APP, true)).toBeNull();
    for (const site of ["same-site", "cross-site", "none"]) {
      expect(refuseCrossSite(post("/bff/x", { "sec-fetch-site": site }), APP, true)?.status).toBe(403);
    }
  });

  it("an Origin header wins over Sec-Fetch-Site", () => {
    expect(refuseCrossSite(post("/bff/x", { origin: CONTENT, "sec-fetch-site": "same-origin" }), APP, true)?.status).toBe(403);
  });

  it("accepts the tenant's own app origin, with the scheme the browser used", () => {
    expect(refuseCrossSite(post("/bff/x", { origin: `https://${APP}` }), APP, true)).toBeNull();
    expect(refuseCrossSite(post("/bff/x", { origin: `http://${APP}` }), APP, true)?.status).toBe(403);
    expect(refuseCrossSite(post("/bff/x", { origin: "http://coffee.app.localhost:4321" }), "coffee.app.localhost:4321", false)).toBeNull();
  });

  it("never blocks reads", () => {
    expect(refuseCrossSite(post("/learn/1", { origin: CONTENT }, "GET"), APP, true)).toBeNull();
    expect(isSameOrigin(post("/x", {}, "HEAD"), [])).toBe(true);
  });
});

describe("expected origin", () => {
  it("is the request host only, never a content origin", () => {
    expect(ownOrigins(APP, true)).toEqual([`https://${APP}`]);
    expect(ownOrigins("coffee.app.localhost:4321", false)).toEqual(["http://coffee.app.localhost:4321"]);
  });
  it("takes the first x-forwarded-host entry and rejects malformed hosts", () => {
    expect(ownOrigins(`${APP}, acme.content.ulams.app`, true)).toEqual([`https://${APP}`]);
    expect(ownOrigins("", true)).toEqual([]);
    expect(ownOrigins("a.example/evil", true)).toEqual([]);
    expect(refuseCrossSite(post("/bff/x", { origin: "https://" }), "", true)?.status).toBe(403);
  });
});
