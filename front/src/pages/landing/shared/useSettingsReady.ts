import { useEffect, useRef, useState } from "react";

interface SettingsState {
  loading: boolean;
  value?: unknown;
  error?: unknown;
}

const hasContent = (value: unknown) =>
  !!value &&
  typeof value === "object" &&
  !Array.isArray(value) &&
  Object.keys(value as object).length > 0;

/**
 * True once tenant settings are known: they have content (fresh or persisted),
 * a fetch has finished, or the request failed. A short timeout stops a broken
 * API from blocking the home page forever.
 */
export function useSettingsReady(settings: SettingsState, timeoutMs = 4000): boolean {
  const seenLoading = useRef(false);
  const [timedOut, setTimedOut] = useState(false);
  if (settings.loading) seenLoading.current = true;

  useEffect(() => {
    const timer = window.setTimeout(() => setTimedOut(true), timeoutMs);
    return () => window.clearTimeout(timer);
  }, [timeoutMs]);

  return (
    hasContent(settings.value) ||
    !!settings.error ||
    (seenLoading.current && !settings.loading) ||
    timedOut
  );
}
