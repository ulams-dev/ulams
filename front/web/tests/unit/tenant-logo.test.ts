import { describe, expect, it } from "vitest";
import type { PublicSettings } from "@ulams/sdk";
import { tenantLogo } from "../../src/lib/tenant-logo.ts";
import { PLATFORM_MANIFEST, tenantManifest } from "../../src/lib/webmanifest.ts";
import { THEME_COLOR, themeFor } from "../../src/lib/theme.ts";
import { THEME_BACKGROUND } from "../../src/lib/accent.ts";
import { PLATFORM_THEME, TENANT_THEMES, THEME_PRESETS } from "@ulams/ui/theme/presets.ts";

const settings = (theme: Record<string, unknown>, company = "Acme Coffee"): PublicSettings => ({ global: { companyName: company }, theme });

describe("tenant logo from the public settings", () => {
  it("is the stored URL with the admin's alt text", () => {
    expect(tenantLogo(settings({ logo: "https://s.example/theme/logo-1.png", logoAlt: "  Acme, home " }))).toEqual({ src: "https://s.example/theme/logo-1.png", alt: "Acme, home" });
  });

  it("falls back to the company name as alt text, and to the given name without settings", () => {
    expect(tenantLogo(settings({ logo: "http://storage.localhost/a.svg" }))).toEqual({ src: "http://storage.localhost/a.svg", alt: "Acme Coffee" });
    expect(tenantLogo({ theme: { logo: "https://s.example/a.png" } }, "slug")).toEqual({ src: "https://s.example/a.png", alt: "slug" });
  });

  it("is null without a logo, and for anything that is not an http(s) address", () => {
    expect(tenantLogo(settings({}))).toBeNull();
    expect(tenantLogo(null)).toBeNull();
    expect(tenantLogo(settings({ logo: "" }))).toBeNull();
    for (const bad of ["javascript:alert(1)", "data:image/svg+xml;base64,AAAA", "//evil.example/x.png", "/storage/a.png", "file:///etc/passwd", "ftp://x/y.png", 5, ["https://x/y.png"]]) {
      expect(tenantLogo(settings({ logo: bad })), String(bad)).toBeNull();
    }
  });

  it("limits the alt text to 120 characters", () => {
    expect(tenantLogo(settings({ logo: "https://s.example/a.png", logoAlt: "a".repeat(300) }))!.alt).toHaveLength(120);
  });
});

describe("per-tenant web manifest", () => {
  it("names the tenant, takes its theme colour and keeps the platform icons", () => {
    const manifest = tenantManifest({ slug: "poland" }, settings({ theme: "poland" }, "Poland, Measured"));
    expect(manifest).toMatchObject({ name: "Poland, Measured", short_name: "Poland, Meas", display: "standalone", start_url: "/", theme_color: "#f4efe6", background_color: "#f4efe6" });
    expect(manifest.icons).toEqual(PLATFORM_MANIFEST.icons);
  });

  it("puts the logo first as an icon of any size", () => {
    const png = tenantManifest({ slug: "x" }, settings({ logo: "https://s.example/t/logo.png" }));
    expect(png.icons[0]).toEqual({ src: "https://s.example/t/logo.png", sizes: "any", type: "image/png", purpose: "any" });
    expect(png.icons).toHaveLength(PLATFORM_MANIFEST.icons.length + 1);
    expect(tenantManifest({ slug: "x" }, settings({ logo: "https://s.example/t/logo.SVG?v=2" })).icons[0]!.type).toBe("image/svg+xml");
  });

  it("falls back to the slug and the default theme without settings", () => {
    expect(tenantManifest({ slug: "oncall" }, null)).toMatchObject({ name: "oncall", theme_color: "#0b0f14" });
    expect(tenantManifest({ slug: "unknown" }, null)).toMatchObject({ name: "unknown", theme_color: "#f6f1e9" });
  });

  it("keeps today's platform file for the platform host", () => {
    expect(PLATFORM_MANIFEST).toMatchObject({ name: "ulams", theme_color: "#0F2B46", background_color: "#0F2B46" });
    expect(PLATFORM_MANIFEST.icons.map((i) => i.src)).toEqual(["/icon-192.png", "/icon-512.png", "/icon-maskable-512.png"]);
  });
});

describe("the site reads the one preset list", () => {
  it("knows exactly the presets of the shared list, by setting or by slug", () => {
    for (const name of TENANT_THEMES) {
      expect(themeFor(name, "other")).toBe(name);
      expect(themeFor(undefined, name)).toBe(name);
    }
    expect(themeFor("platform", "nope")).toBe("coffee");
    expect(themeFor("neon", "nope")).toBe("coffee");
  });

  it("takes the browser chrome colour and the accent background from it", () => {
    for (const name of TENANT_THEMES) {
      expect(THEME_COLOR[name]).toBe(THEME_PRESETS[name].bg);
      expect(THEME_BACKGROUND[name]).toBe(THEME_PRESETS[name].bg);
    }
    expect(THEME_COLOR.platform).toBe(PLATFORM_THEME.themeColor);
    expect(THEME_BACKGROUND.platform).toBe(PLATFORM_THEME.bg);
  });
});
