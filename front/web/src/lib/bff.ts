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
  { method: "GET", pattern: /^\/api\/quiz-attempts$/ },
  { method: "GET", pattern: /^\/api\/quiz-attempts\/\d+$/ },
  { method: "POST", pattern: /^\/api\/quiz-attempts$/ },
  { method: "POST", pattern: /^\/api\/quiz-attempts\/\d+\/end$/, writesProgress: true },
  { method: "POST", pattern: /^\/api\/quiz-answers$/ },
];

export function matchBffRule(method: string, path: string): BffRule | null {
  return BFF_RULES.find((r) => r.method === method.toUpperCase() && r.pattern.test(path)) ?? null;
}

/** Mutating calls must come from this site (CSRF): Origin, else Sec-Fetch-Site. */
export function isSameOrigin(request: Request, ownOrigin: string): boolean {
  if (request.method === "GET" || request.method === "HEAD") return true;
  const origin = request.headers.get("origin");
  if (origin) return origin === ownOrigin;
  return request.headers.get("sec-fetch-site") === "same-origin";
}
