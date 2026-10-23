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
  { method: "POST", pattern: new RegExp(`^/proposals/${ID}/items/${ID}/(accept|reject|reset|regenerate|suggest-lesson)$`) },
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

/**
 * Course versions (/api/admin/course-versions, ADR 0192): the releases of a course, every version of its branch and the comparison of two of them. The browser reaches them as
 * /studio/api/course-versions/…; the BFF forwards the part after the prefix. Read-only: a release is written by the
 * apply step, never from the browser. The API checks authorship; this list keeps the BFF from being an open proxy.
 */
export const COURSE_VERSIONS_PREFIX = "/course-versions";
export const COURSE_VERSIONS_RULES: Array<{ method: string; pattern: RegExp }> = [
  { method: "GET", pattern: /^\/courses\/\d{1,10}\/releases$/ },
  { method: "GET", pattern: /^\/courses\/\d{1,10}\/versions$/ },
  { method: "GET", pattern: /^\/courses\/\d{1,10}\/compare$/ },
  { method: "GET", pattern: new RegExp(`^/releases/${ID}$`) },
];

/** `path` is relative to the BFF root, e.g. `/course-versions/courses/3/releases`. */
export function isCourseVersionsCall(method: string, path: string): boolean {
  if (!path.startsWith(`${COURSE_VERSIONS_PREFIX}/`)) return false;
  const rest = path.slice(COURSE_VERSIONS_PREFIX.length);
  return COURSE_VERSIONS_RULES.some((r) => r.method === method.toUpperCase() && r.pattern.test(rest));
}

/** The API path of an allowed course-versions call (`path` as for `isCourseVersionsCall`). */
export function courseVersionsTarget(path: string): string {
  return `/api/admin/course-versions${path.slice(COURSE_VERSIONS_PREFIX.length)}`;
}

/**
 * Policy lifecycle (/api/admin/policies, ADR 0140). The browser reaches it as /studio/api/policies/…; the BFF forwards the part
 * after the prefix. Creating a policy and uploading a new version are multipart POSTs; approving and rejecting a version are the
 * owner's explicit decisions. The API checks the permission and that the policy is the caller's own; this list keeps the BFF
 * from being an open proxy. The CSV export is a GET the page links to (the author's cookie signs it).
 */
export const POLICIES_PREFIX = "/policies";
const PID = "[0-9a-z]{26}";
export const POLICIES_RULES: Array<{ method: string; pattern: RegExp }> = [
  { method: "GET", pattern: /^\/?$/ },
  { method: "POST", pattern: /^\/?$/ },
  { method: "GET", pattern: new RegExp(`^/${PID}$`) },
  { method: "GET", pattern: new RegExp(`^/${PID}/(versions|variants|dashboard|dashboard/export)$`) },
  { method: "POST", pattern: new RegExp(`^/${PID}/versions$`) },
  { method: "GET", pattern: new RegExp(`^/${PID}/versions/\\d{1,6}$`) },
  { method: "POST", pattern: new RegExp(`^/${PID}/versions/\\d{1,6}/(approve|reject)$`) },
  { method: "POST", pattern: new RegExp(`^/${PID}/variants/generate$`) },
  // a department's own academy (ADR 0141, amendment): ask, read, remove
  { method: "GET", pattern: new RegExp(`^/${PID}/variants/${PID}/academy$`) },
  { method: "POST", pattern: new RegExp(`^/${PID}/variants/${PID}/academy$`) },
  { method: "DELETE", pattern: new RegExp(`^/${PID}/variants/${PID}/academy$`) },
];

/** `path` is relative to the BFF root, e.g. `/policies/01…/dashboard`. */
export function isPoliciesCall(method: string, path: string): boolean {
  if (path !== POLICIES_PREFIX && !path.startsWith(`${POLICIES_PREFIX}/`)) return false;
  const rest = path.slice(POLICIES_PREFIX.length);
  return POLICIES_RULES.some((r) => r.method === method.toUpperCase() && r.pattern.test(rest));
}

/** The API path of an allowed policies call (`path` as for `isPoliciesCall`). */
export function policiesTarget(path: string): string {
  return `/api/admin/policies${path.slice(POLICIES_PREFIX.length)}`;
}
