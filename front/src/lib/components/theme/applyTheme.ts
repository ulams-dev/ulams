import { useSyncExternalStore } from "react";

import { FONTS, themeToCss } from "./cssVars";
import type { ThemeMode, ThemeTokens } from "./types";

/**
 * Runtime theming without styled-components.
 *
 * `applyTheme` writes the theme's CSS custom properties into one <style> element,
 * sets `data-theme` / `data-mode` on <html> and loads the theme's Google Fonts.
 * Components style themselves with `var(--ulams-…)` only. `useThemeTokens` is for
 * the rare JS consumer that needs raw values (charts, canvas, third-party widgets).
 */
const STYLE_ID = "ulams-theme";

let current: ThemeTokens | undefined;
const listeners = new Set<() => void>();

function ensureFontLinks(theme: ThemeTokens): void {
  const fonts: ThemeTokens["font"][] = [theme.font];
  if (theme.bodyFont && theme.bodyFont !== theme.font) fonts.push(theme.bodyFont);
  for (const font of fonts) {
    for (const href of FONTS[font]?.links ?? []) {
      if (!document.head.querySelector(`link[rel="stylesheet"][href="${href}"]`)) {
        const link = document.createElement("link");
        link.rel = "stylesheet";
        link.href = href;
        document.head.appendChild(link);
      }
    }
  }
}

export function applyTheme(theme: ThemeTokens, options: { mode?: ThemeMode; name?: string } = {}): void {
  current = { ...theme, mode: options.mode ?? theme.mode ?? "light" };
  if (typeof document === "undefined") return;

  let style = document.getElementById(STYLE_ID) as HTMLStyleElement | null;
  if (!style) {
    style = document.createElement("style");
    style.id = STYLE_ID;
    document.head.appendChild(style);
  }
  style.textContent = themeToCss(current);

  const root = document.documentElement;
  root.dataset.mode = current.mode;
  const name = options.name ?? current.theme;
  if (name) root.dataset.theme = name;
  else delete root.dataset.theme;

  ensureFontLinks(current);
  listeners.forEach((l) => l());
}

export function setThemeMode(mode: ThemeMode): void {
  if (current) applyTheme(current, { mode, name: current.theme });
}

export function getCurrentTheme(): ThemeTokens | undefined {
  return current;
}

function subscribe(listener: () => void): () => void {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

/** Current theme tokens for JS consumers; re-renders when `applyTheme` runs. */
export function useThemeTokens(): ThemeTokens | undefined {
  return useSyncExternalStore(subscribe, getCurrentTheme, getCurrentTheme);
}
