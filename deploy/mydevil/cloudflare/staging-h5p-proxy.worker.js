// ulams staging: /h5p/* of every API host goes to the H5P service on MyDevil (Caddyfile.snippet of api/h5p),
// which picks the tenant from X-Forwarded-Host.
export default {
  async fetch(request) {
    const url = new URL(request.url);
    const headers = new Headers(request.headers);
    headers.set('X-Forwarded-Host', url.hostname);
    return fetch(new Request(`https://staging-h5p.ulams.app${url.pathname}${url.search}`, { method: request.method, headers, body: request.body, redirect: 'manual' }));
  },
};
