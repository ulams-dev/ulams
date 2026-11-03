/**
 * One URL builder for every image a client shows (ADR 0193, docs/plans/images-cloudflare.md).
 *
 * The mode is a deployment setting:
 *  - `cloudflare`: `<origin>/cdn-cgi/image/<options>/<source>` (Cloudflare Image Transformations)
 *  - `astro`: `/_image?href=…` (Astro's sharp service)
 *  - `noop`: the original file, no srcset (the kill switch)
 *  - `php`: `<api>/api/images/img?path=<path>&w=<w>` (the legacy resize API, kept)
 *
 * No runtime dependencies, so admin and the legacy app can import it as `@ulams/sdk/images`.
 */
import presets from "./images/presets.json" with { type: "json" };

export type ImageService = "cloudflare" | "astro" | "noop" | "php";
export type PresetName = keyof typeof presets;

export interface Preset {
  /** The only widths ever requested; keeps the number of unique transformations bounded. */
  readonly widths: readonly number[];
  readonly quality: number;
  /** false: a single `src`, no `srcset` (rich text, where no intrinsic size is known). */
  readonly srcset: boolean;
}

export interface ImageConfig {
  service: ImageService;
  /** "" or undefined = the page's own host (relative URLs); e.g. "https://ulams.app". */
  transformOrigin?: string;
  /** Origins whose files may be transformed ("https://storage.example.com", or "https://*.example.com"). */
  allowedSources: readonly string[];
  /** API base URL, used by `php`. */
  apiUrl?: string;
}

/** An absolute or root-relative URL, or `{ path, url }` as the API returns files (path for php, url for the rest). */
export type ImageSource = string | { path?: string | null; url?: string | null };

export const IMAGE_SERVICES: readonly ImageService[] = ["cloudflare", "astro", "noop", "php"];
export const IMAGE_PRESETS: Readonly<Record<PresetName, Preset>> = presets;

/** Option string layout; the order is fixed so equal requests produce equal URLs. */
const cloudflareOptions = (width: number, quality: number) =>
  `width=${width},quality=${quality},format=auto,fit=scale-down,onerror=redirect`;

/** Every option string the builder can emit. The optional WAF rule on `/cdn-cgi/image/*` mirrors this list. */
export function allowedCloudflareOptions(): string[] {
  const all = new Set<string>();
  for (const preset of Object.values(IMAGE_PRESETS)) {
    for (const width of preset.widths) all.add(cloudflareOptions(width, preset.quality));
  }
  return [...all].sort();
}

const warned = new Set<string>();
function warnOnce(message: string) {
  if (warned.has(message)) return;
  warned.add(message);
  console.warn(`[ulams images] ${message}`);
}

/**
 * Reads a setting such as `ULAMS_IMAGE_SERVICE`. Unknown or empty values give `fallback`; a known value the client
 * does not support (`supported`) gives `noop` with a warning once.
 */
export function resolveImageService(
  value: string | undefined | null,
  fallback: ImageService,
  supported: readonly ImageService[] = IMAGE_SERVICES,
): ImageService {
  const normalised = value?.trim().toLowerCase();
  const known = IMAGE_SERVICES.find((service) => service === normalised);
  if (!known) return fallback;
  if (!supported.includes(known)) {
    warnOnce(`image service "${known}" is not supported by this client; using "noop"`);
    return "noop";
  }
  return known;
}

const SIGNED_QUERY_KEYS = new Set([
  "x-amz-signature",
  "x-amz-credential",
  "x-goog-signature",
  "x-goog-credential",
  "signature",
  "sig",
  "token",
  "expires",
]);

const NEVER_TRANSFORM = /\.(svgz?|gif)$/i;

function originAllowed(origin: string, allowed: readonly string[]): boolean {
  const target = new URL(origin);
  return allowed.some((entry) => {
    const pattern = entry.replace(/\/+$/, "");
    if (pattern.includes("*.")) {
      const match = /^(https?:)\/\/\*\.(.+)$/.exec(pattern);
      return !!match && target.protocol === match[1] && target.hostname.endsWith(`.${match[2]}`);
    }
    try {
      return new URL(pattern).origin === target.origin;
    } catch {
      return false;
    }
  });
}

function originalOf(src: ImageSource): string {
  if (typeof src === "string") return src;
  return src.url || src.path || "";
}

