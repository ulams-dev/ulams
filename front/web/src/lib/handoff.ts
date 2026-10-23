import type { Tenant } from "@ulams/sdk";
import { tenantFetch } from "./api-transport.ts";
import { isPlausibleHost } from "./entry.ts";

/**
 * Moving a signed-in learner between the hosts of one academy (ADR 0195). Every host has its own `__Host-` session, so
 * a session is never shared; the browser makes three top-level hops and only a one-time code travels in a URL:
 *
 *   target  /handoff/begin?from=<source host>    keeps a verifier in an httpOnly cookie, sends only its S256 challenge
 *   source  /handoff/issue?to=<target host>&challenge=...   asks the API for a code with the learner's own token
 *   target  /handoff/complete?code=...           redeems the code with the verifier and sets a session of its own
 *
 * The code is bound to the learner, the target host and the verifier, lives 60 seconds and works once: a code that leaks
 * (history, logs, Referer) is useless to anybody else, and no access token is ever put in a URL.
 */
export const HANDOFF_COOKIE_BASE = "ulams_handoff";
export const HANDOFF_COMPLETE_PATH = "/handoff/complete";
export const HANDOFF_ISSUE_PATH = "/handoff/issue";

/** The hand-off pages carry a code: never cached, never passed on in a Referer. */
export const HANDOFF_HEADERS: Readonly<Record<string, string>> = {
  "Cache-Control": "private, no-store",
  "Referrer-Policy": "no-referrer",
};

export const isHandoffPath = (pathname: string): boolean => pathname.startsWith("/handoff/");

export interface HostParam {
  /** Lower case host name, no port. */
  name: string;
  port: string;
}

/** A `host` or `host:port` query value, or null for anything that is not one (a scheme, a path, credentials, an address). */
export function parseHostParam(value: string | null | undefined): HostParam | null {
  if (typeof value !== "string" || value.length === 0 || value.length > 260) return null;
  const match = /^([A-Za-z0-9.-]+)(?::(\d{1,5}))?$/.exec(value);
  if (!match) return null;
  const name = match[1]!.toLowerCase().replace(/\.$/, "");
  return isPlausibleHost(name) ? { name, port: match[2] ?? "" } : null;
}

/** The port a request was served on, from its `Host` header (empty for the default port). */
export function portOf(host: string): string {
  return /:(\d{1,5})$/.exec(host)?.[1] ?? "";
}

/**
 * The origin of a sibling host as this deployment serves it: the current protocol, and the current port (the hosts of
 * one academy are served by one deployment). A sibling named with another port is refused.
 */
export function siblingOrigin(sibling: HostParam, currentHost: string, secure: boolean): string | null {
  const port = portOf(currentHost);
  if (sibling.port !== "" && sibling.port !== port) return null;
  return `${secure ? "https" : "http"}://${sibling.name}${port ? `:${port}` : ""}`;
}

export type Issued = { ok: true; code: string } | { ok: false };

/** Asks the API for a hand-off code with the learner's own token. Never throws: any failure is `{ ok: false }`. */
export async function issueCode(
  tenant: Pick<Tenant, "apiUrl">,
  token: string,
  host: string,
  challenge: string,
  fetcher: typeof fetch = tenantFetch(tenant)
): Promise<Issued> {
  try {
    const response = await fetcher(new URL("/api/auth/handoff", tenant.apiUrl), {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json", Authorization: `Bearer ${token}` },
      body: JSON.stringify({ host, challenge }),
      signal: AbortSignal.timeout(10_000),
    });
    if (!response.ok) return { ok: false };
    const code = ((await response.json()) as { data?: { code?: unknown } }).data?.code;
    return typeof code === "string" && /^[A-Za-z0-9_-]{43}$/.test(code) ? { ok: true, code } : { ok: false };
  } catch {
    return { ok: false };
  }
}
