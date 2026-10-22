# Phase 6.3: identity and HR (SAML, OIDC, SCIM, HRIS import)

Status: proposed 2026-10-21. The owner pre-approved the batch of plans, so the defaults in section 13 apply
unless the owner overrides one. ADRs 0120 and 0121 are Proposed.

## 1. Goal

An organisation connects its identity provider (Entra ID, Okta, Google Workspace, Keycloak, JumpCloud, ADFS)
to its own academy. Staff sign in with SAML 2.0 or OpenID Connect, get an account on first login with the
right groups, role and HR attributes, and lose access the moment HR or the IdP deprovisions them. The IdP can
push users and groups through SCIM 2.0. Without an IdP, HR can upload a CSV (or call an API) with a dry run,
a reviewable diff and a rollback. Those attributes (department, location, job title, manager) feed the
existing compliance rule engine (6.2) and manager escalation (6.1).

## 2. Scope and TODO items covered

`docs/ROADMAP-TODO.md`, section 6.3 Identity and HR:

> - [ ] SAML 2.0 and OIDC per tenant · SCIM 2.0 · HRIS import (CSV/API first)

Spec `docs/ROADMAP-PROMPT.md` 6.3: "SSO: SAML 2.0 and OIDC per tenant. SCIM 2.0 provisioning (create, update,
deactivate users and groups). HRIS sync design via connectors (start with a generic CSV/API import); attributes
feed the 6.2 rule engine."

When implementation starts, the single TODO line gets these `(new)` sub-items so progress can be ticked
honestly (wording of the original item stays unchanged):

- (new) Social login hardening: return-URL allow-list, CSRF state, no token in the URL, no linking on unverified email (ID-0)
- (new) Per-tenant identity providers and OIDC login (ID-1)
- (new) SAML 2.0 SP login with metadata (ID-2)
- (new) JIT provisioning with attribute, group and role mapping into compliance attributes (ID-3)
- (new) SCIM 2.0 Users and Groups with deprovisioning (ID-4, ID-5)
- (new) HRIS CSV and API import with dry run, diff and rollback (ID-6, ID-7)
- (new) Admin screens for identity providers, SCIM tokens and HRIS imports (ID-8)

Not in scope: HRIS pull connectors for named vendors (Personio, BambooHR, Workday; only the interface is
defined), SAML IdP role (ulams as IdP), WS-Federation, LDAP, 2FA for local accounts, Sylius customer linking
(6.4 "single login" reuses the user, nothing here blocks it).

## 3. What exists (extend, do not duplicate)

Verified in the code on `main` (2026-10-21). No SAML, OIDC-login or SCIM code exists; all "OIDC" in the repo
is LTI.

| Area | Where | Fact that matters here |
|---|---|---|
| Tenancy | `api/packages/tenancy`, ADR 0007 | Tenant chosen by host (`gecche/laravel-multidomain`), one database, `APP_KEY`, Passport keys and Redis prefix per tenant. Every new table below lives in the tenant database, so isolation is by construction. API host pattern `TENANCY_API_HOST={slug}.localhost`, front `{slug}.app.localhost`. |
| Encrypted per-tenant secrets | `lti/src/Models/LtiKey.php`, `webhooks/src/Models/WebhookEndpoint.php`, `living-course/src/Models/Connection.php` | `encrypted` / `encrypted:array` casts with the tenant `APP_KEY` (ADR 0066: never regenerated). The `settings` package stores Socialite client secrets in plain text: not to be copied. |
| Login and tokens | `api/packages/auth`, `AuthService::createTokenForUser()` | Passport personal access tokens (JWT), 5 min (1 month with remember-me), no refresh token, `POST auth/logout` revokes the current token. |
| Social login | `auth/Services/SocialAccountService.php`, table `social_accounts` | Google and Facebook only. **Security gaps:** Socialite runs `stateless()` and `state` is unsigned base64 JSON; `return_url` is validated only as a URL (no allow-list, open redirect); the access token is put in the redirect query string; an existing account is linked by email alone and its email marked verified without checking the provider's `email_verified`; no unique index on `(provider, provider_id)`. Fixed in ID-0. |
| Users | `Ulams\Auth\Models\User` (core + `SoftDeletes`, `HasGroups`, `ModelFields`) | `email` unique, `is_active`, `email_verified_at`, soft delete with `MaskUserData` on `AccountDeleted`. No external id column. `AccountBlocked` fires on `is_active=false` but nothing listens: **tokens survive deactivation** (fixed in ID-4). |
| Roles, permissions | `Ulams\Core\Enums\UserRole` (`student`, `tutor`, `admin`), `AuthPermissionSeeder` pattern, `UserService::syncRoles()` | Role changes go through `UserService` (domain service). |
| Groups | `groups` (`parent_id`, `registerable`, soft delete), `group_user`, `UserGroupService` (`create`, `addMember`, `removeMember`, ...) | No external id. Fires `UserAddedToGroup` / `UserRemovedFromGroup`. |
| Custom attributes | `api/packages/model-fields` | Typed per-model fields with visibility; used by `User`. Values are written after `parent::save()`. |
| Compliance attributes | `compliance/Services/RuleEngine`, `AttributeResolver`, `compliance_rules`, `compliance_manager_links` (`ManagerService`) | Rules read user columns, model-fields values and the `group` pseudo attribute; triggered by `eloquent.saved` on `User`. Because model-fields values are stored after `saved`, a change to only `department` is picked up at the next scheduled run, not immediately (handled in ID-3). Manager links are "filled by admin API now, by HRIS import later" (`docs/plans/phase-6-compliance.md`). |
| CSV import | `api/packages/csv-users` | Synchronous Excel import keyed by email; `syncRoles` replaces roles; groups only added; no dry run, no per-row report, no deactivation, no rollback. Kept as is for backwards compatibility; HRIS import is a new pipeline. |
| Scoped tokens | ADR 0074, `PersonalAccessTokenService`, `api_token_meta.kind`, `resources/token-scopes.php` (fail closed, coverage test) | Every token belongs to a user; SCIM tokens reuse this (ADR 0121). |
| Audit | `living-course/src/Services/AuditLog` (ADR 0034, hash-chained, closed `ACTIONS` list, course columns); `agent_audit_log` (scoped tokens only) | Neither fits identity events as is; see 4.10. |
| Webhooks | `api/packages/webhooks`, ADR 0103 | `learner.registered` planned; no user lifecycle events. |
| Cross-site POST | `core/src/Http/Middleware/EnforceTrustedOrigin`, `ulams.core.security.origin_exempt` in `core/src/config.php` | The SAML ACS must be exempted, like `api/lti/tool/login`. Session cookie is `SameSite=Lax`, so the ACS cannot rely on a cookie: state is server-side (LTI precedent in `lti/src/Http/Controllers/Tool/ToolController.php`). |
| SSRF-safe client | `Ulams\Core\Http\SafeHttp` | Used for metadata, discovery, JWKS and token endpoint calls. |
| Frontends | `front/web/src/pages/login.astro` (sets `__Host-ulams_session`), `admin/src/pages/User/login`, admin `Users`, `UserGroups`, `Roles`, `Tokens` | No SSO button, no callback page. Legacy `front/src/pages/login/index.tsx` reads `?token=` from social login. |
| CLI | `front/cli` (ADR 0072): commands generated from OpenAPI + `spec/overrides.yaml`; hand-written in `src/commands/*.ts` | New endpoints get CLI and MCP coverage through the generator. |
| Docs | `front/docs-site` (English only): `admin/users.mdx`, `user-groups.mdx`, `compliance.mdx`, `developers/authentication.mdx` | New pages listed in 4.13. |
| CI | `.github/workflows/ci.yml` job `php` (shards in `PHPUNIT_SHARDS`, postgres + valkey services); `nightly-conformance.yml` with `.github/conformance/compose.yml` profiles | A new testsuite must be added to `api/phpunit.xml` and to one shard. Keycloak goes into a nightly profile. |
| Plans/ADRs | `docs/plans/phase-6-compliance.md` (6.3 "another lane"), ADR 0020 (comparison: SSO "Partial", SCIM "Coming") | No other 6.3 plan; nothing here duplicates it. |

