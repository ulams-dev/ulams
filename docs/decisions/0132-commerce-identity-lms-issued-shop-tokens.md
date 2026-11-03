# 0132. Single login for commerce: LMS-issued shop tokens trusted by Sylius

- Status: Proposed
- Date: 2026-10-21
- Plan: `docs/plans/phase-6-commerce.md` (section 9)

## Context and problem statement

Learners must buy without a second account. The LMS is the identity source; the Sylius customer is created or
linked on first checkout. Sylius has no SSO/OIDC for shop users, and the LMS does not expose OAuth/OIDC endpoints
(Passport routes are disabled).

## Considered options

1. The LMS issues a short-lived RS256 JWT (`aud=sylius`, `sub=<tenant>:<user id>`, `channel`, 10 minutes) with a
   per-tenant commerce key published as JWKS; a bridge authenticator on Sylius's `api_shop` firewall verifies it
   and finds or creates the Customer by `ulams_user_ref`. The Astro BFF calls Sylius server-side.
2. Turn the LMS into a full OIDC provider (Passport routes + an OIDC layer) and add an OIDC client to Sylius.
3. Guest checkout by e-mail, linked to the LMS user by e-mail address.

## Decision

Option 1. Guest checkout is disabled. Tokens never reach the browser. The key pair is separate from the Passport
keys. If the LMS becomes an OIDC provider later (6.3), the authenticator can accept those ID tokens instead.

## Consequences

- Good: no new dependency (`lcobucci/jwt` is already installed); one login; customers linked by a stable id, not by
  e-mail (no account takeover through e-mail reuse).
- Good: the channel claim adds a third tenant-binding check.
- Bad: a custom authenticator to maintain across Sylius upgrades; covered by bridge tests.
- Bad: buyers cannot use the Sylius shop without the LMS frontend (intended).
