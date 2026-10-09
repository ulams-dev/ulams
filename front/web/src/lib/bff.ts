/**
 * Which browser calls the server may forward to the tenant API on the learner's behalf.
 * Everything else is refused: the BFF is not an open proxy.
 */
export interface BffRule {
  method: string;
  pattern: RegExp;
  /** Drops the cached progress after the call. */
  writesProgress?: boolean;
}

export const BFF_RULES: BffRule[] = [
  { method: "GET", pattern: /^\/api\/profile\/me$/ },
  { method: "GET", pattern: /^\/api\/courses\/progress\/\d+$/ },
  { method: "PUT", pattern: /^\/api\/courses\/progress\/\d+\/ping$/ },
  { method: "PATCH", pattern: /^\/api\/courses\/progress\/\d+$/, writesProgress: true },
  { method: "POST", pattern: /^\/api\/courses\/progress\/\d+\/h5p$/, writesProgress: true },
  // Interactive topics: the lesson page forwards the bridge's events with the learner's own session (ADR 0086)
  { method: "POST", pattern: /^\/api\/interactive\/topics\/\d+\/events$/, writesProgress: true },
  { method: "GET", pattern: /^\/api\/quiz-attempts$/ },
  { method: "GET", pattern: /^\/api\/quiz-attempts\/\d+$/ },
  { method: "POST", pattern: /^\/api\/quiz-attempts$/ },
  { method: "POST", pattern: /^\/api\/quiz-attempts\/\d+\/end$/, writesProgress: true },
  { method: "POST", pattern: /^\/api\/quiz-answers$/ },
  // Living Course: the learner's own update notices (the API scopes them to the caller)
  { method: "GET", pattern: /^\/api\/living-course\/courses\/\d+\/notices$/ },
  { method: "POST", pattern: /^\/api\/living-course\/notices\/\d+\/dismiss$/ },
  { method: "GET", pattern: /^\/api\/living-course\/courses\/\d+\/freshness$/ },
];

export function matchBffRule(method: string, path: string): BffRule | null {
  return BFF_RULES.find((r) => r.method === method.toUpperCase() && r.pattern.test(path)) ?? null;
}

/**
 * The allow-list for state-changing requests: this site's own origin and nothing else. In
 * particular no sibling subdomain of the same registrable domain (the tenant content origin
 * `<slug>.content.ulams.app` runs third-party package code and is same-site with this app, ADR 0014).
 * `host` is the Host the browser asked for (x-forwarded-host from the proxy; only its first entry is
 * used), `secure` whether it reached us over https.
 */
export function ownOrigins(host: string, secure: boolean): string[] {
  const first = host.split(",")[0]?.trim().toLowerCase() ?? "";
  if (!/^[a-z0-9.-]+(:\d{1,5})?$/.test(first)) return [];
  return [`${secure ? "https" : "http"}://${first}`];
}

/**
 * Mutating calls (POST/PUT/PATCH/DELETE) must come from an allow-listed origin (CSRF, cookie
 * tossing from a sibling subdomain): the exact `Origin` header, else `Sec-Fetch-Site: same-origin`.
 * `Origin: null` (sandboxed frames, redirects across origins) and `Sec-Fetch-Site: same-site` are
 * refused; a request with neither header (not a browser) is refused as well.
 */
/** The 403 for a refused cross-site write, or null when the request may proceed (used by the middleware). */
export function refuseCrossSite(request: Request, host: string, secure: boolean): Response | null {
  if (isSameOrigin(request, ownOrigins(host, secure))) return null;
  return new Response(JSON.stringify({ message: "Cross-site request refused" }), {
    status: 403,
    headers: { "Content-Type": "application/json" },
  });
}

export function isSameOrigin(request: Request, allowed: string | string[]): boolean {
  if (request.method === "GET" || request.method === "HEAD" || request.method === "OPTIONS") return true;
  const list = Array.isArray(allowed) ? allowed : [allowed];
  const origin = request.headers.get("origin");
  if (origin) return list.includes(origin);
  return request.headers.get("sec-fetch-site") === "same-origin";
}
