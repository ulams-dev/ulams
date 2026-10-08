/// <reference types="astro/client" />

declare namespace App {
  interface Locals {
    /** Tenant from the Host header; null on hosts without a tenant. */
    tenant: import("@ulams/sdk").Tenant | null;
    /** Session token (httpOnly cookie), set on /learn and /bff routes. */
    token: string | null;
    /** How the session was obtained on this request. */
    sessionVia: "cookie" | "demo" | "password" | null;
  }
}
