import React, { useContext, useEffect, useRef, useState } from "react";
import { useLocation } from "react-router-dom";
import { useTranslation } from "react-i18next";
import { UlamsContext } from "@ulams/sdk/react/context";
import {
  demoConfigFrom,
  demoLogin,
  shouldAutoLogin,
  siblingAppUrl,
} from "@ulams/demo";
import { API_URL } from "@/config/index";
import { Loader } from "../Loader/Loader";
import styles from "./DemoMode.module.css";

// Once per page load: after a logout the visitor stays logged out until a reload or until
// they open a course.
let bootAttempted = false;

/**
 * Demo tenants (`ulams_demo.enabled` in the public config): logs a logged-out visitor in as
 * the demo student, at boot and again before any course page, and shows the demo badge.
 * Must be rendered inside the router.
 */
export const DemoModeGate: React.FC<{ children: React.ReactNode }> = ({
  children,
}) => {
  const { config, token, user, socialAuthorize } = useContext(UlamsContext);
  const { pathname } = useLocation();
  const demo = demoConfigFrom(config?.value);
  const [inFlight, setInFlight] = useState(false);
  const [failed, setFailed] = useState(false);
  const [awaitingProfile, setAwaitingProfile] = useState(false);

  // The public config is fetched at boot (App.tsx); until it is known, a logged-out visitor
  // could be sent to the login page by a private route.
  const seenLoading = useRef(false);
  if (config?.loading) seenLoading.current = true;
  const configKnown =
    Object.keys(config?.value ?? {}).length > 0 ||
    !!config?.error ||
    (seenLoading.current && !config?.loading);

  // Computed during render: a private route must not redirect to the login page in the
  // render before the effect starts the login.
  const loginDue =
    configKnown &&
    shouldAutoLogin({
      enabled: demo.enabled,
      hasToken: !!token,
      inFlight,
      failed,
      bootAttempted,
      pathname,
    });

  useEffect(() => {
    if (!loginDue) {
      return;
    }

    bootAttempted = true;
    setInFlight(true);
    demoLogin(API_URL, "student")
      .then((newToken) => {
        setAwaitingProfile(true);
        socialAuthorize(newToken);
      })
      .catch((error) => {
        console.warn(error);
        setFailed(true);
      })
      .finally(() => setInFlight(false));
  }, [loginDue, socialAuthorize]);

  useEffect(() => {
    if (awaitingProfile && (user?.value?.id || user?.error)) {
      setAwaitingProfile(false);
    }
  }, [awaitingProfile, user?.value?.id, user?.error]);

  // Logged out: wait for the config and the demo login. Just logged in: wait for the profile
  // that private routes check.
  const waiting = token
    ? awaitingProfile
    : !configKnown || inFlight || loginDue;

  return (
    <>
      {waiting ? <Loader /> : children}
      {demo.enabled && <DemoBadge adminUrl={demo.adminUrl} />}
    </>
  );
};

const DemoBadge: React.FC<{ adminUrl: string | null }> = ({ adminUrl }) => {
  const { t } = useTranslation();
  const href =
    adminUrl ??
    (typeof window !== "undefined"
      ? siblingAppUrl(window.location, "app", "admin")
      : null);

  return (
    <aside className={styles.badge} aria-label={t("DemoMode.Label")}>
      <span>{t("DemoMode.Badge")}</span>
      {href && (
        <a
          className={styles.link}
          href={href}
          target="_blank"
          rel="noopener noreferrer"
        >
          {t("DemoMode.OpenAdmin")}
        </a>
      )}
    </aside>
  );
};

export default DemoModeGate;
