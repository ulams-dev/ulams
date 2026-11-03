import type { APIRoute } from "astro";
import { publicOrigin } from "../i18n/locales.ts";
import { getCourses, getSettings } from "../lib/data.ts";
import { academyLlmsTxt, academyName, platformLlmsTxt } from "../lib/llms.ts";

const headers = { "Content-Type": "text/plain; charset=utf-8", "Cache-Control": "public, max-age=900" };

/** An index of the academy's public courses for agents (llmstxt.org); the platform host gets the product's own. */
export const GET: APIRoute = async ({ request, url, locals }) => {
  const origin = publicOrigin(request.headers, url);
  if (locals.platform) return new Response(platformLlmsTxt(origin), { headers });
  const tenant = locals.tenant;
  if (!tenant) return new Response("Not found", { status: 404 });

  const [settings, courses] = await Promise.all([getSettings(tenant).catch(() => null), getCourses(tenant).catch(() => [])]);
  const body = academyLlmsTxt({
    name: academyName(settings, tenant.slug),
    origin,
    currency: (settings?.currencies?.default as string | undefined) ?? undefined,
    courses,
  });

  return new Response(body, { headers });
};
