import React, { lazy, useContext, useEffect, useMemo } from "react";

import Routes from "./components/Routes";

import styled, { createGlobalStyle } from "styled-components";
import { isMobile } from "react-device-detect";
import * as Sentry from "@sentry/react";
import { UlamsContext } from "@ulams/sdk/react";
import TechnicalMaintenanceScreen from "./components/_App/TechnicalMaintenanceScreen";
import { getTenantTheme } from "@ulams/components/theme";
import { useIsBareLayout } from "@/components/_App/bareLayout";
import routeRoutes from "@/components/Routes/routes";
import { useFirebase } from "@/hooks/useFirebase";
import { StatusBar } from "@capacitor/status-bar";
import { isMobilePlatform } from "@/utils/index";
import "react-loading-skeleton/dist/skeleton.css";
import usePerformanceMetrics from "@/hooks/usePerformanceMetrics";

const Customizer = lazy(
  () => import("./components/_App/ThemeCustomizer/ThemeCustomizer")
);

const GlobalStyle = createGlobalStyle`
  html, body {
    margin: 0;
    padding: 0;
    height: 100%;
    -webkit-font-smoothing: antialiased;
  }
  #root {
    height: 100%;
    /* inherited text colour, so unstyled text stays readable in dark presets */
    color: ${({ theme }) =>
      theme.mode === "dark" ? theme.dm__textColor : theme.textColor};
    background-color: ${({ theme }) =>
    theme.mode === "dark" ? theme.dm__background : theme.gray4};

  }
  #__ybug-launcher {
    right: 135px !important;
  }
  .table-responsive {
    td,
    tr,
    th {
      border: 1px solid
        ${({ theme }) => (theme.mode === "dark" ? theme.gray1 : theme.gray3)};
      padding: 5px;
    }
    table {
      border: 1px solid
        ${({ theme }) => (theme.mode === "dark" ? theme.gray1 : theme.gray3)};
      border-collapse: collapse;
    }
  }
  a {
    text-decoration: none;
  }


`;

const StyledMain = styled.main<{ noPadding?: boolean }>`
  height: fit-content;
  background-color: ${({ theme }) =>
    theme.mode === "dark" ? theme.dm__background : theme.background};
  padding-top: ${({ noPadding }) =>
    noPadding ? "0px" : isMobile ? "92px" : "57px"};
`;

const App = () => {
  const { fetchSettings, settings, fetchNotifications, fetchConfig } =
    useContext(UlamsContext);

  usePerformanceMetrics();
  const isBareLayout = useIsBareLayout();
  // A tenant theme named in settings (e.g. "coffee" / "coffeeTheme") is applied to both the
  // styled-components theme and the --ulams-* CSS variables, and hides the theme customizer.
  // `theme.accent` replaces the preset's primary colour (adjusted to keep AA contrast).
  const themeKey = settings.value?.theme?.theme;
  const themeAccent = settings.value?.theme?.accent;
  const tenantTheme = useMemo(
    () => getTenantTheme(themeKey, themeAccent),
    [themeKey, themeAccent]
  );

  useEffect(() => {
    if (isMobilePlatform) {
      // fix for status bar color
      // https://stackoverflow.com/questions/76578218/how-to-change-the-colour-of-carrier-and-clock-in-ios-and-android-with-ionic/77426871#77426871
      StatusBar.setBackgroundColor({ color: "#FFFFFF" });
    }
  }, []);

  useFirebase();

  useEffect(() => {
    fetchSettings();
    fetchNotifications();
    fetchConfig();
  }, [fetchSettings, fetchNotifications, fetchConfig]);

  return (
    <React.Fragment>
      <GlobalStyle />
      <StyledMain
        noPadding={
          isBareLayout ||
          settings?.value?.global?.technicalMaintenance ||
          location.href.includes(routeRoutes.onboarding)
        }
      >
        <Customizer theme={tenantTheme} />
        {settings?.value?.global?.technicalMaintenance ? (
          <TechnicalMaintenanceScreen
            text={settings?.value?.global?.technicalMaintenanceText}
          />
        ) : (
          <Routes />
        )}
      </StyledMain>
    </React.Fragment>
  );
};

// preventing local storage persist store versioning error
window.addEventListener("error", (event: ErrorEvent) => {
  if (event.message.includes("Cannot read properties of undefined")) {
    if (!window.location.href.includes("noerrorrefresh")) {
      localStorage.removeItem("lms");
      window.location.href = window.location.href + "#noerrorrefresh";
    }
  }
});

export default Sentry.withProfiler(App);
