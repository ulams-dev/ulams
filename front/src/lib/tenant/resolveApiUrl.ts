/**
 * Tenant-aware API base URL resolution, shared by front (Vite) and admin (umi).
 *
 * A tenant is recognised from the page host name with a pattern such as
 * `{slug}.app.localhost=>http://{slug}.localhost`: the left side is the host the
 * app is served from, the right side is the API base URL template. `{slug}` is a
 * single DNS label. Several rules can be given, separated by commas or new lines;
 * the first match wins. `off` (or `none`) disables host-based resolution.
 *
 * Examples
 * - local front:  `{slug}.app.localhost=>http://{slug}.localhost` (default)
 * - local admin:  `{slug}.admin.localhost=>http://{slug}.localhost` (default)
 * - production:   `{slug}.ulams.app=>https://{slug}.api.ulams.app`
 *
 * This module has no imports so that it can run under `node --test` as-is.
 */

export interface TenantHostRule {
  /** Host name pattern with one `{slug}` placeholder, e.g. `{slug}.app.localhost`. */
  host: string;
  /** API base URL template, e.g. `http://{slug}.localhost`. */
  api: string;
}

export interface TenantMatch {
  slug: string;
  apiUrl: string;
}

export const DEFAULT_FRONT_TENANT_PATTERN =
  "{slug}.app.localhost=>http://{slug}.localhost";

export const DEFAULT_ADMIN_TENANT_PATTERN =
  "{slug}.admin.localhost=>http://{slug}.localhost";

/** Documented production form; not a default because it would also match `stage.ulams.app` etc. */
export const PRODUCTION_FRONT_TENANT_PATTERN =
  "{slug}.ulams.app=>https://{slug}.api.ulams.app";

/** Sub-domains that are never tenants. */
export const RESERVED_SLUGS: ReadonlyArray<string> = [
  "www",
  "api",
  "app",
  "admin",
  "stage",
  "staging",
];

const SLUG = /^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/;
const DISABLED = ["off", "none", "false", "0"];
const PLACEHOLDER = "{slug}";

const isBlank = (value: unknown): boolean =>
  value === undefined ||
  value === null ||
  (typeof value === "string" &&
    (value.trim() === "" || value.trim() === "null" || value.trim() === "undefined"));

/** Parses `host=>api` rules. Malformed rules are skipped. */
export function parseTenantHostPattern(
  pattern: string | null | undefined
): TenantHostRule[] {
  if (isBlank(pattern)) return [];
  const text = String(pattern).trim();
  if (DISABLED.includes(text.toLowerCase())) return [];

  return text
    .split(/[,\n]+/)
    .map((rule) => rule.trim())
    .filter(Boolean)
    .map((rule) => {
      const [host, api] = rule.split("=>").map((part) => part.trim());
      return { host: (host ?? "").toLowerCase(), api: api ?? "" };
    })
    .filter(
      (rule) =>
        rule.api !== "" &&
        rule.host.split(PLACEHOLDER).length === 2 &&
        rule.api.includes(PLACEHOLDER)
    );
}

const normaliseHost = (hostname: string): string =>
  hostname.trim().toLowerCase().replace(/\.$/, "").replace(/:\d+$/, "");

/** Returns the tenant slug when `hostname` matches `hostPattern`, otherwise null. */
export function matchTenantSlug(
  hostname: string,
  hostPattern: string
): string | null {
  const host = normaliseHost(hostname);
  const [prefix, suffix] = hostPattern.toLowerCase().split(PLACEHOLDER);
  if (prefix === undefined || suffix === undefined) return null;
  if (!host.startsWith(prefix) || !host.endsWith(suffix)) return null;
  if (host.length <= prefix.length + suffix.length) return null;

  const slug = host.slice(prefix.length, host.length - suffix.length);
  if (!SLUG.test(slug) || RESERVED_SLUGS.includes(slug)) return null;
  return slug;
}

/** Finds the first rule matching `hostname` and fills its API template. */
export function tenantFromHost(
  hostname: string | null | undefined,
  pattern: string | null | undefined
): TenantMatch | null {
  if (isBlank(hostname)) return null;
  for (const rule of parseTenantHostPattern(pattern)) {
    const slug = matchTenantSlug(String(hostname), rule.host);
    if (slug) {
      return {
        slug,
        apiUrl: rule.api.split(PLACEHOLDER).join(slug).replace(/\/+$/, ""),
      };
    }
  }
  return null;
}

export interface ResolveApiUrlInput {
  /** Value injected at runtime into index.html (`window.VITE_APP_API_URL`, `window.REACT_APP_API_URL`). */
  runtime?: string | null;
  /** `window.location.hostname`. */
  hostname?: string | null;
  /** Tenant host pattern; `undefined`/empty uses `defaultPattern`. */
  pattern?: string | null;
  defaultPattern?: string;
  /** Build-time value (`VITE_APP_PUBLIC_API_URL`, `REACT_APP_API_URL`): the platform API. */
  buildTime?: string | null;
}

/**
 * Order: a runtime-injected URL wins (one deployment per tenant); otherwise a
 * host that matches the tenant pattern gives the tenant API; otherwise the
 * build-time URL (the platform API, e.g. `http://api.localhost` on plain localhost).
 */
export function resolveApiUrl({
  runtime,
  hostname,
  pattern,
  defaultPattern = DEFAULT_FRONT_TENANT_PATTERN,
  buildTime,
}: ResolveApiUrlInput): string | null {
  if (!isBlank(runtime)) return String(runtime);
  const tenant = tenantFromHost(
    hostname,
    isBlank(pattern) ? defaultPattern : pattern
  );
  if (tenant) return tenant.apiUrl;
  if (!isBlank(buildTime)) return String(buildTime);
  return null;
}
