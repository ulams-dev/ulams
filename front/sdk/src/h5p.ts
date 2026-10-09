/**
 * Helpers for framing the H5P service's player page (`/h5p/embed/play/:id`) and its
 * postMessage protocol (api/h5p/README.md). H5P itself (GPL) runs only inside the service.
 */

export interface H5PEmbedPlayParams {
  language?: string;
  contextId?: string;
  readOnlyState?: boolean;
  hideActions?: boolean;
}

export type H5PEmbedToParent =
  | { type: "ulams-h5p:ready"; mode: "play" | "edit"; contentId: string }
  | { type: "ulams-h5p:loaded"; contentId: string; title?: string; library?: string }
  | { type: "ulams-h5p:resize"; height: number }
  | { type: "ulams-h5p:xapi"; statement: XapiStatement; contentId: string }
  | { type: "ulams-h5p:saved"; contentId: string; metadata: unknown }
  | { type: "ulams-h5p:error"; message: string; code?: string };

export type H5PParentToEmbed =
  | { type: "ulams-h5p:token"; token: string | null }
  | { type: "ulams-h5p:style"; css?: string; urls?: string[] }
  | { type: "ulams-h5p:save" };

export interface XapiStatement {
  verb?: { id?: string };
  result?: { success?: boolean; completion?: boolean; score?: { scaled?: number } };
  [key: string]: unknown;
}

export function h5pEmbedPlayUrl(apiUrl: string, contentId: string | number, params: H5PEmbedPlayParams = {}): string {
  const query = new URLSearchParams();
  if (params.language) query.set("language", params.language);
  if (params.contextId) query.set("contextId", params.contextId);
  if (params.readOnlyState) query.set("readOnlyState", "yes");
  if (params.hideActions) query.set("hideActions", "1");
  const qs = query.toString();
  return `${apiUrl.replace(/\/+$/, "")}/h5p/embed/play/${encodeURIComponent(String(contentId))}${qs ? `?${qs}` : ""}`;
}

/** Origin the embed page posts from; messages from anywhere else must be ignored. */
export function h5pEmbedOrigin(apiUrl: string, base = "http://localhost"): string {
  return new URL(apiUrl, base).origin;
}

export const isH5PEmbedMessage = (data: unknown): data is H5PEmbedToParent =>
  !!data &&
  typeof data === "object" &&
  typeof (data as { type?: unknown }).type === "string" &&
  (data as { type: string }).type.startsWith("ulams-h5p:");

/** True for statements that finish the activity (completed / passed, or a successful result). */
export function isCompletingStatement(statement: XapiStatement | undefined): boolean {
  if (!statement) return false;
  const verb = statement.verb?.id ?? "";
  return (
    statement.result?.success === true ||
    statement.result?.completion === true ||
    /\/(completed|passed|mastered)$/.test(verb)
  );
}
