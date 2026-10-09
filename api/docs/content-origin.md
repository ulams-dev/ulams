# Content origin

Third-party packages (SCORM, cmi5, later Adapt builds and LiaScript) run arbitrary JavaScript.
They are served from a **per-tenant content origin** that holds nothing else: no API routes, no
cookies, no learner tokens.

| Tenant | Content origin | Files come from |
|---|---|---|
| platform | `http://content.localhost` | `http://api.localhost/api/content/...` |
| `<slug>` | `http://<slug>.content.localhost` | `http://<slug>.localhost/api/content/...` |

`ulams:tenant:create` writes `CONTENT_ORIGIN` to the tenant env file
(`TENANCY_CONTENT_HOST`, default `{slug}.content.localhost`). Existing tenants get it with
`php artisan ulams:tenant:sync-env`. For the platform set `CONTENT_ORIGIN=http://content.localhost`
in `.env`. Without `CONTENT_ORIGIN` the legacy SCORM player is used (feature flag per tenant).

## Caddy

`docker/conf/Caddyfile` (`content_origin` snippet):

- only `GET`/`HEAD` of `/scorm/*`, `/cmi5/*`, `/adapt/*`, `/liascript/*`, proxied to the tenant API's
  `GET /api/content/<path>` (`packages/uploads`, `ContentFileController`) with the header
  `X-Ulams-Content-Origin: 1`. The API reads the file from the package type's disk, local or bucket
  (`ulams_uploads.content_disks`), so one design covers both. Without a configured content origin, or
  without that header (a browser opening the API URL directly; the API site strips the header from
  client requests), the API answers 404, so package HTML never runs on the API origin. Everything
  else on the content origin is 404;
- `Cookie` and `Authorization` are dropped on the way in, `Set-Cookie` on the way out;
- `Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval';
  style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; media-src 'self' blob:;
  font-src 'self' data:; connect-src 'self' <tenant API>; frame-ancestors 'self' <tenant front>
  <tenant admin>; form-action 'none'; base-uri 'self'; object-src 'none'`.
  `unsafe-inline`/`unsafe-eval` are needed by most authoring tools and acceptable because the origin
  holds nothing else;
- `X-Content-Type-Options: nosniff`, `Referrer-Policy: no-referrer`.

The front and admin send a **report-only** CSP (`app_csp_report_only`) whose `frame-src` lists the
content origins and the H5P service. Enforce it after a week without reports. The storage origin
sends `nosniff` and a `script-src 'none'; sandbox` CSP for SVG files; the API also stores SVG,
HTML and XML uploads outside package paths with `Content-Disposition: attachment`
(`packages/uploads`).

**Production: a separate registrable domain** (default taken, product owner to confirm). The content
origin must not share a registrable domain with the app: e.g. app on `<slug>.ulams.app`, content on
`<slug>.ulams-content.net`. On a shared site (`<slug>.content.ulams.app`) package code would be
same-site with the app, which weakens SameSite cookies and lets it set cookies for the parent domain.
Set `TENANCY_CONTENT_HOST={slug}.ulams-content.net`, point that wildcard at the proxy with the rules
above, and list it in the front/admin `frame-src`. Serving the files straight from the bucket (a CDN
with the same headers) is fine too; the API route is the simple default.

The legacy file route on the API origin (`<disk url>/scorm/...`, `ScormFileController`, local disks
only) answers 404 when the tenant has a content origin; it remains for tenants without one.

## SCORM player

1. The learner opens a SCORM topic; the front calls `POST /api/scorm/launch/{sco}` with its
   Passport token.
2. The API publishes the player (`scorm/_player/player.html`, `player.js`, vendored
   `scorm-again.min.js`) to the SCORM disk if it changed, issues a **tracking token** bound to
   `(tenant APP_KEY, user, SCO)` and valid for `SCORM_TRACKING_TOKEN_TTL` seconds (default 4 h), and
   returns `<content origin>/scorm/_player/player.html#api=…&sco=…&token=…`.
3. The front (the Astro reference front `front/web` server-side, or the old React front) shows that URL in an iframe
   (`sandbox="allow-scripts allow-same-origin allow-forms allow-popups"`). The player removes the
   fragment from the address bar, reads the launch data (`GET /api/scorm/content/{sco}`) and loads
   the SCO in a same-origin iframe, so the SCO finds `window.API` / `window.API_1484_11`.
4. CMI changes go to `POST /api/scorm/content/{sco}/track` with the token in
   `X-Ulams-Tracking-Token` (not `Authorization`: Passport blanks any bearer header that is not one
   of its tokens).

The token cannot call any other endpoint, cannot be used for another SCO, and is rejected by every
other tenant (different `APP_KEY`). Publish the player by hand with
`php artisan ulams:scorm:publish-player`.

## Tests

- `packages/scorm/tests/API/ScormContentOriginApiTest.php`: launch, token scope, expiry, forgery,
  tenant isolation.
- `packages/scorm/tests/Integration/ContentOriginHeadersTest.php` (opt-in, against Caddy):
  `CONTENT_ORIGIN_INTEGRATION_URL=http://caddy vendor/bin/phpunit packages/scorm/tests/Integration`.

## Completion

Tracking writes go through `ScormTrackService`. When a learner's SCO reaches `completed` or `passed`
for the first time, `ScormScoCompleted` is dispatched and `topic-types` marks every SCORM topic using
that SCO complete for the learner (course access checked), which fires `TopicFinished` and the
lesson/course checks. Students hold `scorm_track-update` (seeded), so the legacy endpoint works for
them too.

