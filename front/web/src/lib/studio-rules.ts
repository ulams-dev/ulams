/**
 * Which builder calls the BFF forwards (prefix /api/admin/course-builder). The API checks the
 * permission and the session owner; this list keeps the BFF from being an open proxy.
 */
const ID = "[0-9a-z]{26}";
export const STUDIO_RULES: Array<{ method: string; pattern: RegExp }> = [
  { method: "GET", pattern: /^\/sessions$/ },
  { method: "POST", pattern: /^\/sessions$/ },
  { method: "GET", pattern: new RegExp(`^/sessions/${ID}$`) },
  { method: "DELETE", pattern: new RegExp(`^/sessions/${ID}$`) },
  { method: "POST", pattern: new RegExp(`^/sessions/${ID}/sources$`) },
  { method: "GET", pattern: new RegExp(`^/sessions/${ID}/sources/${ID}$`) },
  { method: "GET", pattern: /^\/fragments\/frg_[a-z2-7]{12}$/ },
  { method: "GET", pattern: new RegExp(`^/sessions/${ID}/brief$`) },
  { method: "PUT", pattern: new RegExp(`^/sessions/${ID}/brief$`) },
  { method: "POST", pattern: new RegExp(`^/sessions/${ID}/runs$`) },
  { method: "GET", pattern: new RegExp(`^/sessions/${ID}/events$`) },
  { method: "POST", pattern: new RegExp(`^/runs/${ID}/cancel$`) },
  { method: "POST", pattern: new RegExp(`^/runs/${ID}/steps/${ID}/retry$`) },
  { method: "GET", pattern: new RegExp(`^/sessions/${ID}/versions$`) },
  { method: "GET", pattern: new RegExp(`^/versions/${ID}$`) },
  { method: "GET", pattern: new RegExp(`^/versions/${ID}/diff$`) },
  { method: "POST", pattern: new RegExp(`^/versions/${ID}/(approve|reject|restore)$`) },
  { method: "POST", pattern: new RegExp(`^/sessions/${ID}/(undo|redo|apply|publish|new-site)$`) },
  { method: "GET", pattern: new RegExp(`^/sessions/${ID}/usage$`) },
  { method: "GET", pattern: new RegExp(`^/sessions/${ID}/publish-check$`) },
  { method: "GET", pattern: new RegExp(`^/sessions/${ID}/citations$`) },
  { method: "POST", pattern: new RegExp(`^/sessions/${ID}/outline$`) },
  { method: "POST", pattern: new RegExp(`^/sessions/${ID}/elements/${ID}/variants$`) },
];

export function isStudioCall(method: string, path: string): boolean {
  return STUDIO_RULES.some((r) => r.method === method.toUpperCase() && r.pattern.test(path));
}

/**
 * Living Course calls (/api/admin/living-course). The browser reaches them as
 * /studio/api/living-course/…; the BFF forwards the part after the prefix. A multipart POST uploads
 * a new version of a source. The API checks the author and the permission; this list keeps the
 * BFF from being an open proxy.
 */
export const LIVING_COURSE_PREFIX = "/living-course";
export const LIVING_COURSE_RULES: Array<{ method: string; pattern: RegExp }> = [
  { method: "GET", pattern: new RegExp(`^/sessions/${ID}/sources$`) },
  { method: "GET", pattern: new RegExp(`^/sources/${ID}/revisions$`) },
  { method: "POST", pattern: new RegExp(`^/sources/${ID}/revisions$`) },
  { method: "GET", pattern: new RegExp(`^/revisions/${ID}$`) },
  { method: "GET", pattern: new RegExp(`^/revisions/${ID}/changes$`) },
  { method: "GET", pattern: new RegExp(`^/sessions/${ID}/staleness$`) },
  { method: "GET", pattern: new RegExp(`^/sessions/${ID}/proposals$`) },
  { method: "GET", pattern: new RegExp(`^/proposals/${ID}$`) },
  { method: "POST", pattern: new RegExp(`^/proposals/${ID}/(analyse|accept-all|reject|apply)$`) },
  { method: "POST", pattern: new RegExp(`^/proposals/${ID}/items/${ID}/(accept|reject|reset|regenerate)$`) },
  // connect a Git repository or web pages, check now, rotate the webhook secret, disconnect
  { method: "GET", pattern: /^\/connectors$/ },
  { method: "POST", pattern: new RegExp(`^/sessions/${ID}/sources/connect$`) },
  { method: "POST", pattern: new RegExp(`^/connections/${ID}/(check|webhook-secret)$`) },
  { method: "DELETE", pattern: new RegExp(`^/connections/${ID}$`) },
  // the note learners see under "updated since you completed it", and the learner notice settings of a connection
  { method: "PUT", pattern: new RegExp(`^/proposals/${ID}/learner-note$`) },
  { method: "PUT", pattern: new RegExp(`^/connections/${ID}$`) },
  // audit trail: read, verify the hash chain, export (all GET; the trail cannot be changed from here)
  { method: "GET", pattern: new RegExp(`^/sessions/${ID}/audit$`) },
  { method: "GET", pattern: new RegExp(`^/sessions/${ID}/audit/(verify|export)$`) },
];

/** `path` is relative to the BFF root, e.g. `/living-course/sources/{id}/revisions`. */
export function isLivingCourseCall(method: string, path: string): boolean {
  if (!path.startsWith(`${LIVING_COURSE_PREFIX}/`)) return false;
  const rest = path.slice(LIVING_COURSE_PREFIX.length);
  return LIVING_COURSE_RULES.some((r) => r.method === method.toUpperCase() && r.pattern.test(rest));
}

/**
 * Learner Insights analytics for authors (/api/admin/learner-insights). The browser reaches them as
 * /studio/api/learner-insights/…; the BFF forwards the part after the prefix. `GET /me` is the author's own
 * learner endpoint (/api/learner-insights/me): it only tells whether the module is on for the academy. The
 * API checks authorship and the permissions (individual data has its own); this list keeps the BFF from
 * being an open proxy. Everything is read-only except "Propose an improvement" (ADR 0181), which creates a proposal
 * the author reviews; the element key is a Course Builder element id or an encoded `topic:`/`question:` key.
 */
export const INSIGHTS_PREFIX = "/learner-insights";
export const INSIGHTS_RULES: Array<{ method: string; pattern: RegExp }> = [
  { method: "GET", pattern: /^\/me$/ },
  { method: "GET", pattern: /^\/courses\/\d{1,10}\/(summary|elements|learners)$/ },
  { method: "GET", pattern: /^\/learners\/\d{1,10}\/courses\/\d{1,10}\/timeline$/ },
  { method: "POST", pattern: /^\/courses\/\d{1,10}\/elements\/(?:[0-9A-Za-z]{26}|(?:topic|question)(?::|%3A)\d{1,10})\/improve$/ },
];

/** `path` is relative to the BFF root, e.g. `/learner-insights/courses/3/summary`. */
export function isInsightsCall(method: string, path: string): boolean {
  if (!path.startsWith(`${INSIGHTS_PREFIX}/`)) return false;
  const rest = path.slice(INSIGHTS_PREFIX.length);
  return INSIGHTS_RULES.some((r) => r.method === method.toUpperCase() && r.pattern.test(rest));
}

/** The API path of an allowed Insights call (`path` as for `isInsightsCall`). */
export function insightsTarget(path: string): string {
  const rest = path.slice(INSIGHTS_PREFIX.length);
  return rest === "/me" ? "/api/learner-insights/me" : `/api/admin/learner-insights${rest}`;
}
