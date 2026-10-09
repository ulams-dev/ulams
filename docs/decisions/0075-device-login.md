# 0075. Device login with our own RFC 8628 flow approved in the web app; Passport's device grant stays off

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/cli.md` (5.3); owner question #74

## Context and problem statement

`ulams login` should not ask for a password. Passport 13 ships a device grant, disabled in `e6c21b9e`
because nothing used it.

## Considered options

1. Re-enable Passport's device grant.
2. Our own device flow with the RFC 8628 wire format, issuing scoped personal access tokens (ADR 0074),
   approved on a page in the web app.
3. A localhost redirect (authorization code + PKCE with a loopback listener).

## Decision

Option 2 (default, pending #74). Passport's approval routes need a session-logged-in user on the API
host, which the headless, token-only API does not have, and they issue OAuth client tokens with refresh
tokens rather than named, scoped, revocable tokens. Option 3 does not work over SSH or in containers.
Endpoints `POST /api/auth/device/code` and `POST /api/auth/device/token` use RFC 8628 field names and
errors; the user approves at `/cli/authorize` in the web app, where every role can log in, and may
narrow scopes and expiry. Codes expire in 10 minutes; approval is throttled.

## Consequences

- Good: standard client behaviour, works over SSH, tokens appear in the token list and audit log.
- Bad: we own a small security-sensitive flow (phishing via shared codes is mitigated by showing client,
  IP and scopes on the approval page).
