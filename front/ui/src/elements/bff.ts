import { createClient } from "@ulams/sdk";

/**
 * Browser-side client. Requests go to the page's own server (`/bff/...`), which adds the
 * session token from the httpOnly cookie and forwards them to the tenant API.
 */
export const bff = createClient({ baseUrl: "/bff", timeoutMs: 20_000 });

/** Notifies the progress element that the current topic is finished. */
export function announceComplete(source: string): void {
  document.dispatchEvent(new CustomEvent("ulams:complete", { detail: { source } }));
}

/** Runs `fn` once the page is visible to the user (not while prerendering). */
export function whenActive(fn: () => void): void {
  const doc = document as Document & { prerendering?: boolean };
  if (doc.prerendering) {
    document.addEventListener("prerenderingchange", () => fn(), { once: true });
  } else {
    fn();
  }
}
