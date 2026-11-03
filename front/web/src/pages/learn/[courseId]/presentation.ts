/**
 * POST /learn/:course/presentation: the "Lesson view" form. Plain form post (no JavaScript needed): validates
 * the choices, forwards them to the learner-insights presentation API with the learner's own session, and goes
 * back to the page the form was on. Cross-site posts are refused by the middleware.
 */
import type { APIRoute } from "astro";
import { parsePresentationForm, safeReturn, savePresentationChange } from "../../../lib/presentation.ts";

export const POST: APIRoute = async ({ params, request, locals, redirect }) => {
  const tenant = locals.tenant;
  const courseId = Number(params.courseId);
  if (!tenant || !Number.isInteger(courseId) || courseId <= 0) return redirect("/", 303);
  const form = await request.formData().catch(() => null);
  const back = safeReturn(form?.get("next"), courseId);
  if (!locals.token) return redirect(`/login?next=${encodeURIComponent(back)}`, 303);
  const action = form ? parsePresentationForm(form) : null;
  const saved = action ? await savePresentationChange(tenant, locals.token, courseId, action) : false;
  return redirect(`${back}?lesson-view=${saved ? "ok" : "failed"}`, 303);
};

// the form is a POST-only route; anything else goes back to the course
export const GET: APIRoute = ({ params, redirect }) => redirect(Number(params.courseId) > 0 ? `/learn/${Number(params.courseId)}` : "/", 303);
