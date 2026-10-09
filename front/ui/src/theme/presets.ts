/**
 * Tenant theme presets as the studio's theme picker shows them. The values mirror
 * `../styles/themes/*.css` (a test fails when they drift), so a card previews the real tokens
 * without loading every theme's stylesheet.
 */
export const THEME_PRESETS = {
  coffee: { label: "The Coffee Atlas", mood: "Warm, editorial, paper-like", dark: false, bg: "#f6f1e9", card: "#ede5d8", text: "#2b1d14", muted: "#5e4a40", primary: "#a84521", onPrimary: "#fdfaf5", accent: "#c2552d", border: "#d9cfc2" },
  oncall: { label: "On-Call", mood: "Dense, dark, technical", dark: true, bg: "#0b0f14", card: "#121821", text: "#e6edf3", muted: "#9aa7b4", primary: "#58a6ff", onPrimary: "#0b0f14", accent: "#58a6ff", border: "#1e2733" },
  nightsky: { label: "Night Sky Explorers", mood: "Playful, night-blue", dark: true, bg: "#13153a", card: "#1f2257", text: "#f2f0ff", muted: "#c4bff0", primary: "#ffd23f", onPrimary: "#13153a", accent: "#ffd23f", border: "#343a8a" },
  gravity: { label: "Gravity Lab", mood: "Dark space, calm starfield", dark: true, bg: "#05070f", card: "#0e1424", text: "#e8ecf5", muted: "#a9b4cc", primary: "#3dd6f5", onPrimary: "#05070f", accent: "#3dd6f5", border: "#26324f" },
  poland: { label: "Poland, Measured", mood: "Cartographic paper, graticule", dark: false, bg: "#f4efe6", card: "#fbf8f2", text: "#1b2a3a", muted: "#4f5d6b", primary: "#c8102e", onPrimary: "#ffffff", accent: "#c8102e", border: "#d8cfbf" },
  ulam: { label: "The Scottish Book", mood: "Archival notebook, squared paper", dark: false, bg: "#f7f3e8", card: "#fffdf7", text: "#1e2230", muted: "#4a4f60", primary: "#1d3b8f", onPrimary: "#ffffff", accent: "#1d3b8f", border: "#d9d1bb" },
} as const;

export type TenantThemeName = keyof typeof THEME_PRESETS;
export const TENANT_THEMES = Object.keys(THEME_PRESETS) as TenantThemeName[];
