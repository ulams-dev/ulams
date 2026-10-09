import type { Tenant } from "@ulams/sdk";

/**
 * Content Security Policy of the learner front (ADR 0044). The front builds it per request, so
 * `frame-src` can name the origins of the external tools (LTI) the tenant registered, next to the
 * content origin, and the report endpoint is the tenant's own API. It is sent as
 * `Content-Security-Policy` when enforcement is on (`CSP_ENFORCE`), else as `-Report-Only`.
 */
export interface CspInput {
  /** The tenant of the request; null on the platform host, which has no API to report to. */
  tenant: Tenant | null;
  /** Origins of the tenant's registered external tools (GET /api/lti/frame-origins). */
  toolOrigins: readonly string[];
  /** The tenant's content origin (packages, LiaScript), or null when it has none. */
  contentOrigin: string | null;
  /** Origins that hold uploaded files (images, media, PDFs). */
  storageOrigins: readonly string[];
}

/** Providers the oEmbed topic and the Embed component frame (front/ui Embed.astro). */
export const EMBED_PROVIDERS = ["https://www.youtube-nocookie.com", "https://player.vimeo.com"] as const;

const unique = (values: Array<string | null | undefined>): string[] => [...new Set(values.filter((v): v is string => Boolean(v)))];

/** `http://coffee.localhost/api` to `http://coffee.localhost`; null for anything that is not an http(s) URL. */
export function originOf(url: string | null | undefined): string | null {
  try {
    const parsed = new URL(String(url));
    return parsed.protocol === "http:" || parsed.protocol === "https:" ? parsed.origin : null;
  } catch {
    return null;
  }
}

/** A source of a CSP source list: an origin with nothing that reads as syntax. */
const safeSource = (origin: string | null): origin is string => origin !== null && /^https?:\/\/[A-Za-z0-9.:*-]+$/.test(origin);

export function reportUrl(tenant: Tenant): string {
  return `${tenant.apiUrl.replace(/\/+$/, "")}/api/csp-report`;
}

/** Keeps the entries that are a keyword of the source list or a plain origin. */
const sources = (values: Array<string | null | undefined>, keywords: readonly string[] = ["'self'", "data:", "blob:"]): string[] =>
  unique(values).filter((v) => keywords.includes(v) || safeSource(v));

export function buildCsp({ tenant, toolOrigins, contentOrigin, storageOrigins }: CspInput): string {
  const api = originOf(tenant?.apiUrl);
  const content = originOf(contentOrigin);
  const storage = storageOrigins.map(originOf);
  const tools = toolOrigins.map(originOf);

  const frame = sources(["'self'", content, ...storage, ...EMBED_PROVIDERS, ...tools]);
  const files = sources(["'self'", "data:", "blob:", api, content, ...storage]);
  const media = files.filter((s) => s !== "data:");

  return [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
    "font-src 'self' data: https://fonts.gstatic.com",
    // Authors embed remote images and videos in rich text; scripts, frames and connections stay listed.
    `img-src ${[...files, "https:"].join(" ")}`,
    `media-src ${[...media, "https:"].join(" ")}`,
    `connect-src ${sources(["'self'", api]).join(" ")}`,
    `frame-src ${frame.join(" ")}`,
    "frame-ancestors 'self'",
    "form-action 'self'",
    "object-src 'none'",
    "base-uri 'self'",
    ...(tenant ? [`report-uri ${reportUrl(tenant)}`, "report-to csp-endpoint"] : []),
  ].join("; ");
}

/** The two headers of one response: the policy (enforced or report-only) and where to report it. */
export function cspHeaders(input: CspInput, enforce: boolean): Record<string, string> {
  return {
    [enforce ? "Content-Security-Policy" : "Content-Security-Policy-Report-Only"]: buildCsp(input),
    ...(input.tenant ? { "Reporting-Endpoints": `csp-endpoint="${reportUrl(input.tenant)}"` } : {}),
  };
}

/** Whether the front sets a CSP of its own on this path: its pages, not the proxied H5P service or JSON. */
export function wantsCsp(pathname: string, contentType: string | null): boolean {
  if (pathname === "/h5p" || pathname.startsWith("/h5p/")) return false; // the H5P service sets its own
  if (contentType === null) return true; // before rendering: only the path is known
  return contentType.toLowerCase().includes("text/html");
}

export function parseEnforce(value: string | boolean | undefined | null, fallback: boolean): boolean {
  if (typeof value === "boolean") return value;
  if (value === undefined || value === null || value === "") return fallback;
  return ["1", "true", "yes", "on"].includes(String(value).trim().toLowerCase());
}

/** `http://{slug}.content.localhost` for a tenant; the template comes from ULAMS_CONTENT_ORIGIN. */
export function contentOriginFor(template: string, slug: string): string | null {
  return template ? originOf(template.split("{slug}").join(slug)) : null;
}
