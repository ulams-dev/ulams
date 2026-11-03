/**
 * POST /learn/:course/variant: the version picker, the hint to switch and the placement result's "Use this version".
 * Plain form posts (no JavaScript needed): validates the form, calls the variant API with the learner's own session
 * and goes back to the page the form was on with a status in the query string, landing on the status message.
 * The API decides whether the learner may switch; nothing here grants or checks access. Cross-site posts are
 * refused by the middleware.
 */
import type { APIRoute } from "astro";
import { invalidateProgress } from "../../../lib/data.ts";
import { safeReturn } from "../../../lib/presentation.ts";
import { applyChoice, flashQuery, parseChooseForm } from "../../../lib/variants.ts";

export const POST: APIRoute = async ({ params, request, locals, redirect }) => {
  const tenant = locals.tenant;
  const courseId = Number(params.courseId);
  if (!tenant || !Number.isInteger(courseId) || courseId <= 0) return redirect("/", 303);
  const form = await request.formData().catch(() => null);
  const back = safeReturn(form?.get("next"), courseId);
  if (!locals.token) return redirect(`/login?next=${encodeURIComponent(back)}`, 303);
  const input = form ? parseChooseForm(form) : null;
  const outcome = input ? await applyChoice(tenant, locals.token, courseId, input) : ({ kind: "failed" } as const);
  // a switch can carry completion over: the next page must not show the progress of before
  if (outcome.kind === "switched") invalidateProgress(tenant);
  return redirect(`${back}?${flashQuery(outcome)}#variant-status`, 303);
};

// the form is a POST-only route; anything else goes back to the course
export const GET: APIRoute = ({ params, redirect }) => redirect(Number(params.courseId) > 0 ? `/courses/${Number(params.courseId)}` : "/", 303);
