import * as API from "../types";

/**
 * H5P runs in the separate H5P service (GPL, api/h5p) under the API host.
 * The LMS only frames its embed pages and talks to them over postMessage, so
 * no H5P/Lumi code is bundled here.
 */
export function h5pEmbedPlayUrl(
  apiUrl: string,
  contentId: string | number,
  params: API.H5PEmbedPlayParams = {}
): string {
  const query = new URLSearchParams();
  if (params.language) query.set("language", params.language);
  if (params.contextId) query.set("contextId", params.contextId);
  if (params.readOnlyState) query.set("readOnlyState", "yes");
  if (params.hideActions) query.set("hideActions", "1");
  const qs = query.toString();
  return `${apiUrl}/h5p/embed/play/${encodeURIComponent(String(contentId))}${
    qs ? `?${qs}` : ""
  }`;
}

/** Origin the embed pages post from (messages from elsewhere are ignored). */
export function h5pEmbedOrigin(apiUrl: string): string {
  return new URL(
    apiUrl,
    typeof window !== "undefined" ? window.location.href : "http://localhost"
  ).origin;
}

export const isH5PEmbedMessage = (
  data: unknown
): data is API.H5PEmbedToParent =>
  !!data &&
  typeof data === "object" &&
  typeof (data as { type?: unknown }).type === "string" &&
  (data as { type: string }).type.startsWith("ulams-h5p:");
