# identity: single sign-on per tenant

OpenID Connect and SAML 2.0 login with per-tenant identity providers (Keycloak, Entra ID, Okta, Google, any OIDC or SAML
provider), account creation on first sign-in, strict account linking and a hash-chained audit trail (Phase 6.3,
milestones ID-1, ID-2 and ID-4 of `docs/plans/phase-6-identity.md`; ADR 0120, ADR 0121, ADR 0198) and a SCIM 2.0 server for users. HRIS import is a later milestone.

- Guides for operators: `front/docs-site/src/content/docs/operators/single-sign-on.mdx` (OpenID Connect) and
  `single-sign-on-saml.mdx`; protocol: `developers/authentication.mdx`.
- Public routes (`/api/identity/*`, throttled per IP): providers list, `sso/{slug}/start`, `oidc/{slug}/callback`, `sso/exchange`,
  SAML `saml/{slug}/metadata`, `saml/{slug}/acs` (POST), `saml/{slug}/slo` (GET and POST); `POST saml/{slug}/logout` needs the user's token.
- SCIM 2.0 (`/api/scim/v2/*`, `ScimAuthenticate`: only a `scim` token of one provider; 600 requests per minute per token): `ServiceProviderConfig`, `ResourceTypes`, `Schemas`, `Users` (CRUD, `.search`, filters, PATCH, ETag). Guide: `operators/scim.mdx`, reference: `developers/scim-api.mdx`.
- Admin routes (`/api/admin/identity/*`, permissions `identity_provider_manage`, `identity_audit_read`, `identity_scim_manage` for `providers/{id}/scim-tokens`, token scope
  `identity:read|write`): providers CRUD, audit list and chain verification. OpenAPI tags "Identity" and "Admin Identity".
- Tables (tenant database): `identity_providers` (client secret and SAML SP private keys encrypted with the tenant `APP_KEY`), `identity_links`,
  `saml_replays` (accepted assertion ids), `identity_sessions` (token to IdP session, for single logout),
  `identity_audit` + `identity_audit_head` (append-only, PostgreSQL trigger),
  `identity_external_ids` (SCIM ids of users per provider, `userName`, tombstone on deprovisioning).

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
Scim\FilterParser   RFC 7644 filter grammar to a tree; Scim\FilterCompiler turns it into bound-parameter SQL on a fixed column map
Scim\PatchApplier   add/replace/remove on the SCIM representation (Entra and Okta quirks); nothing is written until all succeed
Scim\UserProvisioner create, adopt, update, deactivate, delete through UserService, model fields and ManagerService
Scim\ScimDirectory  what a provider's client can see: only the users it provisioned
Scim\ScimTokenService service user and scoped tokens of kind `scim` (two active, shown once)
```

Commands: `identity:refresh-metadata` (daily 03:30, per tenant), `identity:prune` (daily).

The login hand-off (signed state, one-time code, return URL allow-list) is `Ulams\Auth\Services\LoginHandoffService`.
Every request to a provider goes through `Ulams\Core\Http\SafeHttp`.

Config: `ulams_identity.*` (`IDENTITY_ENABLED`, `IDENTITY_MAX_PROVIDERS`, `IDENTITY_SAML_MAX_AGE`, `IDENTITY_SCIM_RATE`, `IDENTITY_SCIM_DELETE`, `IDENTITY_ALLOW_INSECURE_URLS` for development only).

Install: registered in `api/config/app.php`; run the migrations and `PermissionsSeeder`. Tests:
`php artisan test --testsuite identity` (fixture providers in `tests/Support/FakeIdp.php` and `FakeSamlIdp.php`, which signs, encrypts and
attacks SAML documents with xmlseclibs). A real Keycloak runs OpenID Connect and SAML in
`.github/workflows/identity-keycloak.yml` (nightly and on pull requests labelled `identity`).

Dependency: `onelogin/php-saml` 4.x (MIT) and its `robrichards/xmlseclibs` (BSD-3-Clause); see `LICENSING.md` for why and what they cost.
