import type { ThemeName } from "@ulams/ui/registry";
import { PLATFORM_THEME, TENANT_THEMES, themeColorOf } from "@ulams/ui/theme/presets.ts";

/** Themes a tenant can use ("platform" is the product site's own theme, never a tenant's). */
export type TenantTheme = Exclude<ThemeName, "platform">;

/** Tenant theme from settings (`theme.theme`), else the slug when it names a preset. */
export function themeFor(settingsTheme: unknown, slug: string): TenantTheme {
  const known: readonly string[] = TENANT_THEMES;
  if (typeof settingsTheme === "string" && known.includes(settingsTheme)) return settingsTheme as TenantTheme;
  if (known.includes(slug)) return slug as TenantTheme;
  return "coffee";
}

/** Browser chrome colour per theme, from the shared preset list. */
export const THEME_COLOR: Record<ThemeName, string> = {
  ...(Object.fromEntries(TENANT_THEMES.map((name) => [name, themeColorOf(name)])) as Record<TenantTheme, string>),
  platform: PLATFORM_THEME.themeColor,
};

export const DEMO_TENANTS: Array<{ slug: string; name: string }> = [
  { slug: "coffee", name: "The Coffee Atlas" },
  { slug: "oncall", name: "On-Call" },
  { slug: "nightsky", name: "Night Sky Explorers" },
  { slug: "gravity", name: "Gravity Lab" },
  { slug: "poland", name: "Poland, Measured" },
  { slug: "ulam", name: "The Scottish Book" },
];
