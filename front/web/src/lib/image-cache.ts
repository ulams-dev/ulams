/**
 * Memory cache for Astro's on-demand image endpoint (`/_image`). Without it the server
 * re-encodes the same course image with sharp for every new visitor.
 */
interface CachedImage {
  body: ArrayBuffer;
  headers: [string, string][];
  storedAt: number;
}

const MAX_BYTES = 64 * 1024 * 1024;
const TTL = 60 * 60_000;
const images = new Map<string, CachedImage>();
let bytes = 0;

function remember(key: string, value: CachedImage): void {
  images.set(key, value);
  bytes += value.body.byteLength;
  while (bytes > MAX_BYTES) {
    const oldest = images.keys().next().value;
    if (oldest === undefined) break;
    bytes -= images.get(oldest)?.body.byteLength ?? 0;
    images.delete(oldest);
  }
}

export async function imageCache(url: URL, render: () => Promise<Response>): Promise<Response> {
  const key = url.search;
  const hit = images.get(key);
  if (hit && Date.now() - hit.storedAt < TTL) {
    return new Response(hit.body.slice(0), { status: 200, headers: [...hit.headers, ["X-Ulams-Image-Cache", "hit"]] });
  }
  const response = await render();
  if (!response.ok || !response.body) return response;
  const body = await response.arrayBuffer();
  const headers: [string, string][] = [...response.headers.entries()];
  if (!response.headers.has("Cache-Control")) headers.push(["Cache-Control", "public, max-age=31536000, immutable"]);
  remember(key, { body, headers, storedAt: Date.now() });
  return new Response(body.slice(0), { status: response.status, headers });
}
