/**
 * Entry points (ADR 0195): extra hosts of one academy, each aimed at a department, a course or a role, with its own
 * theme. The front maps a request host to an academy with its own host rules first; a host that no rule matches is
 * looked up on the platform (`GET /api/platform/entry-points/resolve`), cached like the other public data. An entry
 * point only changes what the page shows first: it never grants access and never joins anyone to a group.
 */
import { AsyncLocalStorage } from "node:async_hooks";
import type { PublicSettings } from "@ulams/sdk";
import { TENANT_THEMES } from "@ulams/ui/theme/presets.ts";
import { SwrCache } from "./cache.ts";
import { cookieName } from "./cookies.ts";

export const ENTRY_KINDS = ["academy", "department", "course", "role"] as const;
export type EntryKind = (typeof ENTRY_KINDS)[number];

export interface EntryPoint {
  /** Slug of the academy that serves the host. */
  tenant: string;
  host: string;
  kind: EntryKind;
  key: string;
  label: string;
  /** `group_id` (department), `course_id` (course) or `role` (role); empty for an academy. */
  target: { group_id?: number; course_id?: number; role?: string };
  theme: { preset?: string; accent?: string; logo_alt?: string };
  seo: { canonical: "tenant" | "self"; index: boolean; title?: string; description?: string };
  locale: string | null;
}

/** How the page got its entry point: the host it was asked on, or the `/go/<key>` cookie on the academy's own host. */
export type EntryContext = EntryPoint & { via: "host" | "go" };

export const KEY_PATTERN = /^[a-z0-9](?:[a-z0-9-]{0,38}[a-z0-9])?$/;
const SLUG_PATTERN = /^[a-z][a-z0-9]{1,29}$/;
const HEX = /^#[0-9a-fA-F]{6}$/;

const text = (value: unknown, max: number): string | undefined => (typeof value === "string" && value.length > 0 && value.length <= max ? value : undefined);

/**
 * The answer of the platform, checked field by field: anything that does not fit is dropped (a theme the front does
 * not know, a malformed colour), and an answer without an academy, a kind or a key is no entry point at all.
 */
export function parseEntry(json: unknown): EntryPoint | null {
  const d = ((json as { data?: unknown } | null)?.data ?? null) as Record<string, unknown> | null;
  if (!d || typeof d !== "object") return null;
  const tenant = typeof d.tenant === "string" && SLUG_PATTERN.test(d.tenant) ? d.tenant : null;
  const kind = ENTRY_KINDS.find((k) => k === d.kind);
  const key = typeof d.key === "string" && KEY_PATTERN.test(d.key) ? d.key : null;
  const host = text(d.host, 253);
  if (!tenant || !kind || !key || !host) return null;

  const target = (d.target ?? {}) as Record<string, unknown>;
  const theme = (d.theme ?? {}) as Record<string, unknown>;
  const seo = (d.seo ?? {}) as Record<string, unknown>;
  const known: readonly string[] = TENANT_THEMES;
  const id = (v: unknown) => (typeof v === "number" && Number.isInteger(v) && v > 0 ? v : undefined);

  return {
    tenant,
    host,
    kind,
    key,
    label: text(d.label, 120) ?? key,
    target: {
      ...(id(target.group_id) ? { group_id: id(target.group_id) } : {}),
      ...(id(target.course_id) ? { course_id: id(target.course_id) } : {}),
      ...(typeof target.role === "string" && /^[a-z0-9][a-z0-9_-]{0,48}$/.test(target.role) ? { role: target.role } : {}),
    },
    theme: {
      ...(typeof theme.preset === "string" && known.includes(theme.preset) ? { preset: theme.preset } : {}),
      ...(typeof theme.accent === "string" && HEX.test(theme.accent) ? { accent: theme.accent } : {}),
      ...(text(theme.logo_alt, 120) ? { logo_alt: text(theme.logo_alt, 120) } : {}),
    },
    seo: {
      canonical: seo.canonical === "self" && kind === "course" ? "self" : "tenant",
      // an answer that says nothing about indexing is treated as "do not index": the safe side
      index: seo.index === true,
      ...(text(seo.title, 120) ? { title: text(seo.title, 120) } : {}),
      ...(text(seo.description, 300) ? { description: text(seo.description, 300) } : {}),
    },
    locale: typeof d.locale === "string" && /^[a-z]{2,3}(?:-[A-Za-z]{2,4})?$/.test(d.locale) ? d.locale : null,
  };
}

/** The lookup failed (the platform is down or answered nonsense), which is not the same as "no such host". */
export class EntryLookupError extends Error {}

export interface EntryResolverOptions {
  /** Public URL of the platform API (a platform host); empty switches entry points off. */
  platformApiUrl: string;
  fetch?: typeof fetch;
  cache?: SwrCache;
  /** Freshness of a found entry point (default 60 s) and of "no such host" (default 10 s). */
  hitTtlMs?: number;
  missTtlMs?: number;
  /** How long a stale answer is served while the platform cannot be reached. */
  maxStaleMs?: number;
}

/** Looks up entry points on the platform with a stale-while-revalidate cache that keeps misses short. */
export class EntryResolver {
  private readonly cache: SwrCache;
  private readonly hit: number;
  private readonly miss: number;
  private readonly stale: number;