## 4. Design

### 4.1 New package `api/packages/identity` (`Ulams\Identity`)

A separate composer package (project convention, ADR 0120): SSO, SCIM and HRIS share the provider record,
the external-id table, the attribute mapper and the audit, and none of them belongs in `auth` (which stays the
owner of users, groups and tokens). It depends on `auth`, `model-fields`, `core` and, through an event only,
`compliance`.

```
api/packages/identity/
  composer.json                         ulams/identity, requires onelogin/php-saml
  src/UlamsIdentityServiceProvider.php  routes, config, limiters, scheduler, commands, permission seeder
  src/config.php                        ulams_identity.* (see 4.12)
  src/routes.php
  src/Models/IdentityProvider.php       encrypted casts for secrets and SP private keys
  src/Models/IdentityLink.php, ExternalId.php, SamlReplay.php, IdentitySession.php, HrisImportRun.php, HrisImportChange.php, IdentityAudit.php
  src/Services/ProviderService.php      CRUD, metadata import/refresh, SP key generation and rotation
  src/Services/Oidc/OidcClient.php      discovery, authorize URL, code exchange, ID token validation (4.3)
  src/Services/Saml/SamlServiceProvider.php   onelogin settings per provider, AuthnRequest, ACS, SLO, metadata (4.2)
  src/Services/SsoLoginService.php      start/complete per protocol on top of auth's LoginHandoffService (ADR 0121)
  src/Services/AccountResolver.php      link or provision, takeover rules (4.5)
  src/Services/AttributeMapper.php      claims/attributes to user fields, model-fields, groups, role, manager (4.4)
  src/Services/Scim/*                   FilterParser, PatchApplier, UserResource, GroupResource, ScimErrors (4.7)
  src/Services/Hris/*                   CsvReader, RowNormaliser, DiffPlanner, Applier, Rollback, HrisConnector interface (4.8)
  src/Services/IdentityAuditLog.php     hash-chained append-only log (4.10)
  src/Events/UserAttributesSynced.php   fired after every mapped write (compliance listens)
  src/Http/Controllers/{Sso,Saml,Oidc,Scim,Admin}/*, Swagger/*
  src/Console/{RefreshMetadataCommand,PruneIdentityCommand,HrisImportCommand,ScimTokenCommand}.php
  database/migrations/*, database/seeders/IdentityPermissionSeeder.php
  tests/{Unit,Feature,Fixtures}/*
```

Permissions (seeded like `AuthPermissionSeeder`, granted to `admin`): `identity_provider_manage`,
`identity_scim_manage`, `identity_hris_import`, `identity_audit_read`. Token scope areas: `identity:read|write`
for the admin API, `scim:write` for SCIM routes (added to `auth/resources/token-scopes.php`, re-exported with
`ulams:tokens:export-scopes`).

### 4.2 Per-tenant provider configuration and secrets

Table `identity_providers` (tenant database):

| Column | Notes |
|---|---|
| `id` uuid, `slug` (unique, used in URLs), `name`, `protocol` (`saml`, `oidc`), `enabled` | |
| `email_domains` json | for "use single sign-on" discovery and enforcement |
| `enforce_for_domains` bool | users whose email is in `email_domains` cannot log in with a password (break-glass in 4.5) |
| `trust_email` bool | the IdP is authoritative for these domains: allows linking an existing account by email (4.5) |
| `jit_enabled` bool, `default_role` (`student`), `allow_role_mapping` bool, `allow_admin_mapping` bool (false) | |
| `attribute_map` json, `group_map` json, `group_sync_mode` (`additive`, `authoritative`) | 4.4 |
| `oidc` json | `issuer`, `client_id`, `scopes`, `token_auth` (`client_secret_basic`, `client_secret_post`), `end_session` toggle |
| `oidc_client_secret` encrypted | never returned by the API after save (hint of 4 chars, like webhooks) |
| `saml` json | IdP `entity_id`, `sso_url`, `slo_url`, `metadata_url`, `name_id_format`, `want_encrypted_assertions`, `allow_idp_initiated` (false), `allowed_sig_algs` |
| `saml_idp_certs` json | one or more IdP signing certs (rotation), with SHA-256 fingerprints |
| `saml_sp_key` encrypted, `saml_sp_cert`, `saml_sp_next_key` encrypted, `saml_sp_next_cert` | SP signing/encryption key pair generated per provider (RSA 3072, 5 years); the next pair is published in metadata before rollover |
| `metadata_refreshed_at`, `last_login_at`, `last_error` | |

Secrets are encrypted with the tenant `APP_KEY` (same as LTI and webhooks); they never appear in logs, audit
rows, API responses, exports or `.env` files. A provider is limited to 10 per tenant (config).

Endpoints, all on the tenant API host:

