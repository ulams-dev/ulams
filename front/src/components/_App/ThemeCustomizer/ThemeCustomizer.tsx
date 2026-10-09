import { ThemeCustomizer as Wrapper } from "@ulams/components/styleguide/ThemeCustomizer";
import { useLocalTheme } from "@ulams/components/styleguide/useLocalTheme";
import defaultTheme from "@ulams/components/theme/contrast";
import type { getTenantTheme } from "@ulams/components/theme";
import { useEffect, useState } from "react";
import styles from "./ThemeCustomizer.module.css";
import { useTranslation } from "react-i18next";

type TenantTheme = NonNullable<ReturnType<typeof getTenantTheme>>;

/**
 * `theme` is the tenant preset from settings. When it is set, the preset is
 * stored as the active theme and the customizer button is not rendered.
 */
export const ThemeCustomizer = (theme: { theme?: TenantTheme }) => {
  const [, setTheme] = useLocalTheme({
    ...defaultTheme,
    theme: "contrastTheme",
  });

  const tenantTheme = theme.theme;
  useEffect(() => {
    if (tenantTheme) {
      setTheme(tenantTheme);
    }
  }, [tenantTheme, setTheme]);

  const { t } = useTranslation();

  const [hidden, setHidden] = useState(true);

  return (
    <div className={styles.root}>
      {!theme.theme && (
        <>
          <button
            onClick={() => setHidden((prevState) => !prevState)}
            aria-label={t(hidden ? "ShowCustomizer" : "HideCustomizer")}
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="24"
              height="24"
              viewBox="0 0 24 24"
              fill="none"
              stroke="#000000"
              strokeWidth="2"
              strokeLinecap="round"
              strokeLinejoin="round"
            >
              <circle cx="12" cy="12" r="3"></circle>
              <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
            </svg>
          </button>
          <Wrapper
            initialTheme={{ ...defaultTheme, theme: "contrastTheme" }}
            hasAll={false}
            hidden={hidden}
            onUpdate={(theme) => {
              setTheme(theme);
            }}
          />
        </>
      )}
    </div>
  );
};

export default ThemeCustomizer;
