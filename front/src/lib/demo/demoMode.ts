/**
 * Demo mode, shared by front (Vite) and admin (umi).
 *
 * A demo tenant (API env `DEMO_MODE=true`, `api/packages/demo`) announces itself in the
 * public config (`GET /api/config` → `ulams_demo.enabled`) and lets visitors log in without
 * a password through `POST /api/demo/login {role}`. The response has the same shape as
 * `POST /api/auth/login`, so the token is stored exactly like after a normal login.
 *
 * This module has no imports so that it can run under `node --test` as-is.
 */

export type DemoRole = "student" | "admin";

export interface DemoConfig {
  enabled: boolean;
  /** Learner site of this tenant, when the API knows it. */
  frontUrl: string | null;
  /** Admin panel of this tenant, when the API knows it. */
  adminUrl: string | null;
}

const isRecord = (value: unknown): value is Record<string, unknown> =>
  typeof value === "object" && value !== null && !Array.isArray(value);

const asUrl = (value: unknown): string | null =>
  typeof value === "string" && /^https?:\/\//.test(value.trim())
    ? value.trim().replace(/\/+$/, "")
    : null;

const truthy = (value: unknown): boolean =>
  value === true ||
  value === 1 ||
  (typeof value === "string" &&
    ["1", "true", "on", "yes"].includes(value.trim().toLowerCase()));

/** Reads `ulams_demo` from the public config (`GET /api/config` data). */
export function demoConfigFrom(config: unknown): DemoConfig {
  const demo = isRecord(config) ? config.ulams_demo : undefined;
  if (!isRecord(demo)) {
    return { enabled: false, frontUrl: null, adminUrl: null };
  }

  return {
    enabled: truthy(demo.enabled),
    frontUrl: asUrl(demo.front_url),
    adminUrl: asUrl(demo.admin_url),
  };
}

/**
 * Swaps the app label of a tenant host, e.g. `coffee.app.localhost` → `coffee.admin.localhost`
 * (and back). Returns null when the host does not have the `from` label in second position.
 */
export function siblingAppUrl(
  location: { protocol: string; hostname: string; port?: string },
  from: string,
  to: string
): string | null {
  const labels = location.hostname.toLowerCase().split(".");
  if (labels.length < 3 || labels[1] !== from || labels[0] === "") {
    return null;
  }
  labels[1] = to;
  const port = location.port ? `:${location.port}` : "";

  return `${location.protocol}//${labels.join(".")}${port}`;
}

/** Course detail, course program and preview pages: a demo visitor must be logged in there. */
export function isCourseRoute(pathname: string): boolean {
  return /^\/(courses\/(preview\/)?\d+|course\/\d+)(\/|$)/.test(pathname);
}

/**
 * Whether the front logs the visitor in as the demo student now: once per page load (the
 * first time it can), and again whenever a logged-out visitor opens a course.
 */
export function shouldAutoLogin(state: {
  enabled: boolean;
  hasToken: boolean;
  inFlight: boolean;
  failed: boolean;
  bootAttempted: boolean;
  pathname: string;
}): boolean {
  if (!state.enabled || state.hasToken || state.inFlight || state.failed) {
    return false;
  }

  return !state.bootAttempted || isCourseRoute(state.pathname);
}

type FetchLike = (
  input: string,
  init?: {
    method?: string;
    headers?: Record<string, string>;
    body?: string;
  }
) => Promise<{ ok: boolean; status: number; json: () => Promise<unknown> }>;

/** `POST /api/demo/login`; resolves with the access token. */
export async function demoLogin(
  apiUrl: string,
  role: DemoRole,
  fetchImpl: FetchLike = fetch as unknown as FetchLike
): Promise<string> {
  const response = await fetchImpl(
    `${apiUrl.replace(/\/+$/, "")}/api/demo/login`,
    {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ role }),
    }
  );
  const body = await response.json().catch(() => null);
  const data = isRecord(body) && isRecord(body.data) ? body.data : null;
  const token = data && typeof data.token === "string" ? data.token : null;
  if (!response.ok || !token) {
    const message =
      isRecord(body) && typeof body.message === "string"
        ? body.message
        : `HTTP ${response.status}`;
    throw new Error(`Demo login as ${role} failed: ${message}`);
  }

  return token;
}
