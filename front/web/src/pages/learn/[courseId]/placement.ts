/**
 * POST /learn/:course/placement: the placement check's answers. A plain form post: the API scores it by code (the
 * answers are not stored) and the page comes back showing the result, which is advisory. Cross-site posts are
 * refused by the middleware.
 */
import type { APIRoute } from "astro";
import { parsePlacementForm, scorePlacement } from "../../../lib/variants.ts";

export const POST: APIRoute = async ({ params, request, locals, redirect }) => {
  const tenant = locals.tenant;
  const courseId = Number(params.courseId);
  if (!tenant || !Number.isInteger(courseId) || courseId <= 0) return redirect("/", 303);
  const back = `/courses/${courseId}/placement`;
  if (!locals.token) return redirect(`/login?next=${encodeURIComponent(back)}`, 303);
  const form = await request.formData().catch(() => null);
  const attempt = form ? await scorePlacement(tenant, locals.token, courseId, parsePlacementForm(form)) : null;
  return redirect(attempt ? `${back}?result=1#placement-result` : `${back}?failed=1#placement-failed`, 303);
};

export const GET: APIRoute = ({ params, redirect }) => redirect(Number(params.courseId) > 0 ? `/courses/${Number(params.courseId)}/placement` : "/", 303);
