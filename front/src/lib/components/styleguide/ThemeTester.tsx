import React, { useCallback, useEffect, useMemo, useRef, useState } from "react";
import { GlobalThemeProvider } from "../theme/provider";
import { default as chroma } from "chroma-js";
import { useLocalTheme } from "./useLocalTheme";
import themes from "../theme";
import axeCore from "axe-core";
import Spin from "../components/atoms/Spin/Spin";
import Badge from "../components/atoms/Badge/Badge";
import { themeToDarkVars, themeToVars } from "../theme/cssVars";
import type { ThemeTokens } from "../theme/types";
import styles from "./ThemeTester.module.css";

type Mode = ("light" | "dark")[];

const modes: Mode = ["light", "dark"];

export interface ThemeTesterWrapperProps {
  name: string;
  /** Theme rendered by this wrapper; its CSS variables are scoped to the wrapper. */
  theme: ThemeTokens;
  mode?: "light" | "dark";
  childrenListStyle?: React.CSSProperties;
  children?: React.ReactNode;
  flexDirection?: React.CSSProperties["flexDirection"];
  alignItems?: React.CSSProperties["alignItems"];
}

const ThemeTesterWrapper: React.FC<ThemeTesterWrapperProps> = (props) => {
  const {
    theme,
    children,
    name,
    childrenListStyle,
    mode = theme.mode,
    flexDirection,
    alignItems,
  } = props;

  const [axeViolations, setAxeViolations] = useState<axeCore.Result[]>();
  const [axeLoading, setAxeLoading] = useState<boolean>(false);

  const ref = useRef<HTMLDivElement>(null);

  const a11yTest = useCallback(() => {
    if (ref.current) {
      setAxeLoading(true);
      axeCore
        .run(ref.current)
        .then((results) => {
          if (results.violations.length > 0) {
            console.table(results.violations);
          }
          setAxeViolations(results.violations);
        })
        .catch((err) => {
          console.error("Something bad happened:", err, Object.keys(err));
        })
        .finally(() => setAxeLoading(false));
    }
  }, [ref]);

  useEffect(() => {
    // a11yTest();
  }, [ref]);

  // Scope the theme to this wrapper (several themes are shown side by side).
  const scopedStyle = useMemo(() => {
    const vars: Record<string, string> = {
      ...themeToVars(theme),
      ...(mode === "dark" ? themeToDarkVars(theme) : {}),
    };
    let titleBg: string | undefined;
    try {
      titleBg =
        mode === "dark"
          ? chroma(theme.dm__background).brighten(0.5).hex()
          : chroma(theme.background).darken(0.5).hex();
    } catch {
      titleBg = undefined;
    }
    return {
      ...vars,
      ...(titleBg ? { "--tt-title-bg": titleBg } : {}),
      ...(flexDirection ? { "--tt-flex-direction": flexDirection } : {}),
      ...(alignItems ? { "--tt-align-items": alignItems } : {}),
    } as React.CSSProperties;
  }, [theme, mode, flexDirection, alignItems]);

  return (
    <div className={styles.wrapper} data-mode={mode} style={scopedStyle}>
      <p className="children-list-title">
        <span>
          Theme <strong>{name}</strong>
        </span>{" "}
        <span>
          Mode <strong>{mode}</strong>
        </span>
      </p>
      <div className="children-list" style={childrenListStyle} ref={ref}>
        {children}
      </div>
      <div className="axe-a11y">
        <div className="button">
          {axeLoading && <Spin />}
          <button disabled={axeLoading} onClick={() => a11yTest()}>
            Run a11y tests with axe
          </button>
        </div>

        {axeViolations && axeViolations.length === 0 && (
          <p>Congratulations! No a11y violations found.</p>
        )}

        {!axeLoading && axeViolations && axeViolations.length > 0 && (
          <table>
            <thead>
              <tr>
                <th className="id">id</th>
                <th className="impact">impact</th>
                <th className="tags">tags</th>
                <th className="help">help</th>
                <th className="description">description</th>
              </tr>
            </thead>
            <tbody>
              {axeViolations &&
                axeViolations.map((v) => (
                  <tr key={v.id}>
                    <td className="id">
                      <span>{v.id}</span>
                    </td>
                    <td className="impact">
                      <span className={v.impact?.toString()}>
                        <Badge>{v.impact}</Badge>
                      </span>
                    </td>
                    <td className="tags">
                      <ul>
                        {v.tags.map((tag) => (
                          <li key={tag}>
                            <span>{tag}</span>
                          </li>
                        ))}
                      </ul>
                    </td>
                    <td className="help">
                      <a target="_blank" href={v.helpUrl}>
                        {v.help}
                      </a>
                    </td>
                    <td className="description">{v.description}</td>
                  </tr>
                ))}
            </tbody>
          </table>
        )}
      </div>
    </div>
  );
};

interface ThemeTesterProps {
  children?: React.ReactNode;
  childrenListStyle?: React.CSSProperties;
  flexDirection?: React.CSSProperties["flexDirection"];
  alignItems?: React.CSSProperties["alignItems"];
}

export const ThemeTester: React.FC<ThemeTesterProps> = (props) => {
  const { children, childrenListStyle, flexDirection, alignItems } = props;
  const [localTheme] = useLocalTheme();

  return (
    <div>
      {localTheme.theme === "all" &&
        Object.entries(themes).map((theme) =>
          modes.map((mode) => (
            <React.Fragment key={`${theme[0]}${mode}`}>
              <ThemeTesterWrapper
                theme={theme[1] as unknown as ThemeTokens}
                flexDirection={flexDirection}
                alignItems={alignItems}
                name={theme[0].split("Theme").join("")}
                mode={mode}
                childrenListStyle={childrenListStyle}
              >
                {children}
              </ThemeTesterWrapper>
            </React.Fragment>
          ))
        )}
      {localTheme.theme !== "all" && localTheme.theme !== "custom" && (
        <>
          <ThemeTesterWrapper
            theme={localTheme}
            flexDirection={flexDirection}
            alignItems={alignItems}
            name={localTheme.theme?.split("Theme").join("") || ""}
            mode={localTheme.mode}
            childrenListStyle={childrenListStyle}
          >
            {children}
          </ThemeTesterWrapper>
        </>
      )}
      {localTheme.theme === "custom" && (
        <GlobalThemeProvider>
          <ThemeTesterWrapper
            theme={localTheme}
            mode={localTheme.mode}
            name={"Custom"}
            alignItems={alignItems}
            childrenListStyle={childrenListStyle}
            flexDirection={flexDirection}
          >
            {children}
          </ThemeTesterWrapper>
        </GlobalThemeProvider>
      )}
    </div>
  );
};

export default ThemeTester;
