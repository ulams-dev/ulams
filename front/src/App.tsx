import React, { lazy, useContext, useEffect, useMemo } from "react";

import Routes from "./components/Routes";

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
import "./styles/global.css";
import styles from "./App.module.css";
import usePerformanceMetrics from "@/hooks/usePerformanceMetrics";

const Customizer = lazy(
  () => import("./components/_App/ThemeCustomizer/ThemeCustomizer")
);

const App = () => {
  const { fetchSettings, settings, fetchNotifications, fetchConfig } =
    useContext(UlamsContext);

  usePerformanceMetrics();
  const isBareLayout = useIsBareLayout();
  // A tenant theme named in settings (e.g. "coffee" / "coffeeTheme") is applied to both the
  // --ulams-* CSS variables, and hides the theme customizer.
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

  const noPadding =
    isBareLayout ||
    settings?.value?.global?.technicalMaintenance ||
    location.href.includes(routeRoutes.onboarding);

  return (
    <React.Fragment>
      <main
        className={[
          styles.main,
          noPadding ? styles.noPadding : isMobile ? styles.mobile : "",
        ]
          .filter(Boolean)
          .join(" ")}
      >
        <Customizer theme={tenantTheme} />
        {settings?.value?.global?.technicalMaintenance ? (
          <TechnicalMaintenanceScreen
            text={settings?.value?.global?.technicalMaintenanceText}
          />
        ) : (
          <Routes />
        )}
      </main>
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
