import { resolveTenant, tenantFromHost, type Tenant } from "@ulams/sdk/tenant";

export interface TenantConfig {
  tenantHosts: string;
  adminUrl: string;
  defaultTenant: string;
}

/**
 * Tenant of a request. `<slug>.app.localhost` → API `http://<slug>.localhost`. Plain
 * `localhost` (no rule matches) uses the default tenant, whose API URL is built by
 * applying the first rule to `<default>.app.localhost`.
 */
export function tenantForHost(host: string | null | undefined, cfg: TenantConfig): Tenant | null {
  const options = { pattern: cfg.tenantHosts, adminUrlTemplate: cfg.adminUrl };
  const direct = resolveTenant(host, options);
  if (direct) return direct;
  if (!cfg.defaultTenant) return null;
  const firstRule = cfg.tenantHosts.split(/[,\n]+/)[0]?.split("=>")[0]?.trim();
  if (!firstRule) return null;
  const syntheticHost = firstRule.replace("{slug}", cfg.defaultTenant);
  const match = tenantFromHost(syntheticHost, cfg.tenantHosts);
  return match ? resolveTenant(syntheticHost, options) : null;
}

/** Host (with port) of another tenant on the same front, for the tenant switcher. */
export function hostForTenant(currentHost: string, currentSlug: string, slug: string): string | null {
  const [name, port] = currentHost.split(":");
  if (!name || !name.startsWith(`${currentSlug}.`)) return null;
  return `${slug}${name.slice(currentSlug.length)}${port ? `:${port}` : ""}`;
}

/** True when the host (port ignored) is one of the platform hosts, e.g. app.localhost or localhost. */
export function isPlatformHost(host: string | null | undefined, platformHosts: string[]): boolean {
  if (!host) return false;
  const name = host.trim().toLowerCase().replace(/\.$/, "").replace(/:\d+$/, "");
  return platformHosts.includes(name);
}

/**
 * Front URL of a tenant as seen from the platform host: the first host rule with the slug,
 * on the same protocol and port as the current request (`coffee.app.localhost:4321`).
 */
export function tenantFrontUrl(slug: string, tenantHosts: string, current: URL): string | null {
  const rule = tenantHosts.split(/[,\n]+/)[0]?.split("=>")[0]?.trim();
  if (!rule || !rule.includes("{slug}")) return null;
  const host = rule.replace("{slug}", slug);
  return `${current.protocol}//${host}${current.port ? `:${current.port}` : ""}`;
}
