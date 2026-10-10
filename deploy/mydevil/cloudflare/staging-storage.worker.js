// ulams staging storage: public read of the staging R2 buckets under /<bucket>/<key> (R2 has no bucket policies).
const BUCKETS = { 'ulams-staging': 'B_ULAMS_STAGING', 'ulams-staging-coffee': 'B_ULAMS_STAGING_COFFEE', 'ulams-staging-gravity': 'B_ULAMS_STAGING_GRAVITY', 'ulams-staging-poland': 'B_ULAMS_STAGING_POLAND', 'ulams-staging-ulam': 'B_ULAMS_STAGING_ULAM', 'ulams-staging-oncall': 'B_ULAMS_STAGING_ONCALL', 'ulams-staging-nightsky': 'B_ULAMS_STAGING_NIGHTSKY' };
export default {
  async fetch(request, env) {
    if (request.method === 'OPTIONS') return new Response(null, { status: 204, headers: cors() });
    if (request.method !== 'GET' && request.method !== 'HEAD') return new Response('method not allowed', { status: 405 });
    const url = new URL(request.url);
    if (url.pathname === '/robots.txt') return new Response('User-agent: *\nDisallow: /\n', { headers: { 'content-type': 'text/plain', 'x-robots-tag': 'noindex' } });
    const parts = url.pathname.replace(/^\/+/, '').split('/');
    const bucket = env[BUCKETS[parts.shift()]];
    const key = decodeURIComponent(parts.join('/'));
    if (!bucket || !key) return new Response('not found', { status: 404, headers: cors() });
    const obj = request.method === 'HEAD' ? await bucket.head(key) : await bucket.get(key, { range: request.headers, onlyIf: request.headers });
    if (!obj) return new Response('not found', { status: 404, headers: cors() });
    const h = new Headers(cors());
    obj.writeHttpMetadata(h);
    h.set('etag', obj.httpEtag);
    h.set('accept-ranges', 'bytes');
    h.set('cache-control', 'public, max-age=300');
    if (!h.get('content-type')) h.set('content-type', 'application/octet-stream');
    if (!('body' in obj)) return new Response(null, { status: obj.size === undefined ? 304 : 200, headers: h });
    return new Response(obj.body, { status: request.headers.has('range') ? 206 : 200, headers: h });
  },
};
function cors() { return { 'access-control-allow-origin': '*', 'x-robots-tag': 'noindex, nofollow' }; }
