import {
  DEMO_STUDENT_EMAIL,
  DEMO_STUDENT_PASSWORD,
  ULAMS_ADMIN_URL,
  ULAMS_CACHE_TTL,
  ULAMS_DEFAULT_TENANT,
  ULAMS_DEMO_TENANTS,
  ULAMS_PLATFORM_HOSTS,
  ULAMS_TENANT_HOSTS,
  ULAMS_WARM_TENANTS,
  ULAMS_COOKIE_SECURE,
  ULAMS_COOKIE_FALLBACK_PREFIX,
  ULAMS_LANDING_STATUS,
  CSP_ENFORCE,
  ULAMS_CONTENT_ORIGIN,
  ULAMS_STORAGE_ORIGINS,
} from "astro:env/server";
import { parseSecureMode } from "./cookies.ts";
import { parseLandingStatus } from "./landing-status.ts";
import { parseEnforce } from "./csp.ts";

/** Runtime settings (process env, see .env.example). Read once. */
export const config = {
  tenantHosts: ULAMS_TENANT_HOSTS || "{slug}.app.localhost=>http://{slug}.localhost",
  adminUrl: ULAMS_ADMIN_URL || "http://{slug}.admin.localhost",
  /** Tenant for unknown hosts (not the platform hosts); empty = tenant picker. */
  defaultTenant: ULAMS_DEFAULT_TENANT ?? "coffee",
  /** Hosts that serve the platform product landing instead of a tenant. */
  platformHosts: (ULAMS_PLATFORM_HOSTS ?? "app.localhost,localhost,127.0.0.1")
    .split(",")
    .map((s) => s.trim().toLowerCase())
    .filter(Boolean),
  /** Demo tenants shown on the platform landing. */
  demoTenants: (ULAMS_DEMO_TENANTS ?? "coffee,oncall,nightsky")
    .split(",")
    .map((s) => s.trim())
    .filter(Boolean),
  cacheTtlMs: Math.max(5, ULAMS_CACHE_TTL ?? 45) * 1000,
  warmTenants: (ULAMS_WARM_TENANTS ?? "coffee,oncall,nightsky")
    .split(",")
    .map((s) => s.trim())
    .filter(Boolean),
  /** `auto` (default) detects https from the request / x-forwarded-proto; `true`/`false` force it. */
  cookieSecure: parseSecureMode(ULAMS_COOKIE_SECURE),
  /** Cookie name prefix over plain http (dev), where `__Host-` is rejected by browsers. */
  cookieFallbackPrefix: ULAMS_COOKIE_FALLBACK_PREFIX ?? "",
  /** `final` shows every roadmap item as delivered, `actual` the honest status (see landing-status.ts). */
  landingStatus: parseLandingStatus(ULAMS_LANDING_STATUS),
  /**
   * Enforce the Content Security Policy (`CSP_ENFORCE=true`) or only report violations. Unset, it
   * enforces everywhere but in the production image (NODE_ENV=production), so developers see
   * violations at once and operators switch it on after a clean week (ADR 0044).
   */
  cspEnforce: parseEnforce(CSP_ENFORCE, process.env.NODE_ENV !== "production"),
  /** The tenant's content origin, `{slug}` replaced; empty = none (the CSP then lists no content frame source). */
  contentOrigin: ULAMS_CONTENT_ORIGIN ?? "http://{slug}.content.localhost",
  /** Origins that serve uploaded files (images, media, PDFs), comma separated. */
  storageOrigins: (ULAMS_STORAGE_ORIGINS ?? "http://storage.localhost")
    .split(",")
    .map((s) => s.trim())
    .filter(Boolean),
  demoStudentEmail: DEMO_STUDENT_EMAIL || "student1@{slug}.ulams.app",
  /** Never logged or sent to the browser. */
  demoStudentPassword: DEMO_STUDENT_PASSWORD || "",
};
