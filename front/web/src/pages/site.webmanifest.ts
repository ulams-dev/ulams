import type { APIRoute } from "astro";
import { getSettings } from "../lib/data.ts";
import { PLATFORM_MANIFEST, tenantManifest } from "../lib/webmanifest.ts";

/** The platform host keeps the product's manifest; a tenant host gets its own name, colour and logo. */
export const GET: APIRoute = async ({ locals }) => {
  const tenant = locals.tenant;
  const settings = tenant ? await getSettings(tenant).catch(() => null) : null;
  const manifest = tenant ? tenantManifest(tenant, settings) : PLATFORM_MANIFEST;
  return new Response(JSON.stringify(manifest, null, 2), {
    headers: { "Content-Type": "application/manifest+json; charset=utf-8", "Cache-Control": "public, max-age=300" },
  });
};
