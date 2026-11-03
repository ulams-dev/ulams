# Academy entry points: per-department, per-course and per-role hosts with their own branding

Status: Proposed (2026-10-22). Written after the owner decision "everything labelled Coming must be implemented".
Open questions in section 9 have recommended defaults that are used unless the owner says otherwise. ADR 0195
(Proposed) records the decision; it amends ADR 0141.

## 1. Goal

One academy (tenant) can be reached at several branded addresses, each aimed at an audience:

- a **department** host (`sales.academy.example.org`) with the department's look and catalogue first;
- a **course** host (`kubernetes.example.org`) that opens straight on one course's landing and sells or enrols it;
- a **role** host (`managers.academy.example.org`) that preselects the role variant of courses that have variants.

Access never depends on the host. The same learner account, enrolments, certificates and reports serve every entry
point of the tenant.

## 2. TODO items covered

| TODO item | Where |
|---|---|
| 6.1 "(new) Policy lifecycle delivery: ... provision distinct branded department domains (Coming)" (the domain part) | EP-1 to EP-4 |
| 6.1 "(new) Branded entry points inside one academy" (added by this plan) | EP-1 to EP-7 |
| 6.4 "Per-tenant branding, catalogue, sales pages" (the per-course sales page on its own host) | EP-5 |
| 4.4 "(new) Learner routing" (role hint from a role host) | EP-6, together with CV-5 |

P6R-11 of `phase-6-remaining.md` ("department host aliases") is delivered by EP-1 to EP-4; P6R-11 keeps only the
style-guide tokens, which move to `style-guide-course-design.md`.

## 3. Current state (read on `main` at `602f94174`)

| Area | Fact | Where |
|---|---|---|
| Tenant record | `front_host`, `admin_host` per tenant in the platform database | `api/packages/tenancy/src/Models/Tenant.php` |
| API hosts | one env file per API host, written by `domain:add` | `api/packages/tenancy/src/Services/MultidomainRegistry.php` |
| Unknown hosts | 404 when `TENANCY_ENFORCE_HOSTS` is on | `api/packages/tenancy/src/Http/Middleware/RejectUnknownHost.php` |
| Front resolution | host patterns `ULAMS_TENANT_HOSTS` (`{slug}.app.localhost=>http://{slug}.localhost`) | `front/web/src/lib/tenant.ts`, `@ulams/sdk/tenant` |
| Sessions | `__Host-` cookies, no `Domain`, exact-Origin checks in the BFF | `front/web/src/lib/cookies.ts`, `front/web/src/lib/bff.ts` |
| Return URLs | trusted origins per tenant (`FRONTEND_URL`, `ADMIN_URL`, `APP_URL`, `TRUSTED_ORIGINS`) | `api/packages/auth/src/Support/ReturnUrlPolicy.php` |
| One-time code hand-off | 60 s, single use, bound to host and S256 verifier (social login) | `POST /api/auth/social/exchange`, ADR 0120 |
| Theme | tenant settings `theme.theme` (preset) and `theme.accent`; brand importer proposes a diff | `front/ui/src/theme/brand-import.ts`, admin `Settings/theme.tsx` |
| Canonical URLs | built from the request origin | `front/web/src/i18n/locales.ts` |
| Access | enrolment, entitlements (ADR 0130), groups and rules (6.2) | `api/packages/entitlements`, `api/packages/compliance` |

## 4. Design

### 4.1 Data (platform database, `tenancy` package)

`tenant_entry_points`:

| Column | Notes |
|---|---|
| `id`, `tenant_id` | |
| `host` | unique across the platform, lower case, validated like `front_host` (no IP, no content-origin suffix, no platform host) |
| `key` | short slug, unique per tenant, used for the `/go/<key>` fallback |
| `kind` | `academy`, `department`, `course`, `role` |
| `target` | json: `{group_id}` for department, `{course_id}` for course, `{role}` (variant role key) for role; validated against the tenant database through a tenancy job, not a cross-database foreign key |
| `label` | shown in the header and the switcher |
| `theme` | json: preset, accent, logo file id, alt text; same validation and contrast report as the tenant theme |
| `seo` | json: `canonical` (`tenant` or `self`), `index` (bool), title and description overrides |
| `locale` | optional default locale |
| `status` | `pending_dns`, `active`, `disabled` |
| `verification_token`, `verified_at` | for custom domains (section 4.5) |

