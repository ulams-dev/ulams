/**
 * Browser side of the social login (ADR 0121). The page keeps a random verifier in sessionStorage,
 * sends only its SHA-256 challenge to the API and redeems the one-time `code` of the redirect
 * together with the verifier. No access token ever appears in a URL.
 */
export const SOCIAL_VERIFIER_KEY = "ulams.social.verifier";

export type SocialProvider = "google" | "facebook";

export interface SocialStorage {
  getItem(key: string): string | null;
  setItem(key: string, value: string): void;
  removeItem(key: string): void;
}

const toBase64Url = (bytes: Uint8Array): string => {
  let binary = "";
  bytes.forEach((b) => {
    binary += String.fromCharCode(b);
  });
  return btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
};

/** 43 characters of base64url, 256 bits. */
export function newVerifier(): string {
  return toBase64Url(crypto.getRandomValues(new Uint8Array(32)));
}

/** S256: base64url(sha256(verifier)). */
export async function challengeFor(verifier: string): Promise<string> {
  const digest = await crypto.subtle.digest("SHA-256", new TextEncoder().encode(verifier));
  return toBase64Url(new Uint8Array(digest));
}

/**
 * The URL that starts a login. Keeps a fresh verifier in `storage`; `returnUrl` must be an origin the
 * tenant allows (its front end, admin or trusted origins), or the API refuses it.
 */
export async function startSocialLoginUrl(
  apiUrl: string,
  provider: SocialProvider,
  returnUrl: string,
  storage: SocialStorage = window.sessionStorage
): Promise<string> {
  const verifier = newVerifier();
  storage.setItem(SOCIAL_VERIFIER_KEY, verifier);
  const query = new URLSearchParams({ return_url: returnUrl, challenge: await challengeFor(verifier) });
  return `${apiUrl}/api/auth/social/${provider}?${query.toString()}`;
}

export interface SocialCallback {
  code: string | null;
  error: string | null;
}

/** `?code=...&complete=true` or `?error=...` of the page the API redirected to (also inside a hash route). */
export function readSocialCallback(search: string): SocialCallback {
  const params = new URLSearchParams(search.startsWith("?") ? search : `?${search}`);
  return { code: params.get("code"), error: params.get("error") };
}

/** Takes the verifier out of storage: a login can be completed once. */
export function takeVerifier(storage: SocialStorage = window.sessionStorage): string | null {
  const verifier = storage.getItem(SOCIAL_VERIFIER_KEY);
  storage.removeItem(SOCIAL_VERIFIER_KEY);
  return verifier;
}
