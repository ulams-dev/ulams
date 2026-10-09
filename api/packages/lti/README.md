# LTI 1.3

Both directions of LTI 1.3 for every tenant (ADR 0012):

- **Platform**: ulams launches external tools inside lessons (topic type `LtiLink`), receives grades
  through AGS 2.0, shares the course member list through NRPS 2.0 (per tool, off by default) and lets
  authors pick content in the tool (Deep Linking 2.0).
- **Tool**: Moodle, Canvas and other LMSs launch ulams courses, with grade passback and a course
  picker for deep linking. Launch validation by `packbackbooks/lti-1p3-tool` (Apache-2.0).

## Endpoints

| Who calls | Endpoint | Purpose |
|---|---|---|
| anyone | `GET /api/lti/jwks`, `GET /.well-known/jwks.json` | Our public keys (next, active, recently retired) |
| admin (`lti_manage`) | `GET /api/admin/lti/endpoints` | Issuer and URLs to register us elsewhere |
| admin (`lti_manage`) | `/api/admin/lti/tools` (CRUD), `POST /api/admin/lti/tools/{tool}/deep-link` | Tools we launch; start deep linking into a lesson |
| admin (`lti_manage`) | `/api/admin/lti/platforms` (CRUD) | Platforms that may launch us |
| learner | `POST /api/lti/launches/{topic}` | OIDC login URL of the topic's tool (2-minute, single-use hint) |
| tool | `GET/POST /api/lti/platform/authorize` | OIDC auth: posts the signed `id_token` to the tool |
| tool | `POST /api/lti/platform/token` | AGS access token (client credentials, JWT assertion) |
| tool | `/api/lti/platform/ags/{course}/lineitems[/{id}[/scores\|/results]]` | AGS line items, scores, results |
| tool | `GET /api/lti/platform/nrps/{course}` | NRPS 2.0 membership container (scope `contextmembership.readonly`, `nrps_enabled` on the tool; `limit`, `page`, `role`; `Link` rel=next) |
| tool | `POST /api/lti/platform/deep-links` | Deep-linking response: creates `LtiLink` topics |
| platform | `GET/POST /api/lti/tool/login`, `POST /api/lti/tool/launch` | OIDC login initiation and launch |
| learner's browser | `POST /api/lti/tool/deep-link` | Returns the picked courses to the platform |
| front | `POST /api/lti/tool/exchange` | One-time launch code to a Passport token |

## Registering

**A tool in ulams** (platform side): `POST /api/admin/lti/tools` with the tool's OIDC login URL,
launch URL, optional deep-linking URL, and its JWKS URL or PEM public key. ulams issues `client_id`
and `deployment_id`; give the tool those plus the issuer, `oidc_auth_url`, `token_url` and
`jwks_url` from `GET /api/admin/lti/endpoints`.

**ulams in Moodle** (tool side): in Moodle add an external tool (LTI 1.3) with the tool URLs from
`GET /api/admin/lti/endpoints` (`tool.oidc_login_url`, `tool.launch_url`, `jwks_url`), then
`POST /api/admin/lti/platforms` with Moodle's issuer (site URL), client ID, deployment ID, and its
`auth.php`, `token.php` and `certs.php` URLs. A resource link picks the course with the custom
parameter `course_id=<id>` (deep linking sets it), `?course=<id>` on the target link URI, or the
platform's `default_course_id`.

## Security

- Per-tenant RSA key set, private keys encrypted with the tenant `APP_KEY`, rotated monthly
  (`ulams:lti:rotate-keys`, scheduled; `--init` at provisioning, step `lti_keys`).
- Login hints, deep-linking data and picker forms are signed with an `APP_KEY`-derived secret and
  short-lived; login hints, JWT ids, OIDC state/nonce and launch codes are single use
  (`lti_nonces`).
- Every inbound JWT is checked for signature, `iss`, `aud`, `exp`, nonce/jti and deployment id.
- Outgoing requests (JWKS, tokens, AGS) go only to public `https` addresses, without redirects.
- Tool side: users are matched by `(platform, sub)`, never by e-mail; Instructor maps to tutor,
  nobody to admin.
- Every launch is audited in `lti_launches`.

## Configuration

See [src/config.php](src/config.php) and the LTI section of
[docs/enviromental-variables.md](../../docs/enviromental-variables.md).

## Tests

```bash
vendor/bin/phpunit --testsuite lti
```

Fake tools and platforms sign with in-test RSA keys (`tests/Support/KeyPair.php`); the tool side's
HTTP client is replaced with an in-memory platform. `tests/Feature/TenantIsolationTest.php` checks
that hints, AGS tokens, deep-linking data and keys of one tenant are rejected by another; the
HTTP version against two real tenants is in `packages/tenancy/tests/Integration`.