- Public: `GET /api/identity/providers` (enabled providers: slug, name, protocol; for login buttons),
  `POST /api/identity/discover` `{email}` (returns `{provider: slug|null}` with the same shape and timing for
  any input; throttled), `GET /api/identity/sso/{slug}/start?return_to=&challenge=`,
  `POST /api/identity/sso/exchange`.
- SAML: `GET /api/identity/saml/{slug}/metadata`, `POST /api/identity/saml/{slug}/acs`,
  `GET|POST /api/identity/saml/{slug}/slo`.
- OIDC: `GET /api/identity/oidc/{slug}/callback`.
- Admin (`auth:api`, permission, scope `identity:*`): CRUD `/api/admin/identity/providers`,
  `POST .../{id}/metadata/refresh`, `POST .../{id}/sp-keys/rotate`, `POST .../{id}/test` (starts a login whose
  result page shows the received claims or attributes and the mapping outcome without creating a user).

The SAML SP entity ID defaults to the metadata URL `https://{api_host}/api/identity/saml/{slug}/metadata`
(stable while the API host is stable; question 4).

### 4.3 SAML 2.0 (SP-initiated) and OIDC (authorization code + PKCE)

Both protocols end in the same `SsoLoginService::complete(provider, subject, attributes, sessionIndex)`.

**Login state.** State and handoff codes are kept by `Ulams\Auth\Services\LoginHandoffService`, added to
`auth` in ID-0 and shared with social login (tables `login_handoff_states`, `login_handoff_codes`). `start` creates a state row: random `state` (32 bytes), provider, `return_to`
(must match the tenant allow-list: the tenant front host, admin host and configured extra origins), the
front-end `challenge` (S256 hash of a verifier the front keeps in an httpOnly cookie), and in its `data` json per protocol the
OIDC `nonce` and `code_verifier` (encrypted) or the SAML AuthnRequest `ID`. Ten-minute expiry, single use (row is
consumed with `UPDATE ... WHERE consumed_at IS NULL`). Server-side state is required because the SAML POST is
cross-site and the session cookie is `SameSite=Lax`.

**SAML** with `onelogin/php-saml` 4.x (comparison in section 5), configured per request from the provider row
(no global settings file):

- AuthnRequest over HTTP-Redirect, signed with the SP key (RSA-SHA256), `RelayState` = state.
- ACS over HTTP-POST; `api/identity/saml/*/acs` and `api/identity/saml/*/slo` added to `origin_exempt`.
- Validation (library settings plus our checks, each one a test): `wantAssertionsSigned=true` and
  `wantMessagesSigned` when the IdP signs responses; unsigned assertions are rejected; `InResponseTo` must equal
  the stored request ID (IdP-initiated responses rejected unless `allow_idp_initiated`, then still replay
  checked); `Destination` and `Recipient` = ACS URL; `Audience` = SP entity ID; `Issuer` = IdP entity ID;
  `NotBefore`/`NotOnOrAfter`/`SubjectConfirmationData` with the library's clock drift (`ALLOWED_CLOCK_DRIFT`,
  180 s) and our own maximum assertion age of 5 minutes from `IssueInstant`; strict mode on; `rejectUnsolicitedResponsesWithInResponseTo` on.
- Signature algorithms: allow-list RSA-SHA256/384/512 and ECDSA-SHA256/384/512, digests SHA-256 or stronger;
  SHA-1 rejected (`rejectDeprecatedAlgorithm`); per-provider opt-in for SHA-1 only with an audit row and an
  admin warning (question 6).
- Replay: assertion `ID` stored in `saml_replays` until `NotOnOrAfter`; a second use is rejected.
- XML hardening: request body capped at 256 KB; documents with a DOCTYPE are rejected before parsing; the
  library's XSW protections (one assertion, signature reference to the signed element) are covered by fixtures of
  known wrapping attacks.
- Encrypted assertions supported (SP key), required when `want_encrypted_assertions`.
- Subject: `NameID` (persistent preferred; transient refused unless an `attribute_map.subject` attribute is set)
  scoped by IdP entity ID.
- Metadata: SP metadata generated with both current and next SP certs; IdP metadata imported from upload or URL
  (`SafeHttp`, https only), refreshed daily by `identity:refresh-metadata`. New IdP signing certs are added next
  to the old ones; removal of the last trusted cert or a change of entity ID is never automatic (audit row and
  admin notification; question 5).

**OIDC** with our own small client on `firebase/php-jwt` (already a dependency) and `SafeHttp` (ADR 0120):

- Discovery from `{issuer}/.well-known/openid-configuration`, cached 24 h; `issuer` must match exactly;
  endpoints must be https.
- Authorization code flow, `response_type=code`, PKCE S256, `state`, `nonce`, `prompt` optional, scopes
  `openid email profile` plus configured ones (e.g. `groups`).
- Token request with `client_secret_basic` (default) or `client_secret_post`; `private_key_jwt` later.
- ID token: JWKS fetched via `SafeHttp`, cached 1 h, refetched once on an unknown `kid`; algorithms allow-list
  RS256/RS384/RS512/PS256/ES256/ES384 (no `none`, no HS*); `iss`, `aud` contains `client_id`, `azp` if several
  audiences, `exp`, `iat`, `nbf` with 60 s leeway, `nonce` equals stored nonce, `auth_time` recorded.
- Claims from the ID token, merged with `userinfo` when configured (userinfo `sub` must equal the ID token
  `sub`). Subject = `(provider, sub)`.
- `email_verified` must be true to use the email for linking (4.5).

**Handoff to the frontend (ADR 0121).** After `complete`, the API never puts a token in a URL. It creates a
`login_handoff_codes` row (random code stored hashed, 60 s, single use, bound to the stored `challenge`) and
redirects to `return_to?code=...`. The front-end server (`front/web` page `/auth/sso/callback`, admin route
`/user/sso-callback`) posts `{code, verifier}` to `POST /api/identity/sso/exchange` and receives a Passport
token issued by `AuthService::createTokenForUser()` (`remember_me` per provider config), then sets
`__Host-ulams_session` as `login.astro` does. A code without the matching verifier (another browser, login
CSRF) fails.

### 4.4 JIT provisioning and attribute, group and role mapping

`attribute_map` maps a claim or SAML attribute name to a target:

```json
{
  "subject": null,
  "email": "email",
  "first_name": "given_name",
  "last_name": "family_name",
  "fields": {"department": "department", "location": "office", "job_title": "title", "employee_id": "employeeNumber"},
  "manager": {"attribute": "manager_email", "match": "email"},
  "groups": "groups"
}
```

