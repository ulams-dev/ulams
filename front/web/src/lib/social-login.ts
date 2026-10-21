import { createHash, randomBytes } from "node:crypto";
import type { Tenant } from "@ulams/sdk";
import { accountStrings } from "@ulams/ui/account-strings";
import { tenantFetch } from "./api-transport.ts";

/**
 * Sign-in with Google or Facebook through the tenant API (ADR 0121).
 *
 * The page never sees an access token in a URL. It keeps a random verifier in an httpOnly cookie,
 * sends only its SHA-256 challenge to the API, and when the browser comes back with a one-time
 * `code` it redeems code + verifier server to server. A code that leaks (history, logs, Referer) is
 * useless without the verifier, which only the browser that started the login has.
 */
export const SOCIAL_PROVIDERS = ["google", "facebook"] as const;
export type SocialProvider = (typeof SOCIAL_PROVIDERS)[number];

export const SOCIAL_COOKIE_BASE = "ulams_sso";
export const SOCIAL_CALLBACK_PATH = "/auth/social/callback";
/** Ten minutes: as long as the API keeps the login state. */
export const SOCIAL_COOKIE_MAX_AGE = 600;

/** The login pages answer with these: nothing about them is cached or sent along in a Referer. */
export const SOCIAL_HEADERS: Readonly<Record<string, string>> = {
  "Cache-Control": "private, no-store",
  "Referrer-Policy": "no-referrer",
};

export const isSocialPath = (pathname: string): boolean => pathname.startsWith("/auth/social/");

export function isSocialProvider(value: string | undefined): value is SocialProvider {
  return (SOCIAL_PROVIDERS as readonly string[]).includes(value ?? "");
}

/** Providers switched on for this deployment: `ULAMS_SOCIAL_LOGIN=google,facebook`; unknown names are dropped. */
export function enabledProviders(value: string | undefined | null): SocialProvider[] {
  return [...new Set((value ?? "").split(",").map((s) => s.trim().toLowerCase()))].filter(isSocialProvider);
}

const base64Url = (bytes: Buffer): string => bytes.toString("base64url");

/** 43 characters of base64url, 256 bits. */
export function newVerifier(): string {
  return base64Url(randomBytes(32));
}

/** S256, as the API checks it: base64url(sha256(verifier)). */
export function challengeFor(verifier: string): string {
  return base64Url(createHash("sha256").update(verifier).digest());
}

/** Only same-site paths: `//host`, `/\host` and absolute URLs would bounce the learner to another site. */
export function safeNext(next: string | null | undefined): string {
  const plain = !!next && [...next].every((c) => c.charCodeAt(0) > 0x1f && c !== "\\");
  return plain && next!.startsWith("/") && !next!.startsWith("//") ? next! : "/";
}

export interface SocialCookie {
  verifier: string;
  next: string;
}

export const encodeCookie = (value: SocialCookie): string => Buffer.from(JSON.stringify(value)).toString("base64url");

/** Null for anything that is not a cookie this module wrote. */
export function decodeCookie(raw: string | undefined): SocialCookie | null {
  if (!raw || raw.length > 2048) return null;
  try {
    const parsed = JSON.parse(Buffer.from(raw, "base64url").toString("utf8")) as Partial<SocialCookie>;
    if (typeof parsed.verifier !== "string" || !/^[A-Za-z0-9._~-]{43,128}$/.test(parsed.verifier)) return null;
    return { verifier: parsed.verifier, next: safeNext(parsed.next) };
  } catch {
    return null;
  }
}

/** The tenant API URL that starts the login; `returnUrl` is this site's callback (the API allow-lists it). */
export function startUrl(tenant: Pick<Tenant, "apiUrl">, provider: SocialProvider, returnUrl: string, challenge: string): string {
  const url = new URL(`/api/auth/social/${provider}`, tenant.apiUrl);
  url.searchParams.set("return_url", returnUrl);
  url.searchParams.set("challenge", challenge);
  return url.toString();
}

/** Reasons the API puts in `?error=`; anything else reads as a generic failure. */
const MESSAGES: Record<string, string> = {
  provider_denied: "Sign-in was cancelled.",
  provider_failed: "The sign-in provider did not accept the request. Try again.",
  link_required: "An account with this e-mail address already exists. Sign in with your password first.",
  email_unverified: "Confirm your e-mail address with the link we sent you, then sign in again.",
  account_unavailable: "This account is not available.",
  email_required: "The provider did not share an e-mail address. Sign in with your e-mail and password instead.",
};

export const GENERIC_ERROR = "Sign-in failed. Try again.";

export function errorMessage(reason: string | null | undefined, lang = "en"): string {
  if (lang === "en") return (reason && MESSAGES[reason]) || GENERIC_ERROR;
  const t = accountStrings(lang);
  return (reason && (t.socialErrors as Record<string, string>)[reason]) || t.socialGeneric;
}

export type Exchange =
  | { ok: true; complete: true; token: string; expiresAt: number }
  | { ok: true; complete: false }
  | { ok: false };

/** Redeems the one-time code. Never throws: any failure is `{ ok: false }` (the cause stays in the API's log). */
export async function exchangeCode(
  tenant: Pick<Tenant, "apiUrl">,
  code: string,
  verifier: string,
  fetcher: typeof fetch = tenantFetch(tenant)
): Promise<Exchange> {
  try {
    const response = await fetcher(new URL("/api/auth/social/exchange", tenant.apiUrl), {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ code, verifier }),
      signal: AbortSignal.timeout(10_000),
    });
    if (!response.ok) return { ok: false };
    const body = (await response.json()) as { data?: { complete?: boolean; token?: string; expires_at?: string | null } };
    const data = body.data;
    if (data?.complete === false) return { ok: true, complete: false };
    if (!data?.token) return { ok: false };
    const parsed = data.expires_at ? Date.parse(data.expires_at) : NaN;
    return { ok: true, complete: true, token: data.token, expiresAt: Number.isNaN(parsed) ? Date.now() + 86_400_000 : parsed };
  } catch {
    return { ok: false };
  }
}
