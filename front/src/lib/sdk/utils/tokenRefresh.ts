/**
 * Passport access tokens are short-lived (5 minutes by default, setting
 * `ulams_auth.token_expiration_minutes`). The front refreshes the token a
 * minute before it expires; consumers that hold a copy of the token (the H5P
 * player frame) pick up the new one from the context.
 */

/** Seconds before expiry at which the token is refreshed. */
export const REFRESH_MARGIN_SECONDS = 60;
/** Tokens living longer than this ("remember me", a month) are not refreshed on a timer. */
export const MAX_REFRESH_DELAY_MS = 60 * 60 * 1000;

/** `exp` claim (seconds since the epoch) of a JWT, without verifying it. */
export function tokenExpiry(token: string): number | undefined {
  const payload = token.split(".")[1];
  if (!payload) return undefined;
  try {
    const base64 = payload.replace(/-/g, "+").replace(/_/g, "/");
    const padded = base64 + "=".repeat((4 - (base64.length % 4)) % 4);
    const claims = JSON.parse(atob(padded)) as { exp?: unknown };
    return typeof claims.exp === "number" && Number.isFinite(claims.exp) ? claims.exp : undefined;
  } catch {
    return undefined;
  }
}

/**
 * Milliseconds until the token should be refreshed, or undefined when no
 * timer is needed (not a JWT, no expiry, or a long-lived token).
 */
export function refreshDelayMs(token: string | null | undefined, nowMs: number): number | undefined {
  if (!token) return undefined;
  const exp = tokenExpiry(token);
  if (exp === undefined) return undefined;
  const delay = exp * 1000 - REFRESH_MARGIN_SECONDS * 1000 - nowMs;
  if (delay > MAX_REFRESH_DELAY_MS) return undefined;
  return Math.max(0, delay);
}
