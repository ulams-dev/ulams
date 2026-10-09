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

## Consequences

- Good: an enforced CSP on learner pages that still lets tool launches work.
- Bad: the admin frame policy is broader than the front's (documented risk).
- Bad: the CSP is now set in two places, Astro middleware and the proxy.
- Default pending #52.