ADR 0141's `tenant_host_aliases` is not created; its fields map onto this table.

### 4.2 Resolution

- **Front**: `tenantForHost` first matches `ULAMS_TENANT_HOSTS`; if no rule matches, it asks the platform API
  `GET /api/platform/entry-points/resolve?host=<host>` (public, returns only `tenant slug`, `kind`, `key`, `label`,
  theme and SEO fields, never ids of people), cached in the existing SWR cache for 60 s with a 10 s negative cache.
  The resolved entry point becomes `Astro.locals.entry`.
- **API**: unchanged host model. The front calls the tenant API host as today and sends `X-Ulams-Entry: <key>`
  only for presentation endpoints (`GET /api/settings`, catalogue listing), which may filter and theme; no policy or
  repository reads the header for access.
- **`/go/<key>`** on the tenant's main host sets the same context in a session-scoped cookie (presentation only).

### 4.3 What each kind changes

| Kind | Home page | Catalogue | Theme | Other |
|---|---|---|---|---|
| `academy` | tenant landing | all | override | a second brand of the same academy |
| `department` | department landing (catalogue filtered to courses on the group's paths and required training) | group first, "all courses" link | override | policy variants of P6R-8 land here |
| `course` | the course landing (`/courses/<id>` rendered at `/`) | hidden; header links to the main academy | override | course price and checkout as on the main host; `canonical: self` allowed |
| `role` | tenant landing | variants of the role preselected | override | sets the CV-5 self-declared role as a suggestion the learner confirms at enrolment |

### 4.4 Sessions and sign-in across hosts

- Each host has its own `__Host-` session. Signing in on an entry host works directly (same tenant API, same users).
- Switching hosts while signed in: the current front calls `POST /api/auth/handoff` with the target host; the API
  checks that the host is an active entry point (or the main host) of the same tenant and returns a one-time code
  (60 s, single use, bound to that host and an S256 challenge from the target). The target front redeems it at
  `POST /api/auth/handoff/exchange` and sets its own session. No token appears in a URL other than the code.
- `ReturnUrlPolicy` and `EnforceTrustedOrigin` read the tenant's active entry hosts (cached, invalidated on change),
  so social, OIDC and SAML logins can return to an entry host. Disabling an entry point removes it at once.
- Logout on one host offers "sign out everywhere", which revokes the tokens of the user (existing revoke path).

### 4.5 DNS, TLS and verification

- Platform-provided hosts follow the deployment's flat host rule (`{slug}-{key}.<app domain>`; the hosted service
  uses flat hosts because Cloudflare Universal SSL covers one level, see `operators/vps-cloudflare`).
- Custom domains: the admin adds the host, ulams shows a TXT record `_ulams-verify.<host>` with the token and a
  CNAME target; a scheduled job verifies and flips `pending_dns` to `active`.
- TLS: Caddy on-demand TLS with `ask https://<platform api>/api/platform/entry-points/tls-allowed?domain=` (200 only
  for active or pending-verified hosts, rate limited); Cloudflare deployments use custom hostnames (owner action,
  documented). Local and CI use `.example.test` hosts through `/etc/hosts`-free Playwright host mapping.

### 4.6 SEO

- Canonical: the main host URL for shared pages; `self` only for course entry points that opt in.
- `noindex` default for `department` and `role` hosts (internal audiences); `index` default for `course` and
  `academy`.
- Sitemap and `llms.txt` per host list only what the host shows; hreflang stays on the same host.
- The platform landing and demo tenants are untouched.

### 4.7 Admin, CLI and API

- Admin: Settings → Entry points (list, add with kind and target picker, theme with the brand importer and contrast
  report, SEO, DNS instructions, status). Permission `entry_points_manage`.
