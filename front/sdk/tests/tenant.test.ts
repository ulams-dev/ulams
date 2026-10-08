import { describe, expect, it } from "vitest";
import { resolveTenant } from "../src/tenant.ts";

describe("resolveTenant", () => {
  it("maps <slug>.app.localhost (with port) to the tenant API and admin", () => {
    expect(resolveTenant("coffee.app.localhost:4321")).toEqual({
      slug: "coffee",
      apiUrl: "http://coffee.localhost",
      adminUrl: "http://coffee.admin.localhost",
    });
  });

  it("supports custom rules and admin templates", () => {
    expect(
      resolveTenant("oncall.ulams.app", {
        pattern: "{slug}.ulams.app=>https://{slug}.api.ulams.app",
        adminUrlTemplate: "https://{slug}.admin.ulams.app/",
      })
    ).toEqual({ slug: "oncall", apiUrl: "https://oncall.api.ulams.app", adminUrl: "https://oncall.admin.ulams.app" });
  });

  it("rejects reserved slugs and unknown hosts", () => {
    expect(resolveTenant("admin.app.localhost")).toBeNull();
    expect(resolveTenant("example.com")).toBeNull();
    expect(resolveTenant(null)).toBeNull();
  });

  it("uses the fallback tenant for hosts without a rule", () => {
    expect(resolveTenant("localhost:4321", { fallback: { slug: "coffee", apiUrl: "http://coffee.localhost/" } })).toMatchObject({
      slug: "coffee",
      apiUrl: "http://coffee.localhost",
    });
  });
});
