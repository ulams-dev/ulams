# 0101. Umami analytics on owned public sites

- Status: Proposed
- Date: 2026-10-12

## Context and problem statement

The owner requested Umami Cloud pageviews on the landing, documentation and six demos,
CTA events distinguishing actual demo openings from scrolling, and verification followed by
excluding the verification browser.

## Decision

Use the supplied website ID with the deferred Cloud script in the shared web layout and
Starlight head. Restrict tracking to ulams.app, www.ulams.app, ulams-dev.github.io and the six
production demo hosts. Do not include customer deployments, localhost, staging or author
previews. Allow cloud.umami.is in script-src and gateway.umami.is in connect-src only on owned
hosts. Use native data-umami-event attributes; do not install a second click listener.

Do not send user IDs or learner records. Exclude query strings and hashes; honour Do Not Track.
Keep the standard automatic pageviews and SPA navigation detection. Disable the verification
browser with the documented per-origin localStorage flag after one visit and CTA click.

## Consequences

Public analytics depends on the Cloud script and collector and can be blocked by extensions.
No new dependency, cookie, API migration or paid product feature is introduced. The website
ID is public, not a credential. Self-hosted customer sites have no tracker injected.

## References

- https://docs.umami.is/docs/collect-data
- https://docs.umami.is/docs/track-events
- https://docs.umami.is/docs/tracker-configuration
- https://docs.umami.is/docs/exclude-my-own-visits
