// ulams staging content origin (api/docs/content-origin.md): GET/HEAD of package files only, proxied to the
// tenant API's /api/content/... with X-Ulams-Content-Origin, cookies dropped, CSP and CORP headers added.
const ALLOWED = /^\/(scorm|cmi5|adapt|liascript|interactive)\//;
export default {
  async fetch(request) {
    const url = new URL(request.url);
    const m = url.hostname.match(/^(?:(.+)-)?staging-content\.ulams\.app$/);
    if (!m) return new Response('not found', { status: 404 });
    if (url.pathname === '/robots.txt') return new Response('User-agent: *\nDisallow: /\n', { headers: { 'content-type': 'text/plain' } });
    if (!['GET', 'HEAD'].includes(request.method) || !ALLOWED.test(url.pathname)) return new Response('not found', { status: 404 });
    const p = m[1] ? m[1] + '-' : '';
    const api = `${p}staging-api.ulams.app`, front = `${p}staging.ulams.app`, admin = `${p}staging-admin.ulams.app`;
    const headers = new Headers(request.headers);
    headers.delete('cookie'); headers.delete('authorization');
    headers.set('X-Ulams-Content-Origin', '1');
    const upstream = await fetch(`https://${api}/api/content${url.pathname}${url.search}`, { method: request.method, headers, redirect: 'manual' });
    const h = new Headers(upstream.headers);
    h.delete('set-cookie');
    // interactive packages: the API sets the CSP of the package version (ADR 0086, no unsafe-eval); keep it
    if (!(url.pathname.startsWith('/interactive/') && h.get('Content-Security-Policy'))) h.set('Content-Security-Policy', `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; media-src 'self' blob:; font-src 'self' data:; connect-src 'self' https://${api}; frame-ancestors 'self' https://${front} https://${admin}; form-action 'none'; base-uri 'self'; object-src 'none'`);
    h.set('X-Content-Type-Options', 'nosniff');
    h.set('Referrer-Policy', 'no-referrer');
    h.set('Cross-Origin-Opener-Policy', 'same-origin');
    h.set('Cross-Origin-Resource-Policy', 'cross-origin');
    h.set('Access-Control-Allow-Origin', '*');
    return new Response(upstream.body, { status: upstream.status, headers: h });
  },
};
