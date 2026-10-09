# 0046. cmi5 on the content origin with a one-time launch token and an LRS-only session token

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/leftovers-0-2.md` (L0-09)

## Context and problem statement

cmi5 has three problems today:

- **Files.** AU files sit on the local disk and play from the API origin.
- **Permissions.** Students lack the read permission.
- **Token exposure.** `LrsService::launchParams` puts the learner's full Passport token into the
  `fetch` URL, which is passed to third-party AU JavaScript.

## Considered options

1. Serve AUs from the content origin. `fetch` exchanges a one-time launch token for an LRS-only
   session token.
2. Serve AUs from the content origin and keep the Passport token.
3. Proxy every LRS call through the BFF.

## Decision

Option 1:

- **Storage and serving.** AUs move to the tenant bucket and are served from
  `<slug>.content.<domain>/cmi5/*` through `/api/content`.
- **Launch token.** The launch URL carries a random one-time token, stored hashed in
  `lrs_launch_tokens`.
- **LRS session token.** `POST /api/cmi5/fetch` returns an HMAC-signed token scoped to LRS
  statements, bound to the registration and AU, valid for 120 minutes. Repeat fetches within the
  session return the same token. The LRS guard accepts it, and every other API guard rejects it.
- **Permissions.** Students get `cmi5_read`, and deletion requires a new `cmi5_delete` permission.

## Consequences

- Good: an AU can no longer act as the learner on the rest of the API.
- Good: cmi5 matches SCORM's isolation.
- Bad: one more token type in the LRS guard, plus CORS from the content origin to `/api/lrs/*`.
