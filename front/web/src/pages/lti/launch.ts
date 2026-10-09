import type { APIRoute } from "astro";
import { setSessionCookie } from "../../lib/session.ts";

/**
 * Landing page of an LTI launch from another LMS (api/packages/lti, tool side,
 * LTI_TOOL_LANDING_URL = {front}/lti/launch?code=…&course=…). The one-time code is exchanged on the
 * server for an API token, which becomes the httpOnly session; the learner lands in the course.
 * Inside an LMS iframe the session cookie may be blocked as third-party; platforms that frame tools
 * should open ulams in a new window.
 */
export const GET: APIRoute = async ({ url, locals, cookies, redirect }) => {
  const tenant = locals.tenant;
  const code = url.searchParams.get("code");
  if (!tenant || !code) {
    return new Response("This link is incomplete. Open the activity in your LMS again.", { status: 400 });
  }

  const response = await fetch(`${tenant.apiUrl}/api/lti/tool/exchange`, {
    method: "POST",
    headers: { "Content-Type": "application/json", Accept: "application/json" },
    body: JSON.stringify({ code }),
    signal: AbortSignal.timeout(8000),
  }).catch(() => null);
  const body = (await response?.json().catch(() => null)) as { data?: { token?: string; course_id?: number }; message?: string } | null;
  if (!response?.ok || !body?.data?.token || !body.data.course_id) {
    return new Response(body?.message ?? "This sign-in link has expired. Open the activity in your LMS again.", {
      status: 401,
      headers: { "Content-Type": "text/plain; charset=utf-8" },
    });
  }

  setSessionCookie(cookies, body.data.token, Date.now() + 8 * 3600_000, url.protocol === "https:");

  return redirect(`/learn/${body.data.course_id}`, 303);
};
