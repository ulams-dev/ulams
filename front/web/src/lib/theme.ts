import type { ThemeName } from "@ulams/ui/registry";

/** Themes a tenant can use ("platform" is the product site's own theme, never a tenant's). */
export type TenantTheme = Exclude<ThemeName, "platform">;

/** Tenant theme from settings (`theme.theme`), else the slug when it names a preset. */
export function themeFor(settingsTheme: unknown, slug: string): TenantTheme {
  const known: TenantTheme[] = ["coffee", "oncall", "nightsky"];
  if (typeof settingsTheme === "string" && (known as string[]).includes(settingsTheme)) return settingsTheme as TenantTheme;
  if ((known as string[]).includes(slug)) return slug as TenantTheme;
  return "coffee";
}

/** Browser chrome colour per theme. */
export const THEME_COLOR: Record<ThemeName, string> = {
  coffee: "#f6f1e9",
  oncall: "#0b0f14",
  nightsky: "#13153a",
  platform: "#fafaf9",
};

export const DEMO_TENANTS: Array<{ slug: string; name: string }> = [
  { slug: "coffee", name: "The Coffee Atlas" },
  { slug: "oncall", name: "On-Call" },
  { slug: "nightsky", name: "Night Sky Explorers" },
];
