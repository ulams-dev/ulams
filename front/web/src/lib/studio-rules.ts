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
  { method: "POST", pattern: new RegExp(`^/sessions/${ID}/(undo|redo|apply|publish)$`) },
  { method: "GET", pattern: new RegExp(`^/sessions/${ID}/usage$`) },
];

export function isStudioCall(method: string, path: string): boolean {
  return STUDIO_RULES.some((r) => r.method === method.toUpperCase() && r.pattern.test(path));
}
