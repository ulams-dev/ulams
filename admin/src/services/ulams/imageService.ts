import {
  type ImageConfig,
  type ImageService,
  type ImageSource,
  type PresetName,
  imageSrcSet,
  imageUrl,
  resolveImageService,
} from '@ulams/images';

/**
 * Image URLs of the admin (ADR 0193). One deployment setting, `REACT_APP_IMAGE_SERVICE` (runtime-config.json or
 * the build environment):
 *  - `php` (default): the API's `/api/images/img` resizer, exactly as before this setting existed
 *  - `cloudflare`: `/cdn-cgi/image/...` on the admin host, source = the file's public storage URL
 *  - `noop`: the original file, no resizing (the kill switch)
 * `astro` is a front-only mode; asking for it here gives `noop` (with a console warning once).
 */
declare const REACT_APP_API_URL: string | undefined;
declare const REACT_APP_IMAGE_SERVICE: string | undefined;
declare const REACT_APP_IMAGE_SOURCES: string | undefined;
declare const REACT_APP_IMAGE_TRANSFORM_ORIGIN: string | undefined;

const SUPPORTED: readonly ImageService[] = ['php', 'cloudflare', 'noop'];

const windowSetting = (key: string): string | undefined => {
  if (typeof window === 'undefined') return undefined;
  const value = (window as unknown as Record<string, unknown>)[key];
  return typeof value === 'string' && value !== '' ? value : undefined;
};

const buildSetting = (value: string | undefined): string | undefined =>
  value !== undefined && value !== '' ? value : undefined;

export const apiUrl = (): string =>
  windowSetting('REACT_APP_API_URL') ??
  buildSetting(typeof REACT_APP_API_URL !== 'undefined' ? REACT_APP_API_URL : undefined) ??
  '';

export const imageService = (): ImageService =>
  resolveImageService(
    windowSetting('REACT_APP_IMAGE_SERVICE') ??
      buildSetting(
        typeof REACT_APP_IMAGE_SERVICE !== 'undefined' ? REACT_APP_IMAGE_SERVICE : undefined,
      ),
    'php',
    SUPPORTED,
  );

const originOf = (url: string): string | undefined => {
  try {
    return new URL(url).origin;
  } catch {
    return undefined;
  }
};

export const imageConfig = (): ImageConfig => {
  const api = apiUrl();
  const extra =
    windowSetting('REACT_APP_IMAGE_SOURCES') ??
    buildSetting(
      typeof REACT_APP_IMAGE_SOURCES !== 'undefined' ? REACT_APP_IMAGE_SOURCES : undefined,
    ) ??
    '';
  const allowedSources = [originOf(api), ...extra.split(',').map((entry) => entry.trim())].filter(
    (entry): entry is string => !!entry,
  );
  return {
    service: imageService(),
    transformOrigin:
      windowSetting('REACT_APP_IMAGE_TRANSFORM_ORIGIN') ??
      buildSetting(
        typeof REACT_APP_IMAGE_TRANSFORM_ORIGIN !== 'undefined'
          ? REACT_APP_IMAGE_TRANSFORM_ORIGIN
          : undefined,
      ) ??
      '',
    allowedSources,
    apiUrl: api,
  };
};

/** The legacy resizer URL, byte for byte what the admin always built (arbitrary widths, path not encoded). */
export const phpImageUrl = (path: string, width: number): string =>
  `${apiUrl()}/api/images/img?path=${path}&w=${width}`;

export interface AdminImage {
  /** Storage path (what the PHP resizer needs). */
  path?: string | null;
  /** Public URL of the file (what `cloudflare` and `noop` need). */
  url?: string | null;
}

const hasUrl = (image: AdminImage) => !!image.url;

/** A file with no public URL cannot go through a transformer; it keeps using the PHP resizer, which always works. */
const usesPhp = (image: AdminImage, service: ImageService) =>
  service === 'php' || (!hasUrl(image) && !!image.path);

export interface ResolvedImage {
  src: string;
  srcSet?: string;
  /** The untouched file, for the onerror fallback. */
  original: string;
}

/** `src`, `srcSet` and the original of one image for the current mode. */
export function resolveImage(
  image: AdminImage,
  options: { size?: number; srcSizes?: number[]; preset?: PresetName },
  config: ImageConfig = imageConfig(),
): ResolvedImage {
  const { size, srcSizes, preset = 'content' } = options;
  const width = srcSizes?.[0] ?? size ?? 960;
  const path = image.path ?? '';
  const original = image.url || (path ? phpImageUrl(path, width) : '');

  if (usesPhp(image, config.service)) {
    return {
      src: phpImageUrl(path, width),
      srcSet: srcSizes?.map((w) => `${phpImageUrl(path, w)} ${w}w`).join(','),
      original: image.url || phpImageUrl(path, width),
    };
  }
  const source: ImageSource = { path: image.path, url: image.url };
  return {
    src: imageUrl(source, width, preset, config),
    srcSet: imageSrcSet(source, preset, config),
    original,
  };
}

/**
 * URL for an image inside markdown. `php` (default) and files without a public URL: the resizer URL, as before.
 * Other modes: the plain public URL; renderers apply their own preset at display time (plan 6.7).
 */
export function contentImageUrl(image: AdminImage, width = 1000): string {
  const service = imageService();
  if (usesPhp(image, service)) return phpImageUrl(image.path ?? '', width);
  return image.url as string;
}
