# 0012. LTI 1.3: one `lti` package, first-party platform side, packbackbooks tool side

- Status: Accepted (2026-10-09)
- Date: 2026-10-09

## Context and problem statement

The spec (1.3, high priority) asks for both directions of LTI 1.3: as a **platform** we launch
external tools (GeoGebra, coding sandboxes, ...) inside lessons with grade passback (AGS) and deep
linking; as a **tool** other LMSs (Moodle, Canvas) launch our courses. Security requirements: key
rotation, nonce/state validation, per-tenant registrations. Tenancy is database per tenant with a
per-tenant `APP_KEY` (ADR 0007). Courses are shown to learners in iframes, where third-party cookies
are usually blocked.

## Considered options

| Side | Option | Licence | Notes |
|---|---|---|---|
| Platform | **First-party on `firebase/php-jwt`** (already a dependency) | BSD-3-Clause | A handful of endpoints we must own anyway: OIDC authorize, JWKS, token, AGS, deep-linking return |
| Platform | `celtic/lti` | LGPL-3.0 | Licence conflicts with the open-core plan |
| Platform | `oat-sa/lib-lti1p3-core` | GPL-2.0 | Licence |
| Tool | **`packbackbooks/lti-1p3-tool` 6.4** | Apache-2.0 | Mature, IMS-certified tool library; launch validation, OIDC, deep linking, AGS/NRPS clients; pure PHP on php-jwt 7 and Guzzle (both present) |
| Tool | First-party | - | Re-implements security-critical validation |
| Both | `ltijs` | Apache-2.0 | Node: one more service to run, against principle 8 |

## Decision

One package `api/packages/lti` (`Ulams\Lti`) with `Platform` and `Tool` namespaces sharing the key
set, the nonce store, claim names and the SSRF-safe HTTP client.

- **Keys**: per tenant (`lti_keys`, private key encrypted with the tenant `APP_KEY`), states `next`,
  `active`, `retired`. `ulams:lti:rotate-keys` promotes next to active monthly (scheduler) and keeps
  retired keys in the JWKS for 30 days. Provisioning step `lti_keys` creates the set. JWKS at
  `/api/lti/jwks` and `/.well-known/jwks.json`.
- **Platform**: `POST /api/lti/launches/{topic}` returns the tool's OIDC login URL with a signed
  (HS256, `APP_KEY`-derived), 2-minute, single-use `login_hint`; no cookies, so it works in iframes.
  `/api/lti/platform/authorize` checks client, registered redirect URI and hint and posts an RS256
  `id_token`. AGS: client-credentials tokens from a JWT assertion (jti single use), line items scoped
  to tool and course, append-only scores, a `Completed`/`FullyGraded` score completes the topic
  through `CourseProgressRepository`. Deep linking creates `LtiLink` topics through
  `TopicRepository`. The topic type `LtiLink` is a normal topic-type registration.
- **Tool**: `packbackbooks/lti-1p3-tool` validates launches. Its cookie and cache interfaces are
  implemented on the tenant database: the OIDC `state` is stored server-side (single use, 10 min)
  instead of in a cookie. Users are mapped by `(platform, sub)`, never by e-mail; Instructor becomes
  tutor, nobody becomes admin. Course access through `CourseAccessService`; the front gets a 60-second
  one-time code it exchanges for a Passport token. Grade passback: a `TopicFinished` listener queues an
  AGS score (course progress percentage) with retries.
- **Outgoing HTTP** (JWKS, tokens, AGS) only to `https` URLs on public addresses, no redirects, with
  the resolved address pinned (`LTI_ALLOW_INSECURE_URLS` for development only).
- **Passport interplay**: global middleware resolves the Passport user, and Passport blanks any
  bearer header that is not one of its tokens. AGS paths move the header aside before that
  (`IsolateLtiBearer`).

## Consequences

- One new runtime dependency (`packbackbooks/lti-1p3-tool`, Apache-2.0, ~200 KB, no services).
- Platform-side conformance is ours to keep: covered by feature tests against an in-test fake tool;
  the 1EdTech/saLTIre round trip and a Moodle profile (`lti-e2e`) are still to be added (M1.9).
- Server-side state trades the browser binding of a state cookie for working inside LMS iframes;
  the nonce bound to the state still ties an `id_token` to its login.
- The LTI Client-Side OIDC (platform storage via `postMessage`) is not implemented yet.
- NRPS (names and roles) is not offered on the platform side yet.
