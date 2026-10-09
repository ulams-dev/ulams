/**
 * Reads the origins of a tenant's enabled external tools (GET /api/lti/frame-origins). Throws on a
 * failed request so the caller's cache keeps serving its last good answer.
 */
export async function fetchFrameOrigins(apiUrl: string, fetcher: typeof fetch = fetch): Promise<string[]> {
  const response = await fetcher(`${apiUrl.replace(/\/+$/, "")}/api/lti/frame-origins`, {
    headers: { Accept: "application/json" },
    signal: AbortSignal.timeout(3000),
  });
  if (!response.ok) throw new Error(`frame-origins answered ${response.status}`);
  const body = (await response.json()) as { data?: unknown };
  return Array.isArray(body.data) ? body.data.filter((origin): origin is string => typeof origin === "string") : [];
}
