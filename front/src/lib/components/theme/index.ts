import blueTheme from "./blue";
import orangeTheme from "./orange";
import redTheme from "./red";
import velvetTheme from "./velvet";
import contrastTheme from "./contrast";
import { coffeeTheme, nightskyTheme, oncallTheme } from "./experiences";
import { experienceFromThemeKey } from "./experienceKey";
import { withAccent } from "./accent";
import { DefaultTheme } from "styled-components";

/**
 * Presets selectable through the tenant's `theme.theme` setting. The experience
 * presets are ThemeTokens (CSS-variable theming); their keys match DefaultTheme,
 * so the styled-components ThemeProvider can use them as well.
 */
const themes: Record<string, DefaultTheme> = {
  blueTheme,
  orangeTheme,
  redTheme,
  velvetTheme,
  contrastTheme,
  coffeeTheme: { ...coffeeTheme },
  oncallTheme: { ...oncallTheme },
  nightskyTheme: { ...nightskyTheme },
};

/** Registry key for a setting value: `coffeeTheme` and `coffee` both give `coffeeTheme`. */
export const resolveThemeKey = (key: unknown): string | undefined => {
  if (typeof key !== "string" || key === "") return undefined;
  if (Object.prototype.hasOwnProperty.call(themes, key)) return key;
  const experience = experienceFromThemeKey(key);
  return experience ? `${experience}Theme` : undefined;
};

/** Preset for a setting value, or undefined when the value names no preset. */
export const getThemeByKey = (key: unknown): DefaultTheme | undefined => {
  const resolved = resolveThemeKey(key);
  return resolved ? themes[resolved] : undefined;
};

/** Preset for the tenant settings `theme.theme` plus the optional `theme.accent` colour. */
export const getTenantTheme = (
  key: unknown,
  accent?: unknown
): DefaultTheme | undefined => {
  const preset = getThemeByKey(key);
  return preset ? withAccent(preset, accent) : undefined;
};

export default themes;
