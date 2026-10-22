import type { Tenant } from "@ulams/sdk";
import { tenantFetch } from "./api-transport.ts";
import { exchangeCode, type Exchange } from "./social-login.ts";

/**
 * Single sign-on through the identity providers a tenant configured (OpenID Connect, ADR 0120/0121).
 * The browser flow is the one of the social login (lib/login-flow.ts): verifier cookie, challenge to the
 * API, one-time code back. What differs is where the buttons come from (the tenant's own list of
 * enabled providers) and which API endpoints are called.
 */
export const SSO_CALLBACK_PATH = "/auth/sso/callback";
const SLUG = /^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$/;

export interface SsoProvider {
  slug: string;
  name: string;
}

export const isSsoSlug = (value: string | undefined): value is string => !!value && SLUG.test(value);

/**
 * The enabled providers of the tenant, for the login buttons. Never throws: when the API is slow, down
 * or answers something unexpected the page simply shows no single sign-on buttons.
 */
export async function listProviders(tenant: Pick<Tenant, "apiUrl">, fetcher: typeof fetch = tenantFetch(tenant)): Promise<SsoProvider[]> {
  try {
    const response = await fetcher(new URL("/api/identity/providers", tenant.apiUrl), {
      headers: { Accept: "application/json" },
      signal: AbortSignal.timeout(3_000),
    });
    if (!response.ok) return [];
    const body = (await response.json()) as { data?: unknown };
    if (!Array.isArray(body.data)) return [];
    const providers: SsoProvider[] = [];
    for (const row of body.data as Array<Record<string, unknown>>) {
      if (row && row.protocol === "oidc" && typeof row.slug === "string" && isSsoSlug(row.slug) && typeof row.name === "string" && row.name.trim() !== "") {
        providers.push({ slug: row.slug, name: row.name.trim().slice(0, 120) });
      }
      if (providers.length >= 10) break;
    }
    return providers;
  } catch {
    return [];
  }
}

/** The tenant API URL that starts the sign-in; `returnUrl` is this site's callback (the API allow-lists it). */
export function ssoStartUrl(tenant: Pick<Tenant, "apiUrl">, slug: string, returnUrl: string, challenge: string): string {
  const url = new URL(`/api/identity/sso/${slug}/start`, tenant.apiUrl);
  url.searchParams.set("return_to", returnUrl);
  url.searchParams.set("challenge", challenge);
  return url.toString();
}

/** Redeems the one-time code at the identity exchange endpoint. Never throws. */
export function exchangeSsoCode(tenant: Pick<Tenant, "apiUrl">, code: string, verifier: string, fetcher?: typeof fetch): Promise<Exchange> {
  return exchangeCode(tenant, code, verifier, fetcher ?? tenantFetch(tenant), "/api/identity/sso/exchange");
}
