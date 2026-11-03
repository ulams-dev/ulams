import { resolveTenant, type Tenant } from "@ulams/sdk/tenant";
import { config } from "./config.ts";
import { EntryLookupError, type EntryResolver } from "./entry.ts";

/**
 * Is `name` another host of this academy: its own front (by the host rules) or one of its active entry points? "Unknown"
 * means the platform could not say, which the caller treats as "try again", never as "yes".
 */
export async function isSiblingHost(tenant: Pick<Tenant, "slug">, name: string, entries: Pick<EntryResolver, "byHost">): Promise<"yes" | "no" | "unknown"> {
  const byRule = resolveTenant(name, { pattern: config.tenantHosts, adminUrlTemplate: config.adminUrl });
  if (byRule) return byRule.slug === tenant.slug ? "yes" : "no";
  try {
    return (await entries.byHost(name))?.tenant === tenant.slug ? "yes" : "no";
  } catch (error) {
    if (error instanceof EntryLookupError) return "unknown";
    throw error;
  }
}
