/// <reference types="astro/client" />

declare namespace App {
  interface Locals {
    /** Tenant from the Host header; null on hosts without a tenant. */
    tenant: import("@ulams/sdk").Tenant | null;
    /** The request is for the platform host (product landing), not a tenant. */
    platform: boolean;
    /** The browser reached the site over https (decides the `__Host-` cookie names). */
    secure: boolean;
    /** Session token (httpOnly cookie), set on /learn and /bff routes. */
    token: string | null;
    /** How the session was obtained on this request. */
    sessionVia: "cookie" | "demo" | "password" | null;
    /** Author token for the Course Builder studio (httpOnly cookie), set on /studio routes. */
    authorToken: string | null;
    /** Language of the learner page being rendered (`en`, `pl`, `zh`); catalogue components read it through `pageLocale`. */
    uiLocale?: string;
    /** The tenant's logo from its public settings (`theme.logo`), read by the catalogue's site header; null when it has none. */
    tenantLogo?: import("./lib/tenant-logo.ts").TenantLogo | null;
  }
}
