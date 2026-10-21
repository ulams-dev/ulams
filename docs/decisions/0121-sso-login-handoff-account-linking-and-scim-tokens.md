# 0121. SSO login handoff by one-time code, strict account linking and SCIM tokens on service users

- Status: Accepted
- Date: 2026-10-21
- Plan: `docs/plans/phase-6-identity.md` (4.3, 4.5, 4.7)

## Context and problem statement

The current social login redirects to an unvalidated `return_url` with the access token in the query string,
uses an unsigned `state`, and links any existing account whose email matches, marking it verified. SAML and
OIDC login would inherit these weaknesses if they reused the flow. SCIM clients also need long-lived bearer
tokens, while every Passport token belongs to a user (ADR 0074).

## Considered options

1. Server-side state plus a one-time code exchanged by the front-end server with a browser-bound verifier;
   linking by email only under explicit trust; SCIM tokens as scoped Passport tokens of a per-tenant service user.
2. Keep the token-in-URL redirect but restrict `return_url`; link by email always; a separate hashed SCIM token
   table with its own guard.
3. Sessions with cookies on the API host for SSO, and a static SCIM secret in the provider row.

## Decision

Option 1.

- **Handoff.** `auth` gets `LoginHandoffService` with `login_handoff_states` (10 minutes, single use, holds the
  allow-listed return URL, the front-end S256 challenge and protocol data) and `login_handoff_codes` (hashed,
  60 seconds, single use). The API redirects with `?code=`; the front-end server posts `{code, verifier}` to the
  exchange endpoint and stores the Passport token in its `__Host-` cookie. Social login moves to the same flow.
- **Linking.** An IdP subject is linked once (`identity_links`, unique per provider and subject). An existing
  local account is linked automatically only when the provider is trusted for the email's domain and the email is
  asserted verified, and never for admins; otherwise the user confirms with their password or an admin links.
- **SCIM tokens.** A per-provider service user without a password holds Passport tokens with
  `api_token_meta.kind = scim` and scope `scim:write`; SCIM routes accept only that kind, and that kind is
  refused everywhere else. Issued once, `ulams_pat_` prefix, at most two active per provider.
- **Deactivation** revokes all of a user's tokens (listener on `AccountBlocked`).

## Consequences

- Good: no token in URLs or browser history; login CSRF and code injection fail without the verifier.
- Good: SCIM reuses token listing, revocation, rate limits and the scope coverage test.
- Bad: clients that read `?token=` (the legacy `front/src` app) must switch to the exchange.
- Bad: one service user per SCIM-enabled provider appears in the users table (hidden from lists, cannot log in).

## Implementation notes

- ID-0 (social login) ships the handoff: the state string handed to the provider is signed (HMAC with a key
  derived from the tenant `APP_KEY`; nonce, protocol, provider, host and expiry) on top of the server-side row,
  so a forged or foreign state is refused before any lookup. The code is bound to the tenant host as well as to the verifier.
- Automatic linking by e-mail also excludes tutors, not only admins and superadmins, and an unconfirmed local
  account that is claimed this way loses its password and tokens first.
