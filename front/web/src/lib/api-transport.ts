import type { Tenant } from "@ulams/sdk";

/** Server-only private gateway transport; public API URLs still identify and isolate tenants. */
export function tenantFetch(tenant: Pick<Tenant,"apiUrl">, internalOrigin = process.env.ULAMS_API_INTERNAL_ORIGIN, fetcher: typeof fetch = fetch): typeof fetch {
  if (!internalOrigin) return fetcher;
  const publicApi = new URL(tenant.apiUrl);
  const gateway = new URL(internalOrigin);
  if (!['http:', 'https:'].includes(gateway.protocol) || gateway.username || gateway.password || gateway.search || gateway.hash || gateway.pathname !== '/') throw new Error('ULAMS_API_INTERNAL_ORIGIN must be an HTTP(S) origin without credentials or a path.');
  return async (input, init) => {
    const request = new Request(input, init);
    const url = new URL(request.url);
    if (url.origin !== publicApi.origin) return fetcher(request);
    const headers = new Headers(request.headers);
    headers.set('X-Ulams-Api-Host', publicApi.host);
    headers.set('X-Forwarded-Proto', publicApi.protocol.slice(0,-1));
    const target = new URL(url.pathname + url.search, gateway);
    return fetcher(new Request(target, { ...request, method: request.method, headers, body: request.method === 'GET' || request.method === 'HEAD' ? undefined : request.body, signal: request.signal, redirect: request.redirect, ...(request.body ? { duplex: 'half' } : {}) } as RequestInit));
  };
}