- Presets for Entra ID, Okta, Google Workspace, Keycloak and ADFS (claim URIs) prefill the map in the admin UI.
- `fields.*` target model-fields of `User`; a missing field is created by the admin action "create field"
  (type `varchar`, visibility `ADMIN`), never implicitly.
- `group_map`: list of `{value, group_id?, role?}`; IdP group values (names or object ids) map to ulams groups
  and optionally to a role. Unmapped values are ignored (no auto-created groups from SSO; SCIM creates groups).
  `group_sync_mode=additive` (default) only adds; `authoritative` also removes the user from mapped groups the
  IdP no longer sends (never from unmapped groups).
- Roles: only when `allow_role_mapping`; `admin` only when `allow_admin_mapping` (off by default, audit row on
  every grant). Without mapping the user keeps existing roles and new users get `default_role`.
- All writes go through `UserService` (create/update/roles), `UserGroupService` (membership), the model-fields
  API and `ManagerService::setManager()` (unresolved manager: stored as pending in `identity_external_ids`
  metadata and resolved when the manager appears).
- After the write, `UserAttributesSynced(user, changedKeys, source)` is fired; the compliance package listens
  and calls `RuleEngine::evaluateUser()`, so rule enrolment happens immediately even when only a custom field
  changed (closes the ordering gap in section 3). One small listener in `compliance`, no other change there.
- Update on each login: mapped attributes are refreshed when the provider is the attribute authority for them
  (4.9); otherwise only on creation.

### 4.5 Account linking and takeover protection

Table `identity_links` (`provider_id`, `subject`, `user_id`, `email_at_link`, `linked_via` (`jit`, `email_trust`,
`password_confirm`, `admin`, `scim`), timestamps; unique `(provider_id, subject)`; one link per user and provider).

Resolution order in `AccountResolver`:

