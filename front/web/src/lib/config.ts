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
} from "astro:env/server";

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
  demoStudentEmail: DEMO_STUDENT_EMAIL || "student1@{slug}.ulams.app",
  /** Never logged or sent to the browser. */
  demoStudentPassword: DEMO_STUDENT_PASSWORD || "",
};
