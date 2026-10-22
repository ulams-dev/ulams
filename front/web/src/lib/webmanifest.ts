import type { PublicSettings } from "@ulams/sdk";
import { tenantLogo } from "./tenant-logo.ts";
import { THEME_COLOR, themeFor } from "./theme.ts";

/** The web app manifest of the platform host (the product site): what `public/site.webmanifest` used to say. */
export const PLATFORM_MANIFEST = {
  name: "ulams",
  short_name: "ulams",
  description: "The open, AI-native, headless LMS.",
  start_url: "/",
  display: "standalone",
  theme_color: "#0F2B46",
  background_color: "#0F2B46",
  icons: [
    { src: "/icon-192.png", sizes: "192x192", type: "image/png", purpose: "any" },
    { src: "/icon-512.png", sizes: "512x512", type: "image/png", purpose: "any" },
    { src: "/icon-maskable-512.png", sizes: "512x512", type: "image/png", purpose: "maskable" },
  ],
} as const;

interface ManifestIcon {
  src: string;
  sizes: string;
  type?: string;
  purpose?: string;
}

/**
 * The manifest of a tenant site: its own name, the browser chrome colour of its theme and, when it has one, its
 * logo as an icon (before the platform icons, which stay so the app remains installable: browsers want PNG icons
 * of 192 and 512 px and a logo of any size cannot promise that).
 */
export function tenantManifest(tenant: { slug: string }, settings: PublicSettings | null | undefined) {
  const name = (typeof settings?.global?.companyName === "string" && settings.global.companyName.trim()) || tenant.slug;
  const theme = themeFor(settings?.theme?.theme, tenant.slug);
  const color = THEME_COLOR[theme];
  const logo = tenantLogo(settings, name);
  const icons: ManifestIcon[] = [...PLATFORM_MANIFEST.icons];
  if (logo) {
    const svg = new URL(logo.src).pathname.toLowerCase().endsWith(".svg");
    icons.unshift({ src: logo.src, sizes: "any", type: svg ? "image/svg+xml" : "image/png", purpose: "any" });
  }
  return {
    name,
    short_name: name.length > 12 ? name.slice(0, 12).trimEnd() : name,
    description: PLATFORM_MANIFEST.description,
    start_url: "/",
    display: "standalone",
    theme_color: color,
    background_color: color,
    icons,
  };
}
