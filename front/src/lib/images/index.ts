import {
  API_URL,
  VITE_ULAMS_IMAGE_SERVICE,
  VITE_ULAMS_IMAGE_SOURCES,
  VITE_ULAMS_IMAGE_TRANSFORM_ORIGIN,
} from "../../config";
import {
  type AvatarOwner,
  type LegacyImage,
  type LegacyImageSettings,
  avatarImage as buildAvatarImage,
  imageServiceOf,
  legacyAvatarUrl,
  storagePathImage as buildStoragePathImage,
} from "./legacyImages.ts";
import type { PresetName } from "../../../sdk/src/images.ts";

/** The build settings of the legacy app (`VITE_ULAMS_IMAGE_*`, ADR 0193). */
export const imageSettings = (): LegacyImageSettings => ({
  service: VITE_ULAMS_IMAGE_SERVICE,
  sources: VITE_ULAMS_IMAGE_SOURCES,
  transformOrigin: VITE_ULAMS_IMAGE_TRANSFORM_ORIGIN,
  apiUrl: API_URL,
});

export const imageService = () => imageServiceOf(imageSettings());

export const avatarImage = (owner: AvatarOwner | null | undefined, width = 192): LegacyImage | undefined =>
  buildAvatarImage(owner, width, imageSettings());

export const storagePathImage = (path: string, width: number | undefined, preset: PresetName = "content") =>
  buildStoragePathImage(path, width, preset, imageSettings());

/**
 * `src` of an avatar. Without a file the app has always requested `/api/images/img?path=<empty>` (a placeholder is
 * shown by the callers that have one); that stays as it was.
 */
export const avatarSrc = (owner: AvatarOwner | null | undefined, width = 192): string =>
  avatarImage(owner, width)?.src ?? legacyAvatarUrl(API_URL, String(owner?.path_avatar));
