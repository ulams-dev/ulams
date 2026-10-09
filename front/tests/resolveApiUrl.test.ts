import { describe, it } from "node:test";
import assert from "node:assert/strict";
import {
  DEFAULT_ADMIN_TENANT_PATTERN,
  DEFAULT_FRONT_TENANT_PATTERN,
  PRODUCTION_FRONT_TENANT_PATTERN,
  matchTenantSlug,
  parseTenantHostPattern,
  resolveApiUrl,
  tenantFromHost,
} from "../src/lib/tenant/resolveApiUrl.ts";

describe("parseTenantHostPattern", () => {
  it("parses one or more host=>api rules", () => {
    assert.deepEqual(parseTenantHostPattern(DEFAULT_FRONT_TENANT_PATTERN), [
      { host: "{slug}.app.localhost", api: "http://{slug}.localhost" },
    ]);
    assert.equal(
      parseTenantHostPattern(`${DEFAULT_FRONT_TENANT_PATTERN}, ${PRODUCTION_FRONT_TENANT_PATTERN}`).length,
      2
    );
  });

  it("returns no rules when disabled, blank or malformed", () => {
    for (const value of ["off", "none", "", undefined, null, "null", "foo.localhost=>http://x", "{slug}.a=>"]) {
      assert.deepEqual(parseTenantHostPattern(value), [], String(value));
    }
  });
});

describe("matchTenantSlug", () => {
  it("extracts a single DNS label", () => {
    assert.equal(matchTenantSlug("coffee.app.localhost", "{slug}.app.localhost"), "coffee");
    assert.equal(matchTenantSlug("Night-Sky.APP.localhost.", "{slug}.app.localhost"), "night-sky");
  });

  it("rejects plain hosts, nested labels and reserved names", () => {
    assert.equal(matchTenantSlug("localhost", "{slug}.app.localhost"), null);
    assert.equal(matchTenantSlug("app.localhost", "{slug}.app.localhost"), null);
    assert.equal(matchTenantSlug("a.b.app.localhost", "{slug}.app.localhost"), null);
    assert.equal(matchTenantSlug("api.ulams.app", "{slug}.ulams.app"), null);
    assert.equal(matchTenantSlug("www.ulams.app", "{slug}.ulams.app"), null);
  });
});

describe("tenantFromHost", () => {
  it("maps the local front and admin hosts to the tenant API", () => {
    assert.deepEqual(tenantFromHost("coffee.app.localhost", DEFAULT_FRONT_TENANT_PATTERN), {
      slug: "coffee",
      apiUrl: "http://coffee.localhost",
    });
    assert.deepEqual(tenantFromHost("oncall.admin.localhost", DEFAULT_ADMIN_TENANT_PATTERN), {
      slug: "oncall",
      apiUrl: "http://oncall.localhost",
    });
  });

  it("supports the production form", () => {
    assert.equal(
      tenantFromHost("nightsky.ulams.app", PRODUCTION_FRONT_TENANT_PATTERN)?.apiUrl,
      "https://nightsky.api.ulams.app"
    );
  });

  it("uses the first matching rule", () => {
    const pattern = `{slug}.app.localhost=>http://{slug}.localhost,{slug}.ulams.app=>https://{slug}.api.ulams.app`;
    assert.equal(tenantFromHost("coffee.ulams.app", pattern)?.apiUrl, "https://coffee.api.ulams.app");
  });
});

describe("resolveApiUrl", () => {
  const base = { buildTime: "http://api.localhost" };

  it("keeps the platform API on plain localhost", () => {
    assert.equal(resolveApiUrl({ ...base, hostname: "localhost" }), "http://api.localhost");
  });

  it("derives the tenant API from the host before the build-time URL", () => {
    assert.equal(resolveApiUrl({ ...base, hostname: "coffee.app.localhost" }), "http://coffee.localhost");
  });

  it("prefers a runtime-injected URL", () => {
    assert.equal(
      resolveApiUrl({ ...base, runtime: "https://x.example", hostname: "coffee.app.localhost" }),
      "https://x.example"
    );
  });

  it("ignores blank runtime values injected as empty strings", () => {
    assert.equal(resolveApiUrl({ ...base, runtime: "", hostname: "oncall.app.localhost" }), "http://oncall.localhost");
  });

  it("uses the admin default pattern for admin hosts", () => {
    assert.equal(
      resolveApiUrl({ ...base, hostname: "nightsky.admin.localhost", defaultPattern: DEFAULT_ADMIN_TENANT_PATTERN }),
      "http://nightsky.localhost"
    );
    assert.equal(resolveApiUrl({ ...base, hostname: "nightsky.admin.localhost" }), "http://api.localhost");
  });

  it("can be disabled", () => {
    assert.equal(resolveApiUrl({ ...base, hostname: "coffee.app.localhost", pattern: "off" }), "http://api.localhost");
  });

  it("returns null when nothing is configured", () => {
    assert.equal(resolveApiUrl({ hostname: "localhost" }), null);
  });
});
