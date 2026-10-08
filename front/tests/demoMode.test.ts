import { describe, it } from "node:test";
import assert from "node:assert/strict";
import {
  demoConfigFrom,
  demoLogin,
  isCourseRoute,
  shouldAutoLogin,
  siblingAppUrl,
} from "../src/lib/demo/demoMode.ts";

describe("demoConfigFrom", () => {
  it("reads ulams_demo from the public config", () => {
    assert.deepEqual(
      demoConfigFrom({
        ulams_demo: {
          enabled: true,
          front_url: "http://coffee.app.localhost/",
          admin_url: "http://coffee.admin.localhost",
        },
      }),
      {
        enabled: true,
        frontUrl: "http://coffee.app.localhost",
        adminUrl: "http://coffee.admin.localhost",
      }
    );
    assert.equal(
      demoConfigFrom({ ulams_demo: { enabled: "1" } }).enabled,
      true
    );
  });

  it("is off without the key, when disabled, or for junk", () => {
    for (const config of [
      undefined,
      null,
      {},
      [],
      { ulams_demo: null },
      { ulams_demo: { enabled: false } },
      { ulams_demo: { enabled: "false" } },
    ]) {
      assert.equal(demoConfigFrom(config).enabled, false);
    }
    assert.equal(
      demoConfigFrom({
        ulams_demo: { enabled: true, admin_url: "javascript:alert(1)" },
      }).adminUrl,
      null
    );
  });
});

describe("siblingAppUrl", () => {
  it("swaps the app label of a tenant host", () => {
    assert.equal(
      siblingAppUrl(
        { protocol: "http:", hostname: "coffee.app.localhost", port: "" },
        "app",
        "admin"
      ),
      "http://coffee.admin.localhost"
    );
    assert.equal(
      siblingAppUrl(
        {
          protocol: "https:",
          hostname: "oncall.admin.localhost",
          port: "8443",
        },
        "admin",
        "app"
      ),
      "https://oncall.app.localhost:8443"
    );
  });

  it("returns null for other hosts", () => {
    assert.equal(
      siblingAppUrl(
        { protocol: "http:", hostname: "localhost" },
        "app",
        "admin"
      ),
      null
    );
    assert.equal(
      siblingAppUrl(
        { protocol: "http:", hostname: "app.localhost" },
        "app",
        "admin"
      ),
      null
    );
    assert.equal(
      siblingAppUrl(
        { protocol: "https:", hostname: "coffee.ulams.app" },
        "app",
        "admin"
      ),
      null
    );
  });
});

describe("isCourseRoute", () => {
  it("matches course, program and preview pages", () => {
    for (const path of [
      "/courses/12",
      "/courses/12/",
      "/course/12",
      "/course/12/3/4",
      "/courses/preview/7/1",
    ]) {
      assert.equal(isCourseRoute(path), true, path);
    }
  });

  it("does not match lists and other pages", () => {
    for (const path of [
      "/",
      "/courses",
      "/courses/",
      "/login",
      "/user/my-profile",
      "/coursesx/1",
    ]) {
      assert.equal(isCourseRoute(path), false, path);
    }
  });
});

describe("shouldAutoLogin", () => {
  const base = {
    enabled: true,
    hasToken: false,
    inFlight: false,
    failed: false,
    bootAttempted: false,
    pathname: "/",
  };

  it("logs in once at boot", () => {
    assert.equal(shouldAutoLogin(base), true);
    assert.equal(shouldAutoLogin({ ...base, bootAttempted: true }), false);
  });

  it("logs in again before a course page", () => {
    assert.equal(
      shouldAutoLogin({ ...base, bootAttempted: true, pathname: "/courses/3" }),
      true
    );
  });

  it("never logs in when off, logged in, busy or after a failure", () => {
    assert.equal(shouldAutoLogin({ ...base, enabled: false }), false);
    assert.equal(shouldAutoLogin({ ...base, hasToken: true }), false);
    assert.equal(shouldAutoLogin({ ...base, inFlight: true }), false);
    assert.equal(
      shouldAutoLogin({ ...base, failed: true, pathname: "/course/1" }),
      false
    );
  });
});

describe("demoLogin", () => {
  it("posts the role and returns the token", async () => {
    const calls: Array<{ url: string; body?: string }> = [];
    const token = await demoLogin(
      "http://coffee.localhost/",
      "admin",
      async (url, init) => {
        calls.push({ url, body: init?.body });
        return {
          ok: true,
          status: 200,
          json: async () => ({
            success: true,
            data: { token: "abc", expires_at: null },
          }),
        };
      }
    );

    assert.equal(token, "abc");
    assert.deepEqual(calls, [
      {
        url: "http://coffee.localhost/api/demo/login",
        body: '{"role":"admin"}',
      },
    ]);
  });

  it("rejects when demo mode is off", async () => {
    await assert.rejects(
      demoLogin("http://api.localhost", "student", async () => ({
        ok: false,
        status: 404,
        json: async () => ({ message: "Not Found" }),
      })),
      /Demo login as student failed: Not Found/
    );
  });
});