- API: `GET/POST/PATCH/DELETE /api/admin/entry-points` (tenant admin, writes through a tenancy service to the platform
  table, audited), the public resolve and TLS endpoints above.
- CLI: `ulams entry-points list|add|verify|disable`, generated from OpenAPI.

## 5. Dependencies

No new runtime dependency. DNS TXT lookups use PHP `dns_get_record` behind a small interface so tests can fake it.

## 6. Migrations

- `tenancy` (platform database): `tenant_entry_points`.
- Permissions seeder: `entry_points_manage`.
- Reversible; no change to existing tables.

## 7. Tests

- Tenant isolation: an entry host never resolves to another tenant; the admin endpoints refuse another tenant's
  entry points; the route-walker list gets the two public endpoints with reasons.
- Access: a learner on a department or course host sees exactly the access they have on the main host (no grant by
  host, no group join); a guest on a course host can buy or enrol only as on the main host.
- Hand-off: replay, expired code, wrong host, other tenant, missing verifier, disabled entry point; no token in any
  URL (Playwright against the mock API, like the social login suite).
- Return URLs: logins can return to an active entry host and are refused after it is disabled.
- SEO: canonical and robots per kind; sitemap per host.
- Front: unit tests for resolution order and caching; axe (WCAG 2.2 AA) for the admin screen and a themed entry host
  at desktop and 360 px; the theme override passes the contrast report.
- Existing suites stay green; H5P and SCORM untouched.

## 8. Risks

- Host confusion bugs could leak another tenant's theme or catalogue: one resolver, unique hosts, isolation tests.
- Open TLS issuance abuse: the `ask` endpoint answers only for known hosts and is rate limited.
- Duplicate-content SEO: canonical defaults and `noindex` for internal audiences.
- Session confusion across hosts: per-host sessions by design; hand-off is explicit.

## 9. Open questions (recommended default in bold)

1. Should a department host auto-join a new learner to its group? **No.** Group membership comes from an admin, an
   enrolment rule, SSO group mapping (ID-3) or SCIM. A later opt-in could allow it for a verified e-mail domain.
2. Hand-off on host switch, or separate sign-ins? **One-time code hand-off**, as in 4.4.
3. Platform-provided host pattern. **`{slug}-{key}.<app domain>`** (flat).
4. Canonical for course hosts. **Main host by default; `self` opt-in per course entry point.**
5. Limit of entry points per tenant. **20**, configurable (`TENANCY_MAX_ENTRY_POINTS`).
6. Admin app or studio for the screen? **Admin app** (Settings), next to the theme screen.

Owner-only inputs (nothing blocks on them): real custom domains beyond `.example.test`; the Cloudflare custom
hostname setup for the hosted service (with the first production deployment, issue #24).

## 10. Milestones (one branch and PR each, based on `main`)

| # | Slice | Demo |
|---|---|---|
| EP-1 | `tenant_entry_points`, admin API, validation, audit, isolation tests, CLI | `ulams entry-points add --kind department` stores a pending host |
| EP-2 | Front resolution (resolve endpoint, SWR cache, `Astro.locals.entry`), theme override, `/go/<key>` fallback | `sales.coffee.example.test` shows the coffee academy in the Sales theme |
| EP-3 | Trusted origins and return URLs from entry hosts; hand-off code and exchange; Playwright hand-off suite | sign in on the main host, switch to the Sales host still signed in |
| EP-4 | Department kind: filtered catalogue and department landing; admin screen with axe | a Sales learner sees Sales courses first; access unchanged |
| EP-5 | Course kind: course landing at `/`, checkout as on the main host, SEO (canonical, robots, sitemap per host) | `kubernetes.example.test` sells and opens one course |
| EP-6 | Role kind: role hint for CV-5 routing, confirmed by the learner | `managers.example.test` preselects the manager variant |
| EP-7 | Custom domains: TXT verification job, Caddy `ask` endpoint, operator docs for Caddy and Cloudflare | a custom host goes from pending to active in the local stack |

Recommended first: **EP-1**, then EP-2 and EP-3. EP-4 is what the policy lifecycle (P6R-8 onwards) needs.
