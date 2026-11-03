# identity: single sign-on per tenant

OpenID Connect and SAML 2.0 login with per-tenant identity providers (Keycloak, Entra ID, Okta, Google, any OIDC or SAML
provider), account creation on first sign-in, strict account linking and a hash-chained audit trail (Phase 6.3,
milestones ID-1 and ID-2 of `docs/plans/phase-6-identity.md`; ADR 0120, ADR 0121). SCIM and HRIS import are later milestones.

- Guides for operators: `front/docs-site/src/content/docs/operators/single-sign-on.mdx` (OpenID Connect) and
  `single-sign-on-saml.mdx`; protocol: `developers/authentication.mdx`.
- Public routes (`/api/identity/*`, throttled per IP): providers list, `sso/{slug}/start`, `oidc/{slug}/callback`, `sso/exchange`,
  SAML `saml/{slug}/metadata`, `saml/{slug}/acs` (POST), `saml/{slug}/slo` (GET and POST); `POST saml/{slug}/logout` needs the user's token.
- Admin routes (`/api/admin/identity/*`, permissions `identity_provider_manage`, `identity_audit_read`, token scope
  `identity:read|write`): providers CRUD, audit list and chain verification. OpenAPI tags "Identity" and "Admin Identity".
- Tables (tenant database): `identity_providers` (client secret and SAML SP private keys encrypted with the tenant `APP_KEY`), `identity_links`,
  `saml_replays` (accepted assertion ids), `identity_sessions` (token to IdP session, for single logout),
  `identity_audit` + `identity_audit_head` (append-only, PostgreSQL trigger).

```
SsoLoginService     start (state + nonce + PKCE), callback (code -> ID token -> account -> one-time code), exchange
OidcClient          discovery, authorization URL, token request, ID token validation on firebase/php-jwt, userinfo
AccountResolver     link, create a student, or refuse (verified e-mail, trust_email + domain, never admins/tutors)
ProviderService     create/update/delete with audit rows; secrets write-only
IdentityAuditLog    the only writer of identity_audit; verify() recomputes the chain
Saml\SamlService    onelogin/php-saml per provider and request: settings, SP metadata, AuthnRequest, response validation, replay
Saml\SamlLogout     single logout both ways (signed LogoutRequest/Response, sessions, token revocation)
Saml\IdpMetadata    IdP metadata over SafeHttp, certificates; ProviderService applies the refresh policy
Saml\SamlXml        size, DOCTYPE, algorithm allow-lists and comment checks that run before the library
```

Commands: `identity:refresh-metadata` (daily 03:30, per tenant), `identity:prune` (daily).

The login hand-off (signed state, one-time code, return URL allow-list) is `Ulams\Auth\Services\LoginHandoffService`.
Every request to a provider goes through `Ulams\Core\Http\SafeHttp`.

Config: `ulams_identity.*` (`IDENTITY_ENABLED`, `IDENTITY_MAX_PROVIDERS`, `IDENTITY_SAML_MAX_AGE`, `IDENTITY_ALLOW_INSECURE_URLS` for development only).

Install: registered in `api/config/app.php`; run the migrations and `PermissionsSeeder`. Tests:
`php artisan test --testsuite identity` (fixture providers in `tests/Support/FakeIdp.php` and `FakeSamlIdp.php`, which signs, encrypts and
attacks SAML documents with xmlseclibs). A real Keycloak runs OpenID Connect and SAML in
`.github/workflows/identity-keycloak.yml` (nightly and on pull requests labelled `identity`).

Dependency: `onelogin/php-saml` 4.x (MIT) and its `robrichards/xmlseclibs` (BSD-3-Clause); see `LICENSING.md` for why and what they cost.
