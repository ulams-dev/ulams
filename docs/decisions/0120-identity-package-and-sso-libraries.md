# 0120. An identity package with onelogin/php-saml, an in-house OIDC client and an in-house SCIM server

- Status: Proposed
- Date: 2026-10-21
- Plan: `docs/plans/phase-6-identity.md`; spec `docs/ROADMAP-PROMPT.md` 6.3

## Context and problem statement

Phase 6.3 needs SAML 2.0 and OIDC login per tenant, SCIM 2.0 provisioning and an HRIS import. Tenants have
separate databases (ADR 0007). The only external login today is Google/Facebook through stateless Socialite.
All LMS writes must go through domain services, and every dependency must be justified for self-hosting.

## Considered options

1. A new package `api/packages/identity` with `onelogin/php-saml` for SAML, an OIDC relying party built on
   `firebase/php-jwt` (already present) and `SafeHttp`, and an in-house SCIM server over the existing services.
2. Extend `auth` with Socialite drivers (`socialiteproviders/saml2`, generic OIDC) and add
   `arietimmerman/laravel-scim-server`.
3. Run a separate identity broker (Keycloak or SimpleSAMLphp) in front of the API and keep one OIDC client.

## Decision

Option 1.

- **Package.** SSO, SCIM and HRIS share the provider record, external ids, attribute mapping and an identity
  audit; `auth` stays the owner of users, groups and tokens and gains only the shared login handoff (ADR 0121).
- **SAML.** `onelogin/php-saml` 4.x (MIT, with `robrichards/xmlseclibs`, BSD-3): mature, pure PHP, settings
  built per request from the tenant's provider row. Strict mode, signed assertions required, SHA-1 rejected by
  default, server-side request ids and replay table. `simplesamlphp/saml2` 7 is still an RC on PHP 8.5 and LGPL;
  `24slides/laravel-saml2` brings its own tenant table.
- **OIDC.** A small relying party: discovery, authorization code with PKCE, ID token validation with an
  algorithm allow-list, all outbound calls through `SafeHttp`. No new dependency; `facile-it/php-openid-client`
  is the fallback if `private_key_jwt` or JWE become necessary.
- **SCIM.** In-house controllers, filter parser and PATCH applier writing through `UserService`,
  `UserGroupService`, model-fields and `ManagerService`. The available Laravel SCIM server writes Eloquent
  attributes directly and brings its own auth.
- **Test IdP.** Keycloak (Apache-2.0) in a nightly compose profile; fixture-signed responses in the PHPUnit suite.

## Consequences

- Good: one new production dependency; no extra deployable; per-tenant isolation unchanged.
- Good: validation rules are ours and covered by explicit negative tests.
- Bad: we maintain an OIDC client and a SCIM parser (a few hundred lines each) and must track IdP quirks.
- Bad: SAML security depends on `onelogin/php-saml` and `xmlseclibs` advisories being applied promptly.
- Option 3 stays possible later for tenants that want a broker; it would connect as one more OIDC provider.
