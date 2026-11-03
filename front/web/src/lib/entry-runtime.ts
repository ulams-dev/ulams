import { resolveTenant, type Tenant } from "@ulams/sdk/tenant";
import { tenantFetch } from "./api-transport.ts";
import { config } from "./config.ts";
import { EntryResolver } from "./entry.ts";

/** Entry points (ADR 0195): one resolver, with one cache, per process; shared by the middleware and `/go/<key>`. */
export const entries = new EntryResolver({
  platformApiUrl: config.platformApiUrl,
  fetch: config.platformApiUrl ? tenantFetch({ apiUrl: config.platformApiUrl }) : fetch,
});

/** The academy of a slug, through the first host rule, the way the warm-up does. */
export function tenantOfSlug(slug: string): Tenant | null {
  const firstRule = config.tenantHosts.split(/[,\n]+/)[0]?.split("=>")[0]?.trim() ?? "";
  if (!firstRule.includes("{slug}")) return null;
  return resolveTenant(firstRule.replace("{slug}", slug), { pattern: config.tenantHosts, adminUrlTemplate: config.adminUrl });
}
