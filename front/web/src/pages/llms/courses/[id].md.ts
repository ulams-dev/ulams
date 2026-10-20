import type { APIRoute } from "astro";
import { publicOrigin } from "../../../i18n/locales.ts";
import { getCourse, getSettings } from "../../../lib/data.ts";
import { academyName, courseMarkdown } from "../../../lib/llms.ts";

/** The public page of a course as Markdown (what `/courses/<id>` shows without signing in). */
export const GET: APIRoute = async ({ request, url, params, locals }) => {
  const tenant = locals.tenant;
  const id = Number(params.id);
  if (!tenant || !Number.isInteger(id) || id <= 0) return new Response("Not found", { status: 404 });

  const [course, settings] = await Promise.all([getCourse(tenant, id).catch(() => null), getSettings(tenant).catch(() => null)]);
  if (!course || course.id !== id) return new Response("Not found", { status: 404 });

  const body = courseMarkdown(course, {
    name: academyName(settings, tenant.slug),
    origin: publicOrigin(request.headers, url),
    currency: (settings?.currencies?.default as string | undefined) ?? undefined,
  });

  return new Response(body, { headers: { "Content-Type": "text/markdown; charset=utf-8", "Cache-Control": "public, max-age=900" } });
};
