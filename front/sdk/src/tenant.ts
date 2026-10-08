/**
 * Tenant resolution from the request host. The host rules are the ones the old front and
 * the admin use (front/src/lib/tenant/resolveApiUrl.ts), re-exported so there is one
 * implementation; it moves here when the old front is removed.
 */
import {
  DEFAULT_ADMIN_TENANT_PATTERN,
  DEFAULT_FRONT_TENANT_PATTERN,
  PRODUCTION_FRONT_TENANT_PATTERN,
  RESERVED_SLUGS,
  matchTenantSlug,
  parseTenantHostPattern,
  tenantFromHost,
  type TenantMatch,
} from "../../src/lib/tenant/resolveApiUrl.ts";

export {
  DEFAULT_ADMIN_TENANT_PATTERN,
  DEFAULT_FRONT_TENANT_PATTERN,
  PRODUCTION_FRONT_TENANT_PATTERN,
  RESERVED_SLUGS,
  matchTenantSlug,
  parseTenantHostPattern,
  tenantFromHost,
};
export type { TenantMatch };

/** Default admin URL template for a tenant slug (`{slug}.admin.localhost`). */
export const DEFAULT_ADMIN_URL_TEMPLATE = "http://{slug}.admin.localhost";

export interface Tenant extends TenantMatch {
  /** Admin panel of the tenant. */
  adminUrl: string;
}

export interface ResolveTenantOptions {
  /** `host=>api` rules; default `{slug}.app.localhost=>http://{slug}.localhost`. */
  pattern?: string | null;
  /** Admin URL template with `{slug}`. */
  adminUrlTemplate?: string | null;
  /** Used when no rule matches (plain `localhost`): a fixed tenant slug + API. */
  fallback?: { slug: string; apiUrl: string } | null;
}

const fill = (template: string, slug: string): string => template.split("{slug}").join(slug).replace(/\/+$/, "");

/** Resolves the tenant of a request host, e.g. `coffee.app.localhost:4321` → coffee. */
export function resolveTenant(host: string | null | undefined, options: ResolveTenantOptions = {}): Tenant | null {
  const pattern = options.pattern && options.pattern.trim() !== "" ? options.pattern : DEFAULT_FRONT_TENANT_PATTERN;
  const match = tenantFromHost(host ?? "", pattern) ?? options.fallback ?? null;
  if (!match) return null;
  return {
    slug: match.slug,
    apiUrl: match.apiUrl.replace(/\/+$/, ""),
    adminUrl: fill(options.adminUrlTemplate || DEFAULT_ADMIN_URL_TEMPLATE, match.slug),
  };
}
