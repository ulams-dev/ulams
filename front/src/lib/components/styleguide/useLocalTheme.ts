import { useCallback, useEffect, useState } from "react";
import type { ThemeTokens } from "../theme/types";

import { orangeTheme as defaultTheme } from "../theme/orange";
import themes from "../theme";

/**
 * Fallback values for keys a stored theme lacks. Optional dark-mode keys (`dm__x`) are
 * left out: a stored preset without `dm__x` means "use x in dark mode too", so the
 * default preset's dark values must not leak into it.
 */
const REQUIRED_DARK_KEYS = new Set(["dm__background", "dm__textColor", "dm__cardBackgroundColor"]);
const storedThemeDefaults = Object.fromEntries(
  Object.entries(defaultTheme).filter(
    ([key]) => !key.startsWith("dm__") || REQUIRED_DARK_KEYS.has(key)
  )
) as Partial<ThemeTokens>;

export const getThemeFromLocalStorage = (
  theme: ThemeTokens = defaultTheme
): ThemeTokens => {
  if (
    window.localStorage.getItem("theme") !== null &&
    typeof window.localStorage.getItem("theme") === "string"
  ) {
    try {
      theme = {
        mode: "light",
        theme: Object.keys(themes).includes(window.location.hash.substr(1))
          ? window.location.hash.substr(1)
          : "all",
        ...storedThemeDefaults,
        ...JSON.parse(window.localStorage.getItem("theme") || ""),
      };
    } catch (err) {
      return defaultTheme;
    }
    return theme;
  }
  return theme;
};

export const setThemeToLocalStorage = (
  theme: ThemeTokens = defaultTheme
): void => {
  window.localStorage.setItem("theme", JSON.stringify(theme));
  window.dispatchEvent(new Event("themeChange"));
};

// Hook
export function useLocalTheme(
  initialValue: ThemeTokens = defaultTheme
): [ThemeTokens, (value: ThemeTokens) => void] {
  const [localTheme, setLocalTheme] = useState<ThemeTokens>(
    getThemeFromLocalStorage(
      Object.keys(themes).includes(window.location.hash.substr(1))
        ? {
            ...(themes[window.location.hash.substr(1)] as ThemeTokens),
            theme: window.location.hash.substr(1),
          }
        : initialValue
    )
  );

  const setTheme = useCallback((theme: ThemeTokens) => {
    setThemeToLocalStorage(theme);
  }, []);

  useEffect(() => {
    if (typeof window !== "undefined") {
      const listener = () => {
        const value = getThemeFromLocalStorage(initialValue);
        setLocalTheme(value);
      };
      window.addEventListener("themeChange", listener);
      window.addEventListener("storage", listener);

      return () => {
        window.removeEventListener("themeChange", listener);
        window.removeEventListener("storage", listener);
      };
    }
  }, []);

  return [localTheme, setTheme];
}
