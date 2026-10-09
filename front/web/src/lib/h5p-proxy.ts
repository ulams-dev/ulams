/**
 * Which calls of the H5P service the `/h5p` proxy makes as the logged-in learner (ADR 0045).
 *
 * The learner's API token stays in the httpOnly session cookie. The H5P frame is told `token: null`,
 * so nothing in the browser can send it; the proxy adds `Authorization: Bearer <session token>` on
 * the server, and only for the learner's own player calls: the embed page, the play model, the
 * saved state (`contentUserData`) and the result (`finishedData`). Everything else (library,
 * content, editor and admin routes) is forwarded as it came, without the session.
 */
/** The routes of the proxy (src/pages/h5p). The middleware reads the session cookie for them. */
export const H5P_ROUTE = /^\/h5p(\/|$)/;

const ID = "[1-9][0-9]{0,18}";
const SEGMENT = "[A-Za-z0-9_.-]{1,64}";

const RULES: Array<{ methods: readonly string[]; path: RegExp }> = [
  { methods: ["GET"], path: new RegExp(`^embed/play/${ID}$`) },
  { methods: ["GET"], path: new RegExp(`^contents/${ID}/play$`) },
  { methods: ["GET", "POST"], path: new RegExp(`^contentUserData/${ID}/${SEGMENT}/${SEGMENT}$`) },
  { methods: ["POST"], path: /^finishedData$/ },
];

/** Whether the proxy may add the session token to this request (`path` is relative to `/h5p/`). */
export function h5pUsesSession(method: string, path: string): boolean {
  if (path.split("/").some((segment) => segment === "" || segment === "." || segment === "..")) return false;
  const verb = method.toUpperCase();
  return RULES.some((rule) => rule.methods.includes(verb) && rule.path.test(path));
}

/** The query string to forward: the proxy's own session replaces any `_token` the caller sent. */
export function h5pSearch(search: string): string {
  const params = new URLSearchParams(search);
  params.delete("_token");
  const out = params.toString();
  return out ? `?${out}` : "";
}