/** True when the source may go through a transformer: a public, non-animated, raster file on the own host or an allowed origin. */
function transformable(url: string, allowed: readonly string[]): boolean {
  if (!url || /^(data|blob):/i.test(url)) return false;
  let parsed: URL;
  let relative = false;
  try {
    if (url.startsWith("//")) {
      parsed = new URL(`https:${url}`);
    } else if (url.startsWith("/")) {
      parsed = new URL(url, "https://own-host.invalid");
      relative = true;
    } else {
      parsed = new URL(url);
    }
  } catch {
    return false;
  }
  if (!relative && parsed.protocol !== "https:" && parsed.protocol !== "http:") return false;
  if (parsed.username || parsed.password) return false;
  if (NEVER_TRANSFORM.test(parsed.pathname)) return false;
  for (const key of parsed.searchParams.keys()) {
    if (SIGNED_QUERY_KEYS.has(key.toLowerCase())) return false;
  }
  if (relative) return !/^\/(cdn-cgi\/image\/|_image$)/.test(parsed.pathname);
  return originAllowed(parsed.origin, allowed);
}

/** The preset width to request: the smallest one that covers `width`, never above `maxWidth` (the intrinsic width). */
function pickWidth(widths: readonly number[], width: number, maxWidth?: number): number {
  const sorted = [...widths].sort((a, b) => a - b);
  const capped = maxWidth === undefined ? sorted : sorted.filter((w) => w <= maxWidth);
  const usable = capped.length > 0 ? capped : [sorted[0] as number];
  return usable.find((w) => w >= width) ?? (usable[usable.length - 1] as number);
}

function cappedWidths(preset: Preset, maxWidth?: number): number[] {
  const sorted = [...preset.widths].sort((a, b) => a - b);
  const capped = maxWidth === undefined ? sorted : sorted.filter((w) => w <= maxWidth);
  return capped.length > 0 ? capped : [sorted[0] as number];
}

function activeService(config: ImageConfig): ImageService {
  return IMAGE_SERVICES.includes(config.service) ? config.service : "noop";
}

function build(src: ImageSource, width: number, preset: Preset, config: ImageConfig): string {
  const original = originalOf(src);
  const service = activeService(config);
  if (service === "noop") return original;
  if (service === "php") {
    const path = typeof src === "string" ? "" : src.path;
    const api = (config.apiUrl ?? "").replace(/\/+$/, "");
    // Exactly today's admin URL (path not encoded), so unset deployments are byte for byte unchanged.
    return path ? `${api}/api/images/img?path=${path}&w=${width}` : original;
  }
  if (!transformable(original, config.allowedSources)) return original;
  if (service === "astro") {
    return `/_image?href=${encodeURIComponent(original)}&w=${width}&f=webp&q=${preset.quality}`;
  }
  const origin = (config.transformOrigin ?? "").replace(/\/+$/, "");
  const source = original.startsWith("/") && !original.startsWith("//") ? original.replace(/^\/+/, "") : original;
  return `${origin}/cdn-cgi/image/${cloudflareOptions(width, preset.quality)}/${source}`;
}

/**
 * One image URL. `width` snaps to the preset's list; `maxWidth` (the file's intrinsic width) caps it so a small file
 * is never upscaled. Sources the mode cannot or must not transform come back unchanged.
 */
export function imageUrl(
  src: ImageSource,
  width: number,
  presetName: PresetName,
  config: ImageConfig,
  maxWidth?: number,
): string {
  const preset = IMAGE_PRESETS[presetName];
  return build(src, pickWidth(preset.widths, width, maxWidth), preset, config);
}

/** `srcset` value, or undefined when a plain `src` is right (`noop`, pass-through sources, single-width presets). */
export function imageSrcSet(
  src: ImageSource,
  presetName: PresetName,
  config: ImageConfig,
  maxWidth?: number,
): string | undefined {
  const preset = IMAGE_PRESETS[presetName];
  if (!preset.srcset) return undefined;
  const widths = cappedWidths(preset, maxWidth);
  const entries = widths.map((w) => ({ w, url: build(src, w, preset, config) }));
  const distinct = new Set(entries.map((entry) => entry.url));
  if (distinct.size < 2) return undefined;
  return entries.map((entry) => `${entry.url} ${entry.w}w`).join(", ");
}
