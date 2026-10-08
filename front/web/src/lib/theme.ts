import type { ThemeName } from "@ulams/ui/registry";

/** Tenant theme from settings (`theme.theme`), else the slug when it names a preset. */
export function themeFor(settingsTheme: unknown, slug: string): ThemeName {
  const known: ThemeName[] = ["coffee", "oncall", "nightsky"];
  if (typeof settingsTheme === "string" && (known as string[]).includes(settingsTheme)) return settingsTheme as ThemeName;
  if ((known as string[]).includes(slug)) return slug as ThemeName;
  return "coffee";
}

/** Browser chrome colour per theme. */
export const THEME_COLOR: Record<ThemeName, string> = {
  coffee: "#f6f1e9",
  oncall: "#0b0f14",
  nightsky: "#13153a",
};

export const DEMO_TENANTS: Array<{ slug: string; name: string }> = [
  { slug: "coffee", name: "The Coffee Atlas" },
  { slug: "oncall", name: "On-Call" },
  { slug: "nightsky", name: "Night Sky Explorers" },
];
