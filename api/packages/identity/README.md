# identity: single sign-on per tenant

OpenID Connect login with per-tenant identity providers (Keycloak, Entra ID, Okta, Google, any OIDC provider), account
creation on first sign-in, strict account linking and a hash-chained audit trail (Phase 6.3, milestone ID-1 of
`docs/plans/phase-6-identity.md`; ADR 0120, ADR 0121). SAML, SCIM and HRIS import are later milestones.

- Guide for operators: `front/docs-site/src/content/docs/operators/single-sign-on.mdx`; protocol:
  `developers/authentication.mdx`.
- Public routes (`/api/identity/*`, throttled per IP): providers list, `sso/{slug}/start`, `oidc/{slug}/callback`, `sso/exchange`.
- Admin routes (`/api/admin/identity/*`, permissions `identity_provider_manage`, `identity_audit_read`, token scope
  `identity:read|write`): providers CRUD, audit list and chain verification. OpenAPI tags "Identity" and "Admin Identity".
- Tables (tenant database): `identity_providers` (client secret encrypted with the tenant `APP_KEY`), `identity_links`,
  `identity_audit` + `identity_audit_head` (append-only, PostgreSQL trigger).

```
SsoLoginService     start (state + nonce + PKCE), callback (code -> ID token -> account -> one-time code), exchange
OidcClient          discovery, authorization URL, token request, ID token validation on firebase/php-jwt, userinfo
AccountResolver     link, create a student, or refuse (verified e-mail, trust_email + domain, never admins/tutors)
ProviderService     create/update/delete with audit rows; secrets write-only
IdentityAuditLog    the only writer of identity_audit; verify() recomputes the chain
```

The login hand-off (signed state, one-time code, return URL allow-list) is `Ulams\Auth\Services\LoginHandoffService`.
Every request to a provider goes through `Ulams\Core\Http\SafeHttp`.

Config: `ulams_identity.*` (`IDENTITY_ENABLED`, `IDENTITY_MAX_PROVIDERS`, `IDENTITY_ALLOW_INSECURE_URLS` for development only).

Install: registered in `api/config/app.php`; run the migrations and `PermissionsSeeder`. Tests:
`php artisan test --testsuite identity` (fixture provider in `tests/Support/FakeIdp.php`). A real Keycloak runs in
`.github/workflows/identity-keycloak.yml` (nightly and on pull requests labelled `identity`).
