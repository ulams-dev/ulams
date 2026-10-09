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

### Production: two supported modes

| Mode | Example | Package code is | Use when |
|---|---|---|---|
| **Separate registrable domain** (strongest) | app `<slug>.ulams.app`, content `<slug>.ulams-content.net` | cross-site to the app | you can register and certify a second domain |
| **Same-site subdomain** (supported, the product owner's choice for `ulams.app`, 2026-10-09; ADR 0014, "Amended 2026-10-09") | app `<slug>.app.ulams.app`, API `<slug>.api.ulams.app`, content `<slug>.content.ulams.app` | same-site with the app | one domain is all you have |

Set `TENANCY_CONTENT_HOST={slug}.ulams-content.net` (separate domain) or
`TENANCY_CONTENT_HOST={slug}.content.ulams.app` (same-site) and run `ulams:tenant:sync-env`; the
value, with `TENANCY_SCHEME`, is written to each tenant as `CONTENT_ORIGIN`. Point that wildcard at the
proxy with the rules above and list it in the front/admin `frame-src`. Serving the files straight
from the bucket (a CDN with the same headers) is fine too; the API route is the simple default.

**What changes on the same site.** The browser sends `SameSite=Lax`/`Strict` cookies on requests
from `*.content.ulams.app` to `*.app.ulams.app` and `*.api.ulams.app`, so SameSite gives no CSRF
protection against package code; a page there can set cookies for the parent domain (cookie tossing,
session fixation) and shares process isolation (site-level) with the app. These mitigations make that
acceptable; each has a test:

1. **Host-only `__Host-` cookies.** In production every session or auth cookie uses the `__Host-`
   prefix (`Secure`, `Path=/`, no `Domain`), so a sibling subdomain cannot set or overwrite it, and
   nothing ever sets `Domain=`. API: `config/session.php` (`SESSION_COOKIE_PREFIX`; also the
   `XSRF-TOKEN` cookie and Horizon/Telescope, which use the same session), tested by
   `tests/Integrations/CookieHardeningTest.php`. Over plain http (`*.localhost`) the prefix is empty.
   The H5P and PDF services set no cookies. The Astro front (`front/web`) does the same.
2. **Exact-Origin checks.** `Ulams\Core\Http\Middleware\EnforceTrustedOrigin` (global) answers `403` to
   POST/PUT/PATCH/DELETE whose `Origin` is not `FRONTEND_URL`, `ADMIN_URL`, `APP_URL` or
   `TRUSTED_ORIGINS` (or, without `Origin`, whose `Sec-Fetch-Site` is not `same-origin`/`none`).
   Content origins never match, not even through the localhost patterns of development. Exempt by
   route: the players' tracking endpoints (`api/scorm/content/*/track`, `api/liascript/progress/*`,
   scoped `X-Ulams-Tracking-Token`, no ambient credentials; sandboxed frames send `Origin: null`),
   the LRS (`trax/api/*/xapi/std/*`), LTI and payment callbacks (signed, server to server). Nothing in
   the API authenticates by cookie alone: the default guard is the Passport bearer guard, there is no
   Sanctum, and nothing logs a user into a session (`CookieHardeningTest`). Tests:
   `packages/core/tests/Features/EnforceTrustedOriginTest.php`. The Astro front checks the same on
   its own routes.
3. **Sandboxed content frames.** Every player iframe carries `sandbox` (policies in
   `front/sdk/src/frames.ts`; `front/ui/tests/frames.test.ts` fails when a frame lacks one) and
   `referrerpolicy="no-referrer"`. Messages between page and player check `event.source` and the exact
   origin. No content player runs with an opaque origin, because each needs `allow-same-origin`:

   | Player | Why it keeps `allow-same-origin` |
   |---|---|
   | SCORM, cmi5, Adapt | The SCO finds `window.API` / `API_1484_11` by walking its parent frames; an opaque origin makes the player and the SCO cross-origin, so tracking never starts (verified in the Playwright harness) |
   | LiaScript | The build is a SCORM 1.2 SCO and also uses a Worker, localStorage/IndexedDB and a service worker; without the flag it fails with "Failed to construct 'Worker'" |
   | H5P | The embed page calls its own service with fetch and a bearer token, and the resize handshake needs real origins; H5P runs on the app host (`/h5p`), not the content origin |

   For these, mitigations 1, 2 and 4 carry the protection: package code has no ambient credentials to
   steal (no cookies on the content origin, tracking tokens scoped to one SCO), cannot overwrite
   `__Host-` cookies, and its writes to the app and API are refused by the Origin checks. Embeds
   (YouTube, Vimeo, LTI tools) run on their own origins.
4. **Response headers.** The content origin keeps its CSP and sends `Cross-Origin-Opener-Policy:
   same-origin`, `Cross-Origin-Resource-Policy: cross-origin` (public package files that
   opaque-origin players load; no credentials), `Access-Control-Allow-Origin: *` and `nosniff`
   (`ContentFileController` sends them too). The apps send `Cross-Origin-Resource-Policy:
   same-origin` on JSON (`ProtectJsonResponses` on the API, the Caddy site blocks for `/bff/*` and
   `/studio/api/*` of the front), so a content page cannot embed it in no-cors mode. CORP does not
   restrict CORS-mode `fetch`, which the SPAs use. Caddy also never reflects `Origin: null` or a
   content origin in `Access-Control-Allow-Origin`/`-Credentials`; only the tracking endpoints answer
   any origin, without credentials and with `Cookie` and `Authorization` dropped. Tests:
   `tests/Integrations/ContentOriginHeadersConfigTest.php`, `ContentFileTest`, `ProtectJsonResponsesTest`.
5. **Config.** `TENANCY_CONTENT_HOST` accepts `{slug}.content.ulams.app`; the tenancy env writer and
   `ulams:tenant:sync-env` write it as `CONTENT_ORIGIN` (`TenantNamingTest`,
   `CreateTenantCommandTest`). No CORS allow-list (`config/cors.php`, the H5P service's per-tenant
   list, Caddy) contains a content origin.

Residual risk of the same-site mode: cookies set before the upgrade without the `__Host-` prefix
stay writable from a sibling subdomain (clear them on rollout), and the content origin still shares a
site with the app for browser process isolation. The separate-domain mode has neither.

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
   (`sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-downloads"`; `allow-same-origin` is required here, see the table in the mitigations). The player removes the
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

