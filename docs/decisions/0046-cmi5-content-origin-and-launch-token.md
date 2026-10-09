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

## Implementation notes

- `lrs_launch_tokens` stores the SHA-256 of the launch token, the user, registration, AU and xAPI
  access. Before the first fetch `expires_at` ends a 10-minute launch window; the first fetch sets
  `used_at` and moves `expires_at` to the end of the session (`CMI5_SESSION_MINUTES`).
- The session token is `ulrs1.<payload>.<HMAC>` with a key derived from the tenant `APP_KEY`. The LRS
  guard also checks that the launch row is used and unexpired, so a session can be revoked by
  deleting the row. The session reads and writes only its registration (statements, state) and may
  read, never write, profiles.
- The LMS writes `LMS.LaunchData` and the learner preferences in process, no longer through an
  internal HTTP request carrying the learner's token.
- A `completed` or `passed` statement fires `AuCompletionReported`; `topic-types` completes the
  topics that use the AU for learners who may attend the course (as for SCORM, ADR 0018).
- CORS on `trax/api/*` already answers any origin without credentials, so the content origin needs
  no allow-list entry. This is consistent with ADR 0014, whose rule concerns credentialed routes.
- `POST /api/cmi5/fetch` is exempt from the Origin check (the AU calls it from the content origin)
  and throttled to 60 requests a minute.

## Consequences

- Good: an AU can no longer act as the learner on the rest of the API.
- Good: cmi5 matches SCORM's isolation.
- Bad: one more token type in the LRS guard, plus CORS from the content origin to `/api/lrs/*`.
