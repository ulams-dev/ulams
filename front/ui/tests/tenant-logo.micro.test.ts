import { describe, it, expect } from "vitest";
import { tenantLogo } from "../../web/src/lib/tenant-logo";

describe("tenantLogo", () => {
  it("returns null if settings is null", () => {
    expect(tenantLogo(null)).toBeNull();
  });

  it("returns null if settings is undefined", () => {
    expect(tenantLogo(undefined)).toBeNull();
  });

  it("returns null if theme.logo is not a string", () => {
    expect(tenantLogo({ theme: { logo: 123 } })).toBeNull();
  });

  it("returns null if theme.logo is not a valid URL", () => {
    expect(tenantLogo({ theme: { logo: "invalid-url" } })).toBeNull();
  });

  it("returns null if theme.logo protocol is not http or https", () => {
    expect(tenantLogo({ theme: { logo: "ftp://example.com/logo.png" } })).toBeNull();
  });

  it("returns a TenantLogo with correct src and alt if all fields are valid", () => {
    const settings = {
      theme: { logo: "https://example.com/logo.png", logoAlt: "Example Logo" },
      global: { companyName: "Example Company" }
    };
    expect(tenantLogo(settings)).toEqual({
      src: "https://example.com/logo.png",
      alt: "Example Logo"
    });
  });

  it("returns a TenantLogo with company name as alt if theme.logoAlt is missing", () => {
    const settings = {
      theme: { logo: "https://example.com/logo.png" },
      global: { companyName: "Example Company" }
    };
    expect(tenantLogo(settings)).toEqual({
      src: "https://example.com/logo.png",
      alt: "Example Company"
    });
  });

  it("returns a TenantLogo with fallback name as alt if both theme.logoAlt and global.companyName are missing", () => {
    const settings = {
      theme: { logo: "https://example.com/logo.png" }
    };
    expect(tenantLogo(settings, "Fallback Name")).toEqual({
      src: "https://example.com/logo.png",
      alt: "Fallback Name"
    });
  });
});
