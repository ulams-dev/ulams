/**
 * Tenant theme presets as the studio's theme picker shows them. The values mirror
 * `../styles/themes/*.css` (a test fails when they drift), so a card previews the real tokens
 * without loading every theme's stylesheet.
 */
export const THEME_PRESETS = {
  coffee: { label: "The Coffee Atlas", mood: "Warm, editorial, paper-like", dark: false, bg: "#f6f1e9", card: "#ede5d8", text: "#2b1d14", muted: "#5e4a40", primary: "#a84521", onPrimary: "#fdfaf5", accent: "#c2552d", border: "#d9cfc2" },
  oncall: { label: "On-Call", mood: "Dense, dark, technical", dark: true, bg: "#0b0f14", card: "#121821", text: "#e6edf3", muted: "#9aa7b4", primary: "#58a6ff", onPrimary: "#0b0f14", accent: "#58a6ff", border: "#1e2733" },
  nightsky: { label: "Night Sky Explorers", mood: "Playful, night-blue", dark: true, bg: "#13153a", card: "#1f2257", text: "#f2f0ff", muted: "#c4bff0", primary: "#ffd23f", onPrimary: "#13153a", accent: "#ffd23f", border: "#343a8a" },
} as const;

export type TenantThemeName = keyof typeof THEME_PRESETS;
export const TENANT_THEMES = Object.keys(THEME_PRESETS) as TenantThemeName[];
