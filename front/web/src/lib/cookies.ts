/**
 * Cookie naming and options for the BFF sessions (pure helpers; the settings come from config.ts).
 *
 * ULAMS production runs the course content origin on a subdomain of the app's own site
 * (`*.content.ulams.app` next to `*.app.ulams.app`, ADR 0014, amended 2026-10-09). Package code on
 * a same-site origin can set cookies for the parent domain (cookie tossing) and its requests carry
 * SameSite=Lax/Strict cookies. Every session cookie therefore uses the `__Host-` prefix: the browser
 * only accepts it when it is Secure, has Path=/ and no Domain, so a sibling subdomain can neither
 * overwrite nor shadow it. `Domain` is never set.
 */
export type SecureMode = "auto" | "true" | "false";

export const SESSION_BASE = "ulams_session";
export const AUTHOR_BASE = "ulams_author";
export const HOST_PREFIX = "__Host-";

export function parseSecureMode(value: string | undefined | null): SecureMode {
  const v = (value ?? "").trim().toLowerCase();
  return v === "true" || v === "false" ? v : "auto";
}

/**
 * Is this request (as the browser sees it) on https? `auto` trusts x-forwarded-proto set by the TLS
 * terminating proxy (Caddy) and the request protocol; `true` / `false` force it (a deployment
 * behind a proxy that does not forward the protocol, or a plain-http trial install).
 */
export function isSecureRequest(headers: Headers, protocol: string, mode: SecureMode = "auto"): boolean {
  if (mode === "true") return true;
  if (mode === "false") return false;
  const forwarded = headers.get("x-forwarded-proto")?.split(",")[0]?.trim().toLowerCase();
  if (forwarded) return forwarded === "https";
  return protocol === "https:";
}

/**
 * `__Host-<base>` when secure. Over plain http (development on *.localhost) the browser would
 * reject the prefix, so the fallback name is `<fallbackPrefix><base>` (default: the bare name).
 */
export function cookieName(base: string, secure: boolean, fallbackPrefix = ""): string {
  return secure ? `${HOST_PREFIX}${base}` : `${fallbackPrefix}${base}`;
}

export interface CookieSetOptions {
  httpOnly: true;
  sameSite: "lax";
  secure: boolean;
  path: "/";
  expires: Date;
}

/** Options for a session cookie: never a Domain attribute, which `__Host-` forbids. */
export function sessionCookieOptions(secure: boolean, expiresAt: number): CookieSetOptions {
  return { httpOnly: true, sameSite: "lax", secure, path: "/", expires: new Date(expiresAt) };
}
