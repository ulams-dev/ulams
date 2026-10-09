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