1. Link exists: use that user. The email from the IdP never moves the login to another account.
2. No link, no local user with that email: JIT create (if `jit_enabled`, else refuse with "ask your
   administrator") with a verified email.
3. No link, a local user with that email exists: link automatically only if `trust_email` is on, the email's
   domain is in `email_domains`, the email is asserted verified (OIDC `email_verified=true`; SAML providers are
   trusted only through `trust_email`) and the user is not an admin and not a superadmin. Otherwise the user is
   asked to sign in once with their password to confirm the link (`linked_via=password_confirm`), or an admin
   links them.
4. A user who is inactive, soft-deleted or deprovisioned by SCIM is refused; the refusal is audited.

Other rules: `superadmins` (`AUTH_SUPERADMIN_EMAIL`) keep password login as break-glass even under
`enforce_for_domains`; enforcing SSO for a domain requires at least one successful test login through that
provider (prevents lockout); unlinking is an admin action with an audit row; email changes coming from the IdP
are applied only if the new email is free, otherwise refused and audited.

### 4.6 Logout

- Local logout (`POST auth/logout`) revokes the Passport token as today.
- `identity_sessions` maps Passport token id to provider, subject and SAML `SessionIndex` (or OIDC `sid`).
- SP-initiated SLO: after local logout the front may redirect to `GET /api/identity/sso/{slug}/logout`, which
  sends a signed SAML LogoutRequest or the OIDC `end_session_endpoint` redirect with `id_token_hint` and an
  allow-listed `post_logout_redirect_uri`, when the provider enables it.
- IdP-initiated SAML SLO: a valid signed LogoutRequest revokes every token of the matching session index.
- OIDC back-channel logout: not in this pass (question 9).

### 4.7 SCIM 2.0 (RFC 7643, 7644)

Base `https://{api_host}/api/scim/v2`, content type `application/scim+json`.

- Discovery: `ServiceProviderConfig` (patch true, bulk false, filter true max 200, changePassword false, sort
  false, etag false, authenticationSchemes oauthbearertoken), `ResourceTypes`, `Schemas`.
- Users: `GET /Users` (filter, `startIndex`, `count` ≤ 200), `GET/PUT/PATCH/DELETE /Users/{id}`, `POST /Users`.
  Schemas: core User plus the enterprise extension. Mapping: `userName` (unique per tenant, case-insensitive,
  stored in `identity_external_ids.user_name`), `externalId`, `name.givenName/familyName`, primary
  `emails[].value` → `email`, `active` → `is_active`, enterprise `employeeNumber`, `department`, `organization`,
  `division`, `costCenter`, `manager.value` → model-fields and `compliance_manager_links`, `title` → `job_title`.
  `id` is the ulams user id as a string. Unsupported attributes are ignored on input and not returned.
- Groups: `GET/POST /Groups`, `GET/PUT/PATCH/DELETE /Groups/{id}`; `displayName` → `groups.name`, `members`
  managed through `UserGroupService`; groups created by SCIM are flagged as managed (read-only membership in the
  admin UI).
- Filtering: our own recursive-descent parser for the RFC 7644 grammar: `eq ne co sw ew pr gt ge lt le`,
  `and`, `or`, `not`, parentheses and value-path filters (`emails[type eq "work"]`), on `userName`,
  `externalId`, `emails.value`, `active`, `displayName`, `members.value`, `meta.lastModified`. Unsupported
  attributes or operators return `400 invalidFilter`. Translated to Eloquent with bound parameters only.
- PATCH: `add`, `replace`, `remove` with and without `path`, value-path filters (`members[value eq "x"]`),
  case-insensitive `op`, and the known client quirks as tested fixtures: Entra ID sends `"False"` strings and
  `replace` without `path`; Okta sends `PUT` for users and `PATCH` for group members. All operations in one
  request apply in a transaction or none do.
- Errors: SCIM error schema with `scimType` (`uniqueness` 409, `invalidFilter`, `invalidValue`, `mutability`,
  `noTarget`); 404 for ids of other resources.
- Deprovisioning: `active=false` → `UserService` sets `is_active=false`; a new `auth` listener on
  `AccountBlocked` revokes all Passport tokens of the user (fixes the gap in section 3 for every deactivation,
  not only SCIM). `DELETE /Users/{id}` deactivates and unlinks by default (`scim.delete_behaviour=deactivate`),
  keeping learning records and certificates; `anonymise` (soft delete + existing `MaskUserData`) is opt-in.
  Reactivation with `active=true` restores access.
- Authentication: bearer tokens per ADR 0121: a per-tenant service user (`scim+{provider}@identity.invalid`,
  no password, cannot log in) holds a Passport token with `api_token_meta.kind=scim`, scope `scim:write`,
  bound to one identity provider (SCIM writes record that provider as the source). Created with
  `POST /api/admin/identity/providers/{id}/scim-tokens` (shown once, `ulams_pat_` prefix, max 365 days, 2 active
  per provider for rotation), listed and revoked through the existing token endpoints. SCIM routes accept only
  `kind=scim` tokens, and `kind=scim` tokens are accepted only on SCIM routes.
- Throttle `ulams-scim` 600 requests per minute per token (Entra ID bursts on initial sync).

Table `identity_external_ids` (`provider_id`, `resource_type` `user|group`, `local_id`, `external_id`,
`user_name`, `source` `scim|hris|jit`, `meta` json, timestamps; unique `(provider_id, resource_type, external_id)`
and `(resource_type, user_name)`). Keeping external ids here leaves the `auth` tables unchanged.

### 4.8 HRIS import (CSV first, API next)

A new pipeline in `identity`, not an extension of `csv-users` (which replaces roles synchronously and keys on
email). Source of an import is a pseudo provider row of protocol `hris` (so external ids, authority and audit
work the same way), one per HR system.

- **Profile**: per HR source a saved column map (`employee_id` key column, `email`, `first_name`, `last_name`,
  `status` or `active`, `department`, `location`, `job_title`, `manager_employee_id`, `groups` pipe-separated,
  `start_date`, `end_date`, any other header → a model-field), date format, delimiter, and the
  `missing_rows` policy: `ignore` (default) or `deactivate` (full sync).
- **Upload**: `POST /api/admin/identity/hris/imports` with a file (CSV, UTF-8 with or without BOM, max 20 MB /
  50 000 rows) stored on the private disk; or JSON `{rows: [...]}` for the API variant with `Idempotency-Key`.
  Both create an `hris_import_runs` row and queue `PlanHrisImport`.
- **Dry run and diff** (always first): rows are validated (per-row errors, not all-or-nothing), matched by
  `employee_id` through `identity_external_ids`, then by email for first-time matching (reported as "matched by
  email"), and planned into `hris_import_changes` rows: `create`, `update` (field-level `before`/`after`),
  `deactivate`, `reactivate`, `group_add`, `group_remove`, `manager_set`. The run shows counts and a paginated
  diff (`GET .../imports/{id}/changes?op=`). Nothing is written to users at this stage.
- **Safety caps**: apply is refused when deactivations exceed 10 % of active users or 50 accounts (configurable)
  unless `force=true` with a reason; admins are never deactivated by an import.
- **Apply**: `POST .../imports/{id}/apply` (only from `planned`, only if no other run was applied after the plan
  was computed, otherwise re-plan) queues `ApplyHrisImport`, chunked by 200 changes, each through the domain
  services of 4.4, each change marked applied with the actual `before` snapshot, then `UserAttributesSynced`.
  New users are created active with verified email and no password (they sign in by SSO or a reset link;
  invitation mail optional per profile).
- **Rollback**: `POST .../imports/{id}/rollback` within 30 days, newest applied run first. Each applied change
  is inverted from its `before` snapshot only if the current value still equals its `after` value; otherwise it
  is reported as a conflict and skipped. Created users are deactivated, not deleted. The rollback is itself a
  run, visible in the history.
- **API variant**: same endpoints with JSON rows, plus `GET .../imports/{id}` for polling; the CLI and MCP use
  them (`ulams hris import file.csv --profile workday --dry-run`, `ulams hris apply <run>`,
  `ulams hris rollback <run>`).
- **Connectors**: interface `Ulams\Identity\Hris\HrisConnector` (`fetchRows(cursor): iterable`) registered
  per tenant with a schedule; no vendor implementation in this pass.

### 4.9 Attribute authority

Several sources can write the same user. Per tenant, `ulams_identity` keeps an ordered authority list per
attribute group (`profile`, `hr_fields`, `groups`, `manager`, `active`). Default: SCIM, then HRIS, then
SSO/JIT, then manual admin edits. A source writes an attribute only if no source above it has written it for
that user; manual admin edits of an attribute owned by SCIM or HRIS are refused in the admin UI with a note
naming the source. The setting is shown and editable on the identity settings screen (question 2).

### 4.10 Audit log

Table `identity_audit` in the tenant database, append-only and hash-chained with the same technique as ADR
0034 (`hash = sha256(prev_hash ‖ canonical row)`, head row lock, PostgreSQL trigger rejecting UPDATE and DELETE).
It is a separate table rather than an extension of `living_course_audit`, which has course-specific columns and
a closed action list; extracting a shared chain helper into `core` is a later refactor (question 8).

Columns: actor (`user`, `scim`, `hris`, `system`, `idp`), `actor_id`, `provider_id`, `action`, `subject_type`,
`subject_id`, `data` json, `ip`, `user_agent`, `request_id`, `created_at`, `prev_hash`, `hash`.

Actions: `sso.login`, `sso.login_failed` (reason code, never the raw assertion or token; assertion ID and its
SHA-256 only), `sso.link`, `sso.unlink`, `sso.logout`, `provider.created|updated|deleted`,
`provider.cert_added|cert_removed|sp_key_rotated|sha1_enabled`, `scim.token_created|revoked`,
`scim.user_created|updated|deactivated|deleted`, `scim.group_created|updated|deleted|members_changed`,
`role.granted_by_mapping`, `hris.run_planned|applied|rolled_back|failed`. Admin endpoints
`GET /api/admin/identity/audit` (filters) and `/export` (CSV, JSON lines), `GET .../audit/verify`. The
compliance audit export (#276) can include these rows behind a filter.

### 4.11 Tenant isolation

All tables live in the tenant database and every route resolves the tenant by host first, so a provider slug,
state, login code, SCIM token or import id of tenant A does not exist in tenant B. Extra guards: SAML audience
and ACS include the tenant API host, so a response for A fails at B even if both trust the same IdP; OIDC
`redirect_uri` is per tenant host; SCIM tokens are Passport tokens signed with per-tenant keys. Tests in 4.14.

### 4.12 Configuration (`ulams_identity`, env)

`IDENTITY_ENABLED` (true), `IDENTITY_MAX_PROVIDERS` (10), `IDENTITY_STATE_TTL` (600), `IDENTITY_CODE_TTL` (60),
`IDENTITY_SAML_MAX_AGE` (300), `IDENTITY_SCIM_DELETE` (`deactivate`), `IDENTITY_SCIM_RATE` (600),
`IDENTITY_HRIS_MAX_ROWS` (50000), `IDENTITY_HRIS_DEACTIVATE_CAP_PERCENT` (10), `IDENTITY_HRIS_DEACTIVATE_CAP_ABS`
(50), `IDENTITY_ALLOW_INSECURE_URLS` (false; dev only, lets Keycloak run on http in compose). No model names
or vendor endpoints hardcoded.

### 4.13 Admin UI, frontends, CLI and docs

- **Admin** (`admin/src/pages/Identity/`, route `/identity/*` in `admin/config/routes.ts`):
  `Providers` (list, create wizard per protocol with IdP presets, SP metadata download and copy buttons,
  metadata URL or XML upload, attribute and group mapping table, "test login" with the claim viewer),
  `ScimTokens` (create, show once, revoke, last used), `HrisImports` (profiles, upload, dry-run diff with filters
  per operation, apply, rollback, history), `Audit` (list, export, verify). Users screen: badge "managed by
  SCIM/HRIS" and linked identities tab with unlink. Admin login gets "Sign in with SSO".
- **Learner web** (`front/web`): `login.astro` shows provider buttons and "Use single sign-on" (email →
  discovery), new `src/pages/auth/sso/callback.astro` (server-side exchange), strings in EN/PL/ZH with the
  existing i18n files; WCAG 2.2 AA (focus order, labelled buttons, error text announced). The legacy
  `front/src` callback is switched to the code exchange in ID-0.
- **CLI/MCP**: admin endpoints carry OpenAPI attributes, so `front/cli` generates `identity providers ...`,
  `identity scim-tokens ...`, `hris imports ...`; names curated in `spec/overrides.yaml`; one hand-written
  `src/commands/hris.ts` for upload + wait + diff summary. SCIM endpoints are excluded from the CLI
  (`spec/exclusions.yaml`): they are for IdPs.
- **Docs** (`front/docs-site`, English, as the site has no other locales): `admin/single-sign-on.mdx` (concepts,
  per-IdP recipes for Entra ID, Okta, Google Workspace, Keycloak, JumpCloud, ADFS, troubleshooting by audit
  reason code), `admin/scim.mdx` (Entra ID and Okta setup), `admin/hris-import.mdx` (CSV format, profile, dry
  run, rollback), `developers/scim-api.mdx`, a section in `developers/authentication.mdx` (code exchange),
  `operators/security.mdx` (keys, SHA-1 policy, break-glass). After ID-5 ships, the comparison cells `sso` and
  `scim` in `front/web/src/data/comparison.json` (+ `.pl`/`.zh`) and ADR 0020 notes are updated.

### 4.14 Tests

- **Unit**: SCIM filter parser (grammar table, precedence, fuzz with random strings never throwing anything but
  `invalidFilter`), PATCH applier (RFC examples, Entra and Okta fixtures), attribute mapper, authority rules,
  diff planner and rollback conflicts, OIDC claim validation (each failing claim), SAML settings builder.
- **Feature** (PHPUnit, `api/packages/identity/tests`, LLM not involved):
  - OIDC: `Http::fake` discovery, JWKS and token endpoints with locally generated RSA/EC keys; wrong `iss`,
    `aud`, `nonce`, expired, `alg=none`, HS256 with the public key as secret, unknown `kid` refetch, replayed state,
    expired state, code without verifier.
  - SAML: responses signed in the test with xmlseclibs and test keys: valid, unsigned, signed by another key,
    wrong audience, destination, InResponseTo, expired, replayed ID, SHA-1, comment injection in NameID,
    XSW fixtures (wrapped assertion, duplicate assertion), DOCTYPE/XXE, oversize body, encrypted assertion.
  - Linking: existing user by email with and without `trust_email`, unverified email, admin target, inactive
    user, email change collision, `enforce_for_domains` blocks password login and allows superadmin.
  - SCIM: create/get/list/filter/patch/put/delete for users and groups, uniqueness 409, deactivation revokes
    tokens, non-SCIM token refused on SCIM routes, SCIM token refused on admin routes, pagination.
  - HRIS: dry run writes nothing, apply, safety cap, rollback with and without conflicts, idempotent API import,
    compliance rule enrolment triggered by a department change.
  - Audit chain verify and tamper detection; secrets never present in any response or audit row.
  - Scope map coverage test (existing) passes with new routes.
- **Tenant isolation**: feature tests per admin and SCIM endpoint with a token of another tenant (technique of
  `adapt`), plus new cases in `api/packages/tenancy/tests/Integration/TenantIsolationTest.php`: SCIM token of A
  gets 401 on B, a state and code of A cannot complete on B, a SAML response for A's SP fails at B.
- **IdP mock in CI**: Keycloak (Apache-2.0, `quay.io/keycloak/keycloak:26`) with a realm import JSON
  (`.github/conformance/keycloak/realm-ulams.json`: one SAML client, one OIDC client, users and groups) as a new
  `--profile sso` in `.github/conformance/compose.yml`, run by `nightly-conformance.yml` and on PRs labelled
  `identity`; a Playwright script logs in through both protocols against a provisioned tenant and checks the
  session, groups and logout. The fast PR path uses the fixture-based tests above, so the main `php` job needs no
  new service container. The new testsuite is added to `api/phpunit.xml` and to the `platform` shard in
  `ci.yml`.
- **Security review**: a dedicated checklist pass before ID-2 and ID-4 merge (section 8), plus the
  `/security-review` run on each PR.
- **Accessibility**: login and callback pages in the existing axe checks of `front/web`.

## 5. Library choices

| Need | Option | Licence | Maintenance (Packagist, 2026-10) | Self-hosting / size | Verdict |
|---|---|---|---|---|---|
| SAML SP | **`onelogin/php-saml` 4.x** | MIT | 4.3.2 (2026-05), long history, CVEs fixed promptly; depends on `robrichards/xmlseclibs` (BSD-3, 4.0.1, 2026-10) | pure PHP, no service; ~0.5 MB | **Chosen**: smallest, most used PHP SAML SP toolkit, per-request settings fit per-tenant providers |
| SAML SP | `simplesamlphp/saml2` 7 | LGPL-2.1+ | 7.0.0 still RC, requires PHP 8.5 | library only | Rejected: RC, PHP 8.5, LGPL adds licensing notes |
| SAML SP | SimpleSAMLphp (full app) | LGPL-2.1+ | active | a second PHP app with its own config and sessions | Rejected: a separate deployable for one feature |
| SAML SP | `litesaml/lightsaml` 6 / `socialiteproviders/saml2` | MIT | active (2026-10) | library | Viable fallback; lower-level API means more of our own validation code; Socialite driver keeps the stateless pattern we are removing |
| SAML SP | `24slides/laravel-saml2` | MIT | 2.5.2 (2026-02) | wraps onelogin | Rejected: its own tenant table and routes clash with database-per-tenant and our handoff |
| OIDC RP | **own client on `firebase/php-jwt` 7 (present) + `SafeHttp`** | BSD-3 (present) | already maintained in the tree | no new dependency | **Chosen**: ~400 lines, SSRF guard built in, exact validation we test; LTI already validates JWTs the same way |
| OIDC RP | `facile-it/php-openid-client` | MIT | 1.0.0 (2026-06) | pulls a JOSE verifier stack | Fallback if `private_key_jwt` or JWE is needed later |
| OIDC RP | `jumbojett/openid-connect-php` | Apache-2.0 | v1.0.2 (2024-09), curl, session-based | | Rejected: stale, uses PHP session, no SSRF guard |
| OIDC RP | `league/oauth2-client` + Socialite providers | MIT | active | | Rejected: OAuth2 client, ID token validation left to us anyway |
| SCIM server | `arietimmerman/laravel-scim-server` | MIT | 1.5.3 (2026-07) | maps Eloquent models directly | Rejected: writes model attributes directly (breaks "domain services only"), its own routes and auth |
| SCIM filter | `tmilos/scim-filter-parser` | MIT | 1.3.0 (2017) | | Rejected: unmaintained; own parser with fuzz tests |
| IdP mock | **Keycloak 26** | Apache-2.0 | active | container, ~450 MB, CI/dev profile only | **Chosen**: SAML and OIDC in one container, realm import, groups |
| IdP mock | SimpleSAMLphp test IdP image | LGPL | community images | | Only if a SAML edge case needs a second IdP |

New production dependencies: `onelogin/php-saml` and its `robrichards/xmlseclibs` (in `api/composer.json`,
listed in `LICENSING.md`). Dev/CI only: the Keycloak image.

## 6. Migrations (all in `api/packages/identity/database/migrations`, reversible, tenant database)

1. `create_identity_providers_table`
2. `create_identity_links_table`
3. `create_identity_external_ids_table`
4. `create_saml_replays_table` (state and code tables come from `auth` in ID-0, item 8)
5. `create_identity_sessions_table`
6. `create_identity_audit_tables` (`identity_audit`, `identity_audit_head`, PostgreSQL trigger; no trigger on
   SQLite test runs, as in ADR 0034)
7. `create_hris_import_runs_table`, `create_hris_import_changes_table`
8. `auth` (ID-0): `create_login_handoff_states_table`, `create_login_handoff_codes_table`, unique index on
   `social_accounts (provider, provider_id)` after de-duplication

`ulams:tenant:create` runs them for new tenants; `upgrade` step registry (ADR 0081) for existing ones.

## 7. Risks

- **XML signature wrapping and parser bugs** in SAML: mitigated by strict mode, allow-listed algorithms,
  DOCTYPE rejection, XSW fixture tests, and tracking `onelogin/php-saml` advisories (Dependabot is on).
- **Account takeover through email linking**: no automatic link without `trust_email` + domain + verified
  email; admins never auto-linked.
- **Lockout** when SSO is enforced and the IdP breaks: superadmin break-glass, enforcement requires a
  successful test login, enforcement can be switched off with the CLI on the server.
- **IdP quirks** (Entra ID PATCH strings, Okta PUT, group object ids instead of names): fixtures per client;
  manual interop run with real tenants before ID-5 is marked done (owner input, section 14).
- **Mass deactivation** from a bad CSV or SCIM misconfiguration: dry run first, caps, rollback, admins exempt.
- **Clock skew** on self-hosted servers: documented NTP requirement; drift 180 s (SAML) and 60 s (OIDC).
- **APP_KEY rotation** would make stored secrets unreadable (ADR 0066 already forbids regeneration).
- **API host change** (custom domain) changes the SAML entity ID and ACS: IdP reconfiguration documented;
  question 4.
- **Attribute conflicts** between SCIM, HRIS, SSO and admins: explicit authority list (4.9).
- **Scope**: eight milestones; each is mergeable and demoable on its own.

## 8. Security review points (checklist for ID-0, ID-2, ID-4, ID-6 PRs)

1. No token, code verifier or secret in any URL, log, audit row or error message.
2. Every `return_to` / `post_logout_redirect_uri` checked against the tenant allow-list (exact origin).
3. State and login codes are single use, short-lived, bound to the initiating browser.
4. SAML: signature required on the assertion, algorithm allow-list, audience, destination, InResponseTo,
   replay, DOCTYPE rejected, body size limit; IdP-initiated off by default.
5. OIDC: issuer exact, alg allow-list, aud/azp, nonce, PKCE, JWKS over `SafeHttp`.
6. Linking rules of 4.5, admins never auto-linked, role mapping to admin off by default.
7. SCIM: token kind separation, filter translated with bound parameters only, PATCH atomic, rate limit.
8. Deactivation revokes all tokens; deprovisioned users cannot log in by any method.
9. HRIS: file stored privately, CSV injection neutralised on export (`=`, `+`, `-`, `@` prefixes), caps.
10. Tenant isolation tests present for every new endpoint.

## 9. Milestones

Each milestone is one PR (or two small ones), with tests, docs and a demo script.

| ID | Content | Demo |
|---|---|---|
| **ID-0** | Social login hardening in `auth`: signed server-side state, `return_url` allow-list, code exchange instead of `?token=` (`LoginHandoffService` and its two tables in `auth`, reused by `identity`), link by email only when the provider says `email_verified`, unique `(provider, provider_id)`, `AccountBlocked` listener revoking tokens. Legacy `front/src` callback switched to the exchange. | Google login still works; the token no longer appears in the browser history; an open-redirect attempt fails. |
| **ID-1** | `identity` package skeleton, `identity_providers`, `identity_links`, audit table, OIDC login (discovery, PKCE, validation), JIT with the basic map (email, names, default role), `front/web` login buttons and callback page, admin API, Keycloak `--profile sso` with an OIDC client. | Log in to a demo tenant through Keycloak OIDC; a new student appears; the audit shows `sso.login`. |
| **ID-2** | SAML SP: per-provider keys, SP metadata, IdP metadata import and daily refresh, AuthnRequest, ACS validation, replay table, encrypted assertions, SP-initiated SLO and IdP-initiated SLO, Keycloak SAML client. Security checklist pass. | Same login through Keycloak SAML; metadata URL imported; a tampered response is refused with a reason in the audit. |
| **ID-3** | Full attribute, group and role mapping, model-fields targets, manager mapping, `UserAttributesSynced` + compliance listener, linking rules and password-confirm linking, `enforce_for_domains` with break-glass, IdP presets. | A Keycloak user in group "Nurses" with department "ICU" logs in and is enrolled by an existing compliance rule within the same request. |
| **ID-4** | SCIM Users: discovery endpoints, filter parser, PATCH, PUT, deactivate/delete behaviour, SCIM tokens bound to a provider, rate limit, external ids, authority rules. | Okta SCIM test suite (or recorded Entra ID requests) creates, updates and deactivates a user; the deactivated user's session ends. |
| **ID-5** | SCIM Groups and members, managed-group flag, Entra/Okta fixtures, docs for both IdPs, comparison cells updated. | Group pushed from the IdP appears with members; removing a member removes access to its learning path. |
| **ID-6** | HRIS CSV: profiles, upload, dry-run diff, caps, apply, rollback, manager links, CLI hand-written command. | Upload a 500-row CSV, review 12 changes, apply, roll back one run. |
| **ID-7** | HRIS API variant (JSON rows, Idempotency-Key), `HrisConnector` interface with a scheduled runner and a fake connector for tests. | `ulams hris import rows.json --dry-run` from the CLI and through MCP. |
| **ID-8** | Admin screens: Providers, SCIM tokens, HRIS imports, Audit; users screen badges and linked identities; admin SSO login. | Configure an Entra-style SAML provider entirely from the admin UI with the test-login claim viewer. |

**Recommended first milestone: ID-0**, because it closes a live account-takeover and token-leak path in the
current social login and builds the state and code-exchange primitives every later milestone uses. ID-1
follows directly; ID-8 screens can run in parallel with ID-4 onwards once the admin API of ID-1 to ID-3 is
stable.

## 10. API and CLI coverage summary

Every admin endpoint has OpenAPI attributes, a policy, a scope mapping and generated CLI/MCP commands; SCIM
endpoints are documented in OpenAPI for integrators but excluded from the CLI. Public endpoints (providers list,
discovery, start, callback, ACS, exchange) are throttled per IP with the named limiter `ulams-identity`.

## 11. Webhooks

Optional and small, in ID-4: add `user.created`, `user.updated`, `user.deactivated` to the webhooks
`EventCatalog` from the auth events, since SCIM and HRIS make them meaningful (question 10).

## 12. Documentation languages

The docs site is English only (no locales configured), so identity docs are English. Learner-facing strings
(login buttons, discovery, callback errors) are EN/PL/ZH in `front/web`. Admin strings follow the admin app's
existing translation files.

## 13. Open questions (each with the default that will be used)

1. **Package**: new `api/packages/identity` rather than extending `auth`. Default: new package (ADR 0120).
2. **Attribute authority**: default order SCIM > HRIS > SSO/JIT > manual edits, editable per tenant.
3. **JIT for unknown users**: default on per provider, new users get `student`; role mapping off; admin
   mapping off.
4. **SAML SP entity ID**: default the metadata URL on the tenant API host; alternative a host-independent URN
   (`urn:ulams:{tenant}:{provider}`) that survives domain changes. Default: URL, with the URN offered as an
   option on the provider form.
5. **IdP certificate changes from metadata refresh**: default add new certs automatically, never remove the
   last trusted cert or change the entity ID without an admin action; notify admins.
6. **SHA-1 signatures**: default rejected; per-provider opt-in with warning and audit row (legacy ADFS).
7. **SCIM DELETE**: default deactivate and unlink, keep learning records; anonymise is opt-in.
8. **Audit storage**: default a new hash-chained `identity_audit` table in the package; extracting a shared
   chain helper from `living-course` into `core` is a later refactor, not part of this lane.
9. **OIDC back-channel logout and `private_key_jwt`**: default not in this pass; added on customer request.
10. **User lifecycle webhooks** (`user.created|updated|deactivated`): default yes, in ID-4.
11. **HRIS missing rows**: default `ignore`; `deactivate` (full sync) is opt-in per profile with caps 10 % /
    50 accounts.
12. **HRIS new users**: default created active without password and without mail; invitation mail opt-in per
    profile.
13. **Existing `csv-users` import**: default kept unchanged and documented as the simple import; HRIS import is
    the recommended path for HR data.
14. **SSO enforcement exceptions**: default only superadmins (env) keep password login under enforcement.
15. **Keycloak e2e on every PR**: default nightly and on PRs labelled `identity`; fixture tests on every PR.

## 14. Owner-only inputs (not needed to start; defaults above cover the code)

- Optional real-IdP interop accounts for a manual check before ID-5 is ticked: an Okta Integrator (free)
  developer org and a Microsoft Entra ID tenant (free tier through an Azure account), and optionally a Google
  Workspace test domain (paid; skip by default). No spending is required; Keycloak covers CI.
- Approval of ADR 0120 and 0121 (currently Proposed).
- No DNS, hosting or deployment changes (work stays local until the roadmap is done).

## 15. ID-1 as shipped

Differences from the plan text, so later milestones start from what is in the code:

- `POST /api/identity/discover` (email to provider) and the admin "test login" endpoint are not part of ID-1; they
  arrive with ID-3 (enforcement, claim viewer). The login page shows one button per enabled provider.
- `identity_external_ids`, `saml_replays`, `identity_sessions` and the HRIS tables are created by the milestones that
  use them. ID-1 migrations: `identity_providers`, `identity_links`, `identity_audit` + `identity_audit_head`.
- `identity_providers` has `oidc_secret_hint` (last four characters, only for secrets of 16 characters or more);
  `enforce_for_domains`, `attribute_map`, `group_map` and the SAML columns are added by ID-2 and ID-3.
- JIT needs an address the provider vouches for (`email_verified: true`) or `trust_email` on the provider; an
  unverified address is refused with `email_unverified` instead of creating an unverified account.
- Lookups of users go through `auth.providers.users.model` (`Ulams\Identity\Support\Users`), because Passport can only
  issue tokens for that class; this was found by the Keycloak run, not by the fixture tests.
- The Keycloak run is `.github/workflows/identity-keycloak.yml` (pull requests labelled `identity`, a manual run, and
  called by `nightly-conformance.yml`, job `sso`), with `--profile sso` in `.github/conformance/compose.yml`.
- A new token scope area `identity` (`api/admin/identity*`); `api/identity/*` is public.
