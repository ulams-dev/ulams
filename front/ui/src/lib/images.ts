/**
 * Image URLs for the Astro fronts, on the shared builder (`@ulams/sdk/images`, ADR 0193).
 *
 * The mode is a runtime setting, read on every call: `ULAMS_IMAGE_SERVICE` = `cloudflare | astro | noop`
 * (unset: `astro`, today's behaviour). `ULAMS_IMAGE_SOURCES` lists the public storage origins whose files may be
 * transformed (comma separated; default: `ULAMS_STORAGE_ORIGINS`, the origins the CSP already allows).
 */
import {
  imageSrcSet,
  imageUrl,
  resolveImageService,
  type ImageConfig,
  type ImageService,
  type PresetName,
} from "@ulams/sdk/images";

export type { ImageConfig, ImageService, PresetName };

const SUPPORTED: readonly ImageService[] = ["cloudflare", "astro", "noop"];
const DEFAULT_SOURCES = "http://storage.localhost";

function setting(name: string): string | undefined {
  const fromProcess = (globalThis as { process?: { env?: Record<string, string | undefined> } }).process?.env?.[name];
  if (fromProcess !== undefined) return fromProcess;
  return (import.meta as { env?: Record<string, string | undefined> }).env?.[name];
}

const list = (value: string): string[] =>
  value
    .split(",")
    .map((s) => s.trim())
    .filter(Boolean);

/** The deployment's image configuration. */
export function imageConfig(): ImageConfig {
  const sources = setting("ULAMS_IMAGE_SOURCES") || setting("ULAMS_STORAGE_ORIGINS") || DEFAULT_SOURCES;
  return {
    service: resolveImageService(setting("ULAMS_IMAGE_SERVICE"), "astro", SUPPORTED),
    allowedSources: list(sources),
  };
}

/**
 * For plain `<img>` sites, which never went through Astro's `/_image`: `astro` keeps serving the original there,
 * so a deployment that changes no setting renders what it rendered before.
 */
export function rawImageConfig(): ImageConfig {
  const config = imageConfig();
  return config.service === "astro" ? { ...config, service: "noop" } : config;
}

/**
 * `src` for a plain `<img>`: one URL at the preset width that covers `width`, capped at the file's
 * intrinsic width when known. Original file in `astro` and `noop` modes and for sources that must not be transformed.
 */
export function imageSrc(src: string, preset: PresetName, width: number, intrinsicWidth?: number): string {
  return imageUrl(src, width, preset, rawImageConfig(), intrinsicWidth);
}

export { imageSrcSet, imageUrl };
