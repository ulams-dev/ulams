/**
 * Image URLs of the legacy learner app (ADR 0193, plan docs/plans/images-cloudflare.md, IMG-5), on the shared builder
 * of `@ulams/sdk/images` (front/sdk/src/images.ts). One build setting, `VITE_ULAMS_IMAGE_SERVICE`:
 *  - `php` (default, also for an unset or unknown value): the API resizer, byte for byte the URLs the app always built
 *  - `cloudflare`: `/cdn-cgi/image/...` on the app's own host, the source is the file's public storage URL
 *  - `noop`: the original file, no resizing (the kill switch)
 * `astro` is a front-only mode; asking for it here gives `noop` with a console warning once.
 * `VITE_ULAMS_IMAGE_SOURCES` lists the origins whose files may be transformed (comma-separated, normally the public
 * storage origin); `VITE_ULAMS_IMAGE_TRANSFORM_ORIGIN` moves the transformation to another host (empty: own host).
 * A file with no public URL cannot go through a transformer, so it keeps using the PHP resizer, which always works.
 *
 * No browser globals here, so `node --test` can run it (tests/legacyImages.test.ts); src/lib/images/index.ts binds the settings.
 */
import {
  type ImageConfig,
  type ImageService,
  type PresetName,
  imageSrcSet,
  imageUrl,
  resolveImageService,
} from "../../../sdk/src/images.ts";

export interface LegacyImageSettings {
  /** `VITE_ULAMS_IMAGE_SERVICE` */
  service?: string | null;
  /** `VITE_ULAMS_IMAGE_SOURCES`, comma-separated origins */
  sources?: string | null;
  /** `VITE_ULAMS_IMAGE_TRANSFORM_ORIGIN` */
  transformOrigin?: string | null;
  /** The API base URL (no trailing slash needed). */
  apiUrl: string;
}

export const SUPPORTED_SERVICES: readonly ImageService[] = ["php", "cloudflare", "noop"];

const list = (value?: string | null): string[] =>
  (value ?? "")
    .split(",")
    .map((entry) => entry.trim().replace(/\/+$/, ""))
    .filter(Boolean);

export const imageServiceOf = (settings: LegacyImageSettings): ImageService =>
  resolveImageService(settings.service, "php", SUPPORTED_SERVICES);

export function imageConfigOf(settings: LegacyImageSettings): ImageConfig {
  const api = settings.apiUrl.replace(/\/+$/, "");
  let apiOrigin: string[] = [];
  try {
    apiOrigin = [new URL(api).origin];
  } catch {
    apiOrigin = [];
  }
  return {
    service: imageServiceOf(settings),
    transformOrigin: (settings.transformOrigin ?? "").trim(),
    allowedSources: [...apiOrigin, ...list(settings.sources)],
    apiUrl: api,
  };
}

/** The URL the app built for avatars before this setting existed (no width: the PHP endpoint serves the original size). */
export const legacyAvatarUrl = (apiUrl: string, path: string): string =>
  `${apiUrl}/api/images/img?path=${path}`;

export interface AvatarOwner {
  path_avatar?: string | null;
  url_avatar?: string | null;
}

export interface LegacyImage {
  src: string;
  srcSet?: string;
}

/**
 * An avatar for the `avatar` preset. `width` is the CSS width it is shown at. Returns undefined without a file,
 * so the caller keeps its placeholder.
 */
export function avatarImage(
  owner: AvatarOwner | null | undefined,
  width: number,
  settings: LegacyImageSettings,
): LegacyImage | undefined {
  const path = owner?.path_avatar || "";
  const url = owner?.url_avatar || "";
  if (!path && !url) return undefined;
  const config = imageConfigOf(settings);
  const phpFallback = path ? { src: legacyAvatarUrl(config.apiUrl ?? "", path) } : undefined;
  if (config.service === "php") return phpFallback ?? { src: url };
  if (!url) return phpFallback;
  return {
    src: imageUrl({ path, url }, width, "avatar", config),
    srcSet: imageSrcSet({ path, url }, "avatar", config),
  };
}

/**
 * A file known only by its storage path (the `Image` component). Outside `php` the public URL is the first
 * `VITE_ULAMS_IMAGE_SOURCES` origin plus the path; without that setting the component keeps using the PHP resizer.
 * undefined means: use the PHP resizer / `imgcache` flow of the component.
 */
export function storagePathImage(
  path: string,
  width: number | undefined,
  preset: PresetName,
  settings: LegacyImageSettings,
): LegacyImage | undefined {
  const config = imageConfigOf(settings);
  if (config.service === "php") return undefined;
  const origin = list(settings.sources)[0];
  if (!origin || !path) return undefined;
  const source = { path, url: `${origin}/${path.replace(/^\/+/, "")}` };
  return {
    src: imageUrl(source, width ?? 1280, preset, config),
    srcSet: imageSrcSet(source, preset, config),
  };
}
