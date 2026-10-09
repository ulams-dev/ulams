# 0044. Content Security Policy: report collector, enforcement, tool origins from the API

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/leftovers-0-2.md` (L1-05)

## Context and problem statement

The front and admin CSP is report-only and nothing collects the reports. `frame-src` is a static
list in Caddy, so registered LTI tools cannot be framed once the policy is enforced. cmi5 still plays
from the API origin; it moves to the content origin in L0-09.

## Considered options

1. A report collector, then enforcement. The front builds `frame-src` per request from the
   registered tool origins. The admin allows `https:` frames.
2. The same as 1, but the admin uses an exact list from runtime config.
3. Keep report-only.

## Decision

Option 1:

- **Collector.** A rate-limited collector at `POST /api/csp-report` stores aggregated reports (host
  only, no query strings) for 30 days.
- **Front.** The CSP header moves from Caddy to Astro middleware, so `frame-src` can include the
  tenant's tool origins from `GET /api/lti/frame-origins` (cached for 5 minutes).
- **Admin.** The admin policy, still set by the proxy, allows `frame-src 'self' <content origins>
  https:`. Admins are trusted staff and frame tools only for deep linking.
- **Enforcement.** `CSP_ENFORCE` switches enforcement on; operators turn it on after 7 days with no
  unexpected reports. The dev stack enforces by default.

## Implementation notes

- **Collector.** `POST /api/csp-report` stores one row per directive, blocked host and page path in
  `csp_reports` (count, first and last seen). It accepts `application/csp-report` and
  `application/reports+json`, takes at most 16 KB and 20 violations per request, is throttled to 60
  requests a minute per IP, and is exempt from the Origin check (content origins and sandboxed frames
  report). Caddy opens exactly this path to any origin without credentials. `csp-reports:prune` runs
  daily and deletes rows not seen for `CSP_REPORT_RETENTION_DAYS` (30). Admins read
  `GET /api/admin/csp-reports`.
- **Front.** `front/web/src/lib/csp.ts` builds the policy per request; `frame-src` holds the page, the
  tenant's content origin (`ULAMS_CONTENT_ORIGIN`), the upload origins, the oEmbed providers and the
  origins from `GET /api/lti/frame-origins` (enabled tools; public; cached 5 minutes in the BFF and by
  clients). Every origin is checked to be a plain `http(s)` origin before it enters a source list. The
  proxied H5P pages keep the policy of the H5P service.
- **Admin and content origins.** The Caddy `app_csp_admin` snippet and the `content_origin` snippet carry
  `report-uri` and `report-to` (with `Reporting-Endpoints`) pointing to the tenant API.
- **Default.** `CSP_ENFORCE` unset means enforced outside `NODE_ENV=production` (the dev stack), and
  report-only in the production image; the admin header name comes from `ULAMS_CSP_HEADER`.

## Consequences

- Good: an enforced CSP on learner pages that still lets tool launches work.
- Bad: the admin frame policy is broader than the front's (documented risk).
- Bad: the CSP is now set in two places, Astro middleware and the proxy.
- Default pending #52.