  constructor(private readonly options: EntryResolverOptions) {
    this.cache = options.cache ?? new SwrCache({ maxEntries: 1000 });
    this.hit = options.hitTtlMs ?? 60_000;
    this.miss = options.missTtlMs ?? 10_000;
    this.stale = options.maxStaleMs ?? 10 * 60_000;
  }

  get enabled(): boolean {
    return this.options.platformApiUrl !== "";
  }

  /** The entry point that serves a host (port ignored), or null. Throws {@link EntryLookupError} when the platform cannot say. */
  byHost(host: string): Promise<EntryPoint | null> {
    const name = host.trim().toLowerCase().replace(/:\d+$/, "").replace(/\.$/, "");
    if (!this.enabled || !isPlausibleHost(name)) return Promise.resolve(null);
    return this.lookup(`host|${name}`, `host=${encodeURIComponent(name)}`);
  }

  /** The entry point `key` of an academy (the `/go/<key>` fallback), or null. */
  byKey(tenant: string, key: string): Promise<EntryPoint | null> {
    if (!this.enabled || !SLUG_PATTERN.test(tenant) || !KEY_PATTERN.test(key)) return Promise.resolve(null);
    return this.lookup(`key|${tenant}|${key}`, `tenant=${encodeURIComponent(tenant)}&key=${encodeURIComponent(key)}`);
  }

  private lookup(cacheKey: string, query: string): Promise<EntryPoint | null> {
    return this.cache.get<EntryPoint | null>(cacheKey, () => this.fetchEntry(query), {
      ttlMs: (value) => (value === null ? this.miss : this.hit),
      maxStaleMs: this.stale,
    });
  }

  private async fetchEntry(query: string): Promise<EntryPoint | null> {
    const url = `${this.options.platformApiUrl.replace(/\/+$/, "")}/api/platform/entry-points/resolve?${query}`;
    let response: Response;
    try {
      response = await (this.options.fetch ?? fetch)(url, { headers: { Accept: "application/json" }, signal: AbortSignal.timeout(4_000) });
    } catch (error) {
      throw new EntryLookupError(`entry point lookup failed: ${(error as Error).message}`);
    }
    if (response.status === 404) return null;
    if (!response.ok) throw new EntryLookupError(`entry point lookup answered ${response.status}`);
    const entry = parseEntry(await response.json().catch(() => null));
    if (!entry) throw new EntryLookupError("entry point lookup answered something that is not an entry point");
    return entry;
  }
}

/** A host name worth a lookup: DNS characters only, two labels at least, not an address. */
export function isPlausibleHost(name: string): boolean {
  if (name.length < 3 || name.length > 253 || !name.includes(".")) return false;
  if (/^[0-9.]+$/.test(name)) return false;
  return /^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/.test(name);
}

/**
 * The public settings of the academy as this entry point shows them: its accent, logo text and default language replace
 * the academy's. The preset is not here: `theme.theme` also picks the academy's landing document and page copy, which an
 * entry point must not change, so the preset is applied to the look of the page only ({@link visualTheme}). An entry
 * point that sets a preset but no accent drops the academy's accent, which was chosen for the academy's own preset. The
 * accent still goes through the contrast adjustment of `accentCss`, so an entry point theme cannot make text unreadable.
 * Pure: the cached settings of the academy are never changed.
 */
export function overlaySettings(settings: PublicSettings | null, entry: EntryPoint | null): PublicSettings | null {
  if (!settings || !entry) return settings;
  const { preset, accent, logo_alt: logoAlt } = entry.theme;
  if (!preset && !accent && !logoAlt && !entry.locale) return settings;

  const theme: Record<string, unknown> = { ...(settings.theme ?? {}) };
  if (preset) delete theme.accent;
  if (accent) theme.accent = accent;
  if (logoAlt) theme.logoAlt = logoAlt;
  const global = entry.locale ? { ...(settings.global ?? {}), language: entry.locale } : settings.global;
  return { ...settings, theme, ...(global ? { global } : {}) } as PublicSettings;
}

/** The theme preset a page wears: the entry point's when it sets one, else the academy's (`theme`, already resolved). */
export function visualTheme<T extends string>(theme: T, entry: Pick<EntryPoint, "theme"> | null | undefined): T | string {
  return entry?.theme.preset ?? theme;
}

const store = new AsyncLocalStorage<EntryContext | null>();

/** Runs `fn` (the rest of the request) with the entry point the request was served for. */
export const runWithEntry = <T>(entry: EntryContext | null, fn: () => T): T => store.run(entry, fn);

/** The entry point of the request being served; null outside a request or on the academy's own host. */
export const currentEntry = (): EntryContext | null => store.getStore() ?? null;

/** Name of the cookie `/go/<key>` sets: which entry point of the academy this browser looks through (presentation only). */
export const entryCookieName = (secure: boolean, fallbackPrefix = ""): string => cookieName("ulams_entry", secure, fallbackPrefix);

/** A path on this site that a redirect may go to: one slash, no scheme, no host, no backslash or control character. */
export function safeNext(value: string | null | undefined): string {
  if (typeof value !== "string" || !value.startsWith("/") || value.startsWith("//") || value.length > 512) return "/";
  // a backslash is a slash to browsers, and control characters are dropped from URLs by them: both hide a host
  for (const char of value) if (char === "\\" || char.charCodeAt(0) <= 0x1f || char.charCodeAt(0) === 0x7f) return "/";
  return value;
}
