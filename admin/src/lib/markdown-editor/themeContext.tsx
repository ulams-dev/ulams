import * as React from "react";
import "./styles/theme.css";
import { light } from "./theme";

export type EditorTheme = typeof light;

/** `toolbarBackground` -> `--md-toolbar-background` (see styles/theme.css). */
export const cssVarName = (key: string): string =>
  `--md-${key.replace(/[A-Z]/g, (m) => `-${m.toLowerCase()}`)}`;

/** Inline custom properties for the keys of a (partial) theme. */
export function themeToCssVars(
  theme?: Partial<EditorTheme> | null,
): Record<string, string> | undefined {
  if (!theme) return undefined;
  const vars: Record<string, string> = {};
  Object.keys(theme).forEach((key) => {
    const value = theme[key];
    if (typeof value === "string" || typeof value === "number") {
      vars[cssVarName(key)] = String(value);
    }
  });
  return vars;
}

export type EditorThemeContextValue = {
  /** Resolved theme (light/dark merged with the `theme` prop), for values needed in JS. */
  theme: EditorTheme;
  dark?: boolean;
  /** The editor"s `theme` prop, applied as inline custom properties. */
  overrides?: Partial<EditorTheme>;
};

export const EditorThemeContext = React.createContext<EditorThemeContextValue>({ theme: light });

export const cx = (...names: Array<string | false | null | undefined>) =>
  names.filter(Boolean).join(" ");

/**
 * className/style that put the theme variables on an element. Used on the editor root and on
 * portalled elements, which are outside the root and do not inherit its variables.
 */
export function themeScopeProps(
  ctx: EditorThemeContextValue,
  className?: string,
  style?: React.CSSProperties,
): { className: string; style?: React.CSSProperties } {
  const vars = themeToCssVars(ctx.overrides);
  return {
    className: cx("ulams-md-theme", ctx.dark && "ulams-md-theme--dark", className),
    style: vars || style ? { ...vars, ...style } : undefined,
  };
}

/** Injects the resolved theme as the `theme` prop (replaces the former CSS-in-JS withTheme). */
export function withEditorTheme<P extends { theme: EditorTheme }>(
  Component: React.ComponentType<P>,
): React.ComponentType<Omit<P, "theme">> {
  function WithTheme(props: Omit<P, "theme">) {
    const { theme } = React.useContext(EditorThemeContext);
    return <Component {...(props as P)} theme={theme} />;
  }
  WithTheme.displayName = `WithTheme(${Component.displayName || Component.name || "Component"})`;
  return WithTheme;
}
