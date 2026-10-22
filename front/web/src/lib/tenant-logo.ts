import type { PublicSettings } from "@ulams/sdk";

/** The tenant's logo as the header shows it. */
export interface TenantLogo {
  src: string;
  alt: string;
}

/**
 * The logo from the public settings: `theme.logo` is the URL of the stored file (written by the admin's logo
 * endpoint, which checks the file), `theme.logoAlt` its text alternative. Only an http(s) URL is taken, so a
 * setting can never put a `javascript:` or `data:` address in the page. Without an alt text the company name
 * is the alternative; without a logo the header shows the company name as text.
 */
export function tenantLogo(settings: PublicSettings | null | undefined, fallbackName = ""): TenantLogo | null {
  const raw = settings?.theme?.logo;
  if (typeof raw !== "string") return null;
  let src: URL;
  try {
    src = new URL(raw.trim());
  } catch {
    return null;
  }
  if (src.protocol !== "http:" && src.protocol !== "https:") return null;
  const alt = typeof settings?.theme?.logoAlt === "string" ? settings.theme.logoAlt.trim() : "";
  const name = typeof settings?.global?.companyName === "string" ? settings.global.companyName.trim() : fallbackName;
  return { src: src.href, alt: (alt || name || fallbackName).slice(0, 120) };
}
