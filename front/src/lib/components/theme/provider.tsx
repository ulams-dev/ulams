import React, { useEffect } from "react";

import { useLocalTheme } from "../styleguide/useLocalTheme";
import { applyTheme } from "./applyTheme";
import { FONTS } from "./cssVars";
import type { ThemeFont, ThemeTokens } from "./types";

/** Font registry shared with the CSS-variable theming (cssVars.ts FONTS). */
export const Fonts: Record<ThemeFont, { links: string[]; fontFamily: string }> =
  FONTS;

const NO_FONT = { fontFamily: "sans-serif", links: [] as string[] };

/** Font for UI and body text: `bodyFont` when the theme has one, else `font`. */
export const getFontFromTheme = (
  theme?: ThemeTokens
): { links: string[]; fontFamily: string } => {
  const key = theme?.bodyFont ?? theme?.font;
  return (key && Fonts[key]) || NO_FONT;
};

/** Display font for headings (`font`), e.g. Playfair Display in the coffee preset. */
export const getDisplayFontFromTheme = (
  theme?: ThemeTokens
): { links: string[]; fontFamily: string } =>
  (theme?.font && Fonts[theme.font]) || NO_FONT;

/**
 * Applies the active theme as `--ulams-*` CSS variables (see ./README.md).
 * The theme is `defaultTheme` when given, otherwise the one stored by
 * `useLocalTheme` (tenant preset from settings or the theme customizer).
 */
export const GlobalThemeProvider: React.FC<{
  defaultTheme?: ThemeTokens;
  children?: React.ReactNode;
}> = ({ defaultTheme, children }) => {
  const [localTheme] = useLocalTheme();
  const theme = defaultTheme ?? localTheme;
  const links = Array.from(
    new Set([
      ...getDisplayFontFromTheme(theme).links,
      ...getFontFromTheme(theme).links,
    ])
  );

  useEffect(() => {
    applyTheme(theme, {
      mode: theme.mode ?? "light",
      name: typeof theme.theme === "string" ? theme.theme : undefined,
    });
  }, [theme]);

  return (
    <>
      {links.map((link) => (
        <link key={link} rel="stylesheet" href={link} />
      ))}
      {children}
    </>
  );
};
