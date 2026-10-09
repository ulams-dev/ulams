# 0014. Third-party packages run on a per-tenant content origin, files served through the API

- Status: Accepted (2026-10-09)
- Date: 2026-10-09

## Context and problem statement

SCORM, cmi5, Adapt builds and LiaScript courses are third-party HTML and JavaScript. Before Phase 1
they ran on the API origin or the shared `storage.localhost` origin, where they could read learner
tokens, call the API as the learner and reach other tenants' files. Packages live on different disks
(local for SCORM in development, the tenant bucket for LiaScript and production), and the players
need to report progress without the learner's Passport token. Phase 1 decisions 3, 11, 13, 37 and 40
(`docs/plans/phase-1.md`, section 14).

## Considered options

1. Keep packages on the API or storage origin and sanitise them: impossible for arbitrary JavaScript.
2. **A per-tenant content origin that holds nothing but package files, behind a proxy that reads
   them through the tenant API.**
3. A content origin served straight from the bucket (CDN): no local-disk support, CSP and header
   rules move to the CDN configuration.

## Decision

Option 2, with option 3 allowed in production.

- Every tenant gets `CONTENT_ORIGIN` in its env file (`TENANCY_CONTENT_HOST`, default
  `{slug}.content.localhost`; production on a separate registrable domain). No database column;
  unset means the legacy player.
- Caddy proxies only `GET`/`HEAD` of `/scorm/*`, `/cmi5/*`, `/adapt/*`, `/liascript/*` to the tenant
  API's `GET /api/content/<path>` with `X-Ulams-Content-Origin: 1` (stripped from client requests on
  the API site). The API reads the file from the package type's disk, local or bucket, and answers
  404 without the header, so package HTML never runs on the API origin. Cookies and `Authorization`
  are dropped; a strict CSP limits `connect-src` to the tenant API and `frame-ancestors` to the
  tenant front and admin.
- Players report progress with a **stateless, topic- or SCO-scoped tracking token** (HMAC with a key
  derived from the tenant `APP_KEY`, 4 h), passed in the URL fragment and sent as
  `X-Ulams-Tracking-Token`, never the learner's Passport token.
- The Astro front launches packages server-side with the learner's session and frames them sandboxed,
  without a full-screen link (the URL carries the one-off token).

## Consequences

- One PHP request per package file; acceptable for courses, and production may switch to a CDN with
  the same headers without code changes.
- The legacy per-tenant SCORM file route on the API origin answers 404 for tenants with a content
  origin. cmi5 still plays from the API origin (follow-up).
- Front and admin CSP is report-only until a report collector exists.
- Details: `api/docs/content-origin.md`.

## Amended 2026-10-09: same-site content subdomain

**Owner decision (2026-10-09).** Production serves content origins from `{slug}.content.ulams.app`,
on the same registrable domain as the app (`ulams.app`, `*.ulams.app`, `*.admin.ulams.app`,
`*.api.ulams.app`), instead of the separate domain recorded above as the default. The separate
domain remains supported and is the strongest option for self-hosters.

**Trade-off.** Package code is same-site with the app. Browsers send `SameSite=Lax`/`Strict` cookies
on its requests to the app's hosts, so `SameSite` offers no CSRF protection against it; it can set
cookies on the parent domain (cookie tossing, session fixation); and it shares site-level process
isolation with the app. We accept this in exchange for one domain, one wildcard certificate and no
second registrable domain to operate.

**Mitigations (all shipped, each with tests; also on for the separate domain).**

1. Every session cookie of the Astro front, the studio and the API (session, `XSRF-TOKEN`) uses the
   `__Host-` prefix in production (`Secure`, `Path=/`, no `Domain`); development over http uses a
   configurable fallback name. No `Domain=` anywhere.
2. Every state-changing request to the front, the API (uploads and the course builder included) is
   refused unless its `Origin` is one of the tenant's own app origins (or `Sec-Fetch-Site` is
   `same-origin`); content origins and `Origin: null` get 403. Exempt: routes authenticated by
   non-ambient credentials (tracking tokens, LTI, payment callbacks). The API authenticates by bearer
   token only, with no cookie-only endpoint. CORS allow-lists never include content origins.
3. All player iframes are sandboxed with `referrerpolicy="no-referrer"` and verify `postMessage`
   source and origin. SCORM, cmi5, Adapt, LiaScript and H5P keep `allow-same-origin`, because the SCO
   finds `window.API` through its parent frame, LiaScript needs a Worker and storage, and H5P calls
   its service with fetch; the protection for these comes from 1, 2 and 4.
4. The content origin sends the CSP, `nosniff`, `Cross-Origin-Opener-Policy: same-origin` and
   `Cross-Origin-Resource-Policy: cross-origin`; app JSON sends `Cross-Origin-Resource-Policy:
   same-origin`, so content pages cannot embed it.
5. `TENANCY_CONTENT_HOST={slug}.content.ulams.app` works with `ulams:tenant:sync-env`.

Consequence: sessions created before the rollout used unprefixed cookie names and must be re-created.
Details: `api/docs/content-origin.md`, `front/docs-site` (Operators, Content origin).
