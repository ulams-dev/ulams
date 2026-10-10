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
