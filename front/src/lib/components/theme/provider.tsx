import { DefaultTheme, ThemeProvider } from "styled-components";

import React, { useEffect } from "react";

import { useLocalTheme } from "../styleguide/useLocalTheme";
import { applyTheme } from "./applyTheme";
import { FONTS } from "./cssVars";
import type { ThemeFont, ThemeTokens } from "./types";

export interface SharedDefaultTheme {
  theme?: string;
  background: string;
  dm__background: string;
  cardBackgroundColor: string;
  dm__cardBackgroundColor: string;
  errorColor: string;
  dm__errorColor?: string;
  invertColor: string;

  buttonRadius?: number;
  inputRadius?: number;
  noteRadius?: number;
  checkboxRadius?: number;
  cardRadius?: number;
  modalRadius?: number;

  white: string;
  gray5: string;
  gray4: string;
  gray3: string;
  gray2: string;
  gray1: string;
  black: string;
  positive: string;
  positive2: string;
}

declare module "styled-components" {
  export interface DefaultTheme
    extends SharedDefaultTheme,
      Record<string, unknown> {
    mode?: "light" | "dark";
    font: ThemeFont;
    /** Body font when it differs from the display font (experience presets). */
    bodyFont?: ThemeFont;
    radius?: number;
    textColor: string;
    dm__textColor: string;
    primaryColor: string;
    dm__primaryColor?: string;
    dm__primaryColorOnLight?: string;
    secondaryColor?: string;
    dm__secondaryColor?: string;
    headerColor?: string;
    dm__headerColor?: string;
    inputBg?: string;
    dm__inputBg?: string;
    inputDisabledBg?: string;
    dm__inputDisabledBg?: string;
    labelListValueColor?: string;
    dm__labelListValueColor?: string;
    primaryButtonDisabled?: string;
    dm__primaryButtonDisabled?: string;
    outlineButtonColor?: string;
    dm__outlineButtonColor?: string;
    outlineButtonInvertColor?: string;
    dm__outlineButtonInvertColor?: string;
    breadcrumbsColor?: string;
    dm__breadcrumbsColor?: string;
    numerationsColor?: string;
    dm__numerationsColor?: string;
    colorBackground?: string;
    dm__colorBackground?: string;
  }
}

/** Font registry shared with the CSS-variable theming (cssVars.ts FONTS). */
export const Fonts: Record<
  DefaultTheme["font"],
  { links: string[]; fontFamily: string }
> = FONTS;

const NO_FONT = { fontFamily: "sans-serif", links: [] as string[] };

/** Font for UI and body text: `bodyFont` when the theme has one, else `font`. */
export const getFontFromTheme = (
  theme?: DefaultTheme
): { links: string[]; fontFamily: string } => {
  const key = theme?.bodyFont ?? theme?.font;
  return (key && Fonts[key]) || NO_FONT;
};

/** Display font for headings (`font`), e.g. Playfair Display in the coffee preset. */
export const getDisplayFontFromTheme = (
  theme?: DefaultTheme
): { links: string[]; fontFamily: string } =>
  (theme?.font && Fonts[theme.font]) || NO_FONT;

export const GlobalThemeProvider: React.FC<{
  defaultTheme?: DefaultTheme;
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

  // Keep the CSS-variable theme (--ulams-*, data-theme, data-mode on <html>) in sync
  // with the styled-components theme, so CSS Modules and styled components agree.
  useEffect(() => {
    applyTheme(theme as unknown as ThemeTokens, {
      mode: theme.mode ?? "light",
      name: typeof theme.theme === "string" ? theme.theme : undefined,
    });
  }, [theme]);

  return (
    <ThemeProvider theme={theme}>
      {links.map((link) => (
        <link key={link} rel="stylesheet" href={link} />
      ))}
      {children}
    </ThemeProvider>
  );
};
