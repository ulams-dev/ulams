/**
 * Sandbox policy for the iframes that run course content, and the message check for the
 * postMessage channels between a host page and such a frame (ADR 0014, amended 2026-10-09).
 *
 * Package content (SCORM, cmi5, Adapt, LiaScript, H5P) is third-party code. In production the content
 * origin can be a subdomain of the app's own site, so a frame without `allow-same-origin` would be
 * the ideal: an opaque origin is cross-site, carries no cookies and cannot touch storage. Where a
 * player cannot work that way the reason is recorded next to its flag set below.
 */

const BASE = ["allow-scripts", "allow-forms", "allow-popups", "allow-downloads"] as const;

/**
 * SCORM / cmi5 / Adapt players on the tenant content origin. The SCO finds `window.API` /
 * `window.API_1484_11` by walking up its parent frames; with an opaque origin the player page and
 * the SCO are cross-origin and the lookup fails, and the SCO cannot be rewritten to use postMessage.
 * Needs `allow-same-origin`; protected by the `__Host-` cookies and exact-origin checks instead.
 */
export const SANDBOX_SCORM = [...BASE, "allow-same-origin"].join(" ");

/**
 * LiaScript player (content origin). The build runs as a SCORM 1.2 SCO (same parent-frame API
 * lookup as above) and keeps its state in localStorage / IndexedDB and a service worker, which an
 * opaque origin forbids. Needs `allow-same-origin`.
 */
export const SANDBOX_LIASCRIPT = [...BASE, "allow-same-origin"].join(" ");

/**
 * H5P player and editor (the H5P service, reached on the API host or through the front's /h5p
 * proxy). The embed page calls its own service with fetch/XHR and a bearer token, and answers the
 * parent's handshake against an allow-list of real origins; both break on an opaque origin (CORS
 * and the `null` origin). Needs `allow-same-origin`. Dropped on purpose: top navigation, pointer lock.
 */
export const SANDBOX_H5P = [...BASE, "allow-same-origin", "allow-modals", "allow-presentation", "allow-popups-to-escape-sandbox"].join(" ");

/**
 * Third-party video and tool origins (YouTube, Vimeo, LTI tools): another site's origin, never ours,
 * so `allow-same-origin` only keeps their own origin working (their cookies and storage).
 */
export const SANDBOX_THIRD_PARTY = [...BASE, "allow-same-origin", "allow-modals", "allow-presentation", "allow-popups-to-escape-sandbox"].join(" ");

/** The strictest policy for frames that can run without their own origin (opaque origin). */
export const SANDBOX_OPAQUE = [...BASE, "allow-modals", "allow-presentation"].join(" ");

export interface FrameMessageCheck {
  /** The frame element's window (`iframe.contentWindow`). */
  frame: Window | null | undefined;
  /** The exact origin the frame's page is served from; `"null"` for an opaque-origin (sandboxed) frame. */
  origin: string;
}

/**
 * Accept a message only from the frame we embedded: same window object AND the exact expected
 * origin. For an opaque-origin frame `event.origin` is the string "null", so pass `origin: "null"`
 * and also check a per-launch nonce inside the payload.
 */
export function isTrustedFrameMessage(event: { source: unknown; origin: string }, expected: FrameMessageCheck): boolean {
  return !!expected.frame && event.source === expected.frame && event.origin === expected.origin;
}

/**
 * `targetOrigin` for postMessage to a frame. `"*"` is only valid (and unavoidable) for an
 * opaque-origin frame, so post nothing secret to it.
 */
export function frameTargetOrigin(origin: string): string {
  return origin === "null" ? "*" : origin;
}
