/**
 * Theme definition used by the CSS-variable theming (see ./README.md).
 *
 * Keys mirror the former styled-components DefaultTheme so presets and the
 * `theme` setting stored by the API keep working. Every `dm__x` key is the
 * dark-mode value of `x`; it becomes the value of the same CSS variable under
 * `[data-mode="dark"]`.
 */
export type ThemeFont =
  | "Inter"
  | "Mulish"
  | "Titillium"
  | "Lato"
  | "Fraunces"
  | "Space Grotesk"
  | "Baloo 2";

export type ThemeMode = "light" | "dark";

export interface SharedThemeTokens {
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

export interface ThemeTokens extends SharedThemeTokens {
  mode?: ThemeMode;
  font: ThemeFont;
  /** Optional body font when the display font differs (e.g. editorial themes). */
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
