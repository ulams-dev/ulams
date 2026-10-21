# Plan: tech debt (Phase 0 and 1 leftovers) and the quality bar

Status: **draft, waiting for the product owner's approval.** Nothing in this plan is implemented.

Facts were read from `main` at `4bde7dd1f` (2026-10-21), the GitHub issues and pull requests, the CI runs
and `.github/dependabot.yml`. This plan replaces the matching packages of `docs/plans/leftovers-0-2.md`
(L0-01, L0-04, L0-11, L0-15, L0-18, L0-20) where the code has moved on since 2026-10-09. The ADRs it
relies on already exist: 0014 (content origin), 0042 (no WebSocket server), 0047 (OpenAPI attributes)
and 0055 (course experiments). Two new records are proposed with this plan: **0190** (tenant isolation
route coverage) and **0191** (register of learning-benefit claims).

## 1. Goal

Close the remaining Phase 0 and Phase 1 tech-debt items and turn the quality bar from a list of
intentions into checks that run in CI or nightly. Each item ends up in one of four states:

- **done**: the code already does it; tick it with a note.
- **obsolete**: the reason for the item is gone; close it with a note. The item text stays as it is.
- **do**: a work package in a TD milestone below.
- **owner** or **moved**: only the owner can act, or another plan owns the item.

## 2. Scope

### In scope (TODO items, quoted)

Phase 0:

1. "Multitenancy via `gecche/laravel-multidomain`: current setup, dynamic subdomains possible? (partial: …)"
2. "(new) Remaining legacy references (partial: …)"
3. "(new) Drop Soketi until realtime is needed (broadcast driver is `log`); Laravel Reverb after 0.2"
4. "(new) Containers cannot reach `storage.localhost` (it resolves to the container itself); use the
   internal MinIO endpoint for server-side fetches (e.g. Image topic creation)"
5. "(new) Responsible disclosure: the payment-callback, LRS-token, webcam-upload and course-access issues
   exist in the upstream EscolaLMS packages; notify upstream users"
6. "(new) Publish images to GHCR (`ghcr.io/ulams-dev/*`, decided 2026-10-08); publish the base image as
   `ulams/php:8.3` with source offers for its GPL programs"
7. "(new) Yarn install on Node 23 needs `--ignore-engines` (obsolete: …)"
8. "(new) Dependency holds (Dependabot ignore rules in `.github/dependabot.yml`): `sharp` 0.35 … `@ant-design/pro-components` …"
9. "(new) ESLint 10 in `admin` (umi lint, fabric config) and `front` …"
10. "(new) Prettier 3 in `admin` (2.8.8) and `front` (2.4.1) …"
11. "(new) Stripe in the old `front` cart: `@stripe/react-stripe-js` 7 needs `@stripe/stripe-js` 10 …"
12. "(new) Move the `@OA\` docblock annotations (223 files) to PHP attributes and drop the abandoned
    `doctrine/annotations`"

Phase 1:

13. "(new) Production: serve content origins from a separate registrable domain (not same-site with the
    app), and add registered LTI tool origins to the front/admin `frame-src` …"
14. "(new) Turn on the nightly conformance runs (`NIGHTLY_CONFORMANCE=true`) and run the saLTIre job once
    with an operator"

Quality bar:

15. "Mocked LLM in tests + eval command on golden fixtures (schema, citation coverage, quiz answers
    supported, duration, cost)"
16. "Tenant isolation tests for every endpoint"
17. "WCAG 2.2 AA checks for learner-facing UI"
18. "A/B experiment + delayed-retention metric for every feature claiming learning benefit"
19. "E2E: upload → interview → live course → chat edit → source change → accepted update → progress intact"
20. "Commerce E2E: buy → webhook → access granted; refund → access revoked; missed webhook repaired by
    reconciliation"
21. "Existing tests and linters green; H5P/SCORM unchanged; README per package"

### Out of scope

- The Phase 8 self-hosting plan, written separately: "Upgrade PostgreSQL 12 (EOL) to 16/17", "Replace
  MinIO with SeaweedFS (or RustFS) and give each tenant its own S3 identity", Helm, SBOM and signing,
  backups. Items 1 and 4 depend on it only for per-tenant storage credentials and the internal endpoint
  name.
- The arm64 problem in `publish.yml` (another agent is fixing it) and `nightly-conformance.yml`
  itself (fixed in #306).
- Building the experiments package (Phase 2.7 "Built-in A/B experiments per course" and Phase 4). Item 18
  only defines how claims are recorded and checked.
- Building commerce (Phase 6.4 and the separate commerce plan). Item 20 only defines the harness.

## 3. Current state per item

| # | Item | State on `main` | Verdict |
|---|---|---|---|
| 1 | Multitenancy | Dynamic subdomains work (`ulams:tenant:create`). Production TLS is documented with Caddy on-demand TLS and a file-based `ask` site (`front/docs-site/examples/production/Caddyfile:20`, `:348`) and, for the VPS reference, at Cloudflare (`deploy/vps-cloudflare`, ADR 0092). Left: per-tenant S3 credentials (Phase 8 plan). The two-tenant HTTP test `api/packages/tenancy/tests/Integration/TenantIsolationTest.php` is opt-in (`TENANCY_INTEGRATION=1`) and no workflow runs it. The docs example Caddyfile is not validated in CI (the `deploy` job only validates `deploy/vps-cloudflare`) | **do** (TD-3), then tick with a note pointing at the Phase 8 item |
| 2 | Legacy references | Done: `escolalms/php` image replaced (`api/docker/php`), ReportBro removed (the `templates-pdf` migration tool is meant to keep the name), no Docker Hub publishing (Docker Hub is a pull mirror only), SQL views recreated by `ulams:db:recreate-views` inside `ulams:upgrade` (`api/packages/tenancy/src/Upgrade/DefaultUpgradeSteps.php:31`). Left: upstream CI badges and images hot-linked from `github.com/EscolaLMS/*` in package `README.md` and `ADMIN.md` files (for example `courses-import-export`, `files`, `images`, `courses`, `templates-email`); `ulams.app` is now the owner's real domain, so "replace the placeholder" means: hostnames must come from configuration, not from code; three links go to `http://docs.ulams.app/`, which does not serve the docs (they are on `https://ulams-dev.github.io/ulams`), for example `admin/src/app.tsx:216` | **do** (TD-1) |
| 3 | Soketi | `soketi` service in `api/docker-compose.yml:324`, `ws.localhost` and metrics routes in `api/docker/conf/Caddyfile`, `pusher/pusher-php-server` in `api/composer.json:73`, channel files `api/routes/channels.php`, `api/packages/{webinar,consultations}/src/channels.php`, every env file has `BROADCAST_DRIVER=log`. No `ShouldBroadcast` class exists. Seven docs pages mention Soketi. ADR 0042 (Proposed) decides removal | **do** (TD-2) |
| 4 | `storage.localhost` from containers | The Image topic reads through the disk (`api/packages/topic-types/src/Models/TopicContent/Image.php:68`); `ImagesService` uses `Storage::get`. Not audited: other server-side reads of public URLs (course import/export, Markdown helper, the PDF service rendering images by URL) | **do** (TD-3): audit plus a guard test, then tick |
| 5 | Responsible disclosure | Notice drafted in `docs/security/upstream-notice.md` (status "draft, not sent"); sending is #50 | **owner** (#50); TD-1 refreshes the draft against upstream's default branch first |
| 6 | GHCR and base image | `publish.yml` pushes `ghcr.io/ulams-dev/{php,api,h5p,pdf,admin,front,mjml}`; the base image is PHP 8.4 (not `ulams/php:8.3`, superseded, #44). `api/docker/php/NOTICE` lists the GPL programs and points at Alpine aports, but there is no written source offer and the image does not record the exact package versions | **do** (TD-1): record the versions in the image and add the written offer; then tick |
| 7 | Node 23 `--ignore-engines` | The owner confirmed it obsolete in #44. Two docs pages still tell people to pass `--ignore-engines`: `front/docs-site/src/content/docs/developers/cli.mdx:35` and `developers/claude-code.mdx:20` | **obsolete**: remove the flag from the docs in TD-1, then close with a note |
| 8 | sharp and pro-components holds | The reason for the sharp hold is stale: `astro@7.3.8` now has an optional dependency on `sharp ^0.35.4`, while `front/web` and `front/docs-site` pin `sharp 0.34.5`, so two copies are installed. The pro-components range hold (`>= 2.8.5, <= 2.8.10`) is still in place at 2.8.4 | **do** (TD-4) |
| 9 | ESLint 10 in admin and front | admin: `.eslintrc.js` with `@umijs/fabric` plus `@umijs/lint`, eslint `^8.56`; old front: `.eslintrc.cjs`, `@typescript-eslint` 7, eslint `^8.56`. The other five workspaces already use flat config with `eslint@10.12.0`, `@eslint/js`, `typescript-eslint@8.71.1` | **do** (TD-4) for admin; old front by decision Q4 |
| 10 | Prettier 3 | admin 2.8.8 with a CI step that resolves `prettier/bin-prettier.js` (`ci.yml:153`); old front 2.4.1 with no CI check. No `.git-blame-ignore-revs` | **do** (TD-4) for admin; old front by Q4 |
| 11 | Stripe in the old front | `@stripe/stripe-js ^1.19`, `@stripe/react-stripe-js ^1.6`, three files in `front/src`, no test | **hold** until Q4 (old front retirement) or the Sylius checkout |
| 12 | `@OA\` to attributes | **291** files now carry `@OA\` docblocks (not 223), none use `#[OA\…]`. `zircote/swagger-php 6.12.0`, `darkaonline/l5-swagger 11.1.0`, `doctrine/annotations ^2.0` required directly, `api/app/Support/Swagger/DocBlockConfigFactory.php` keeps docblocks working. The snapshot already exists: CI fails when `front/cli/spec/openapi.json` (the normalised spec) differs from the generated one (`ci.yml:390`), and `sdk-php` is regenerated and diffed | **do** (TD-6) |
| 13 | Separate registrable content domain | The owner chose same-site `*.content.ulams.app` (decision of 2026-10-09, ADR 0014 amended; #24 closed). The separate-domain mode stays supported and documented; LTI tool origins are in `frame-src` (ADR 0044). Nothing is left to build; the deployment itself is the Phase 8 item "First production deployment on the VPS and Cloudflare" | **obsolete** as worded: close with a note |
| 14 | Nightly conformance and saLTIre | The repository variable `NIGHTLY_CONFORMANCE` has been `true` since 2026-10-09, and the scheduled run passes again since #306. Left: one operator run of the saLTIre job (#141) | **owner** (#141); TD-1 records the first half as done in the item's note |
| 15 | Mocked LLM and evals | Done in code: the fake driver with cassettes (`api/packages/ai/src/Fake`), four eval commands (`course-builder:eval`, `living-course:eval`, `tutor:eval`, `learner-insights:eval`) on golden fixtures with schema, citation coverage, supported quiz answers, duration and cost checks, and a Markdown report per run. Gaps: no CI job runs them on the fake driver, so a regression in a check only shows on a live run; the "medium PDF" fixture is 7.9 KB (`coffee-handbook.pdf`); no live run has been recorded for the tutor and remediation evals (needs a key, Phase 4 item) | **do** (TD-5) |
| 16 | Tenant isolation for every endpoint | About a dozen packages have isolation tests (for example `api/packages/compliance/tests/Api/TenantIsolationTest.php`, which swaps the Passport key pair and expects 401 on a hand-written endpoint list). About 640 route definitions exist; most packages have none. A route-walking test already exists for token scopes (`api/tests/Integrations/TokenScopeMapCoverageTest.php`), so the same technique works for isolation | **do** (TD-3), ADR 0190 |
| 17 | WCAG 2.2 AA | `web-a11y` CI job: axe and keyboard checks on the learner pages against a mock API (`front/web/tests/a11y/*.a11y.spec.ts`, seven suites); axe runs on every studio screen in the e2e suites; catalogue components have vitest-axe tests. Not covered: the old front (`front/src`) and new learner pages added without a suite | **do** (TD-7): a coverage check that every learner route has an a11y spec |
| 18 | A/B and delayed retention | The remediation holdout exists (`OutcomeTracker::helpedVsHoldout()`, off by default); the experiments package (ADR 0055, consent model confirmed in #58) is not built. Features that claim a learning benefit are named only in prose | **do** (TD-8, ADR 0191): a claims register and a CI check; the package stays a Phase 2/4 item |
| 19 | Journey E2E | Covered in two Playwright suites on the fake driver: `front/web/tests/e2e/studio.spec.ts` (upload → interview → generation → apply → chat edit of a quiz question) and `studio-living-course.spec.ts` (publish → learner completes → source v2 → accept → progress intact). Both are skipped unless `STUDIO_E2E=1` and no workflow runs them. "Course live on subdomain" is not asserted on a tenant host | **do** (TD-7): one nightly job running both suites against a containerised stack, and a tenant-host assertion |
| 20 | Commerce E2E | `api/packages/commerce` has the `CommerceProvider` interface and the Wellms cart adapter; the Sylius adapter, entitlements and reconciliation do not exist | **do** (TD-9): harness only (fake provider, scenario list, test skeletons marked skipped) |
| 21 | Tests, linters, READMEs | Main is green (CI run on `4bde7dd1f`), the PHPUnit quarantine is empty. CI checks that every `api/packages/*` has a `README.md`, but `bulk-notifications/README.md` is a title only, `translations` (2 lines), `permissions` (7) and `cmi5` (10) are stubs, no package README has a "Limitations" section, 16 lack a configuration section and 25 an API section. Three JS workspaces have no README: `front/sdk`, `front/ui`, `front/docs-site`. Six Dependabot PRs have been open since 2026-10-10 (#227, #232–#235, #252), three of them red | **do** (TD-1 for Dependabot triage, TD-10 for READMEs) |

## 4. Batch design (prioritised)

### Batch A: safe now, no decision needed

- TD-1 hygiene: items 2, 6, 7 (docs), 13 and 14 notes, Dependabot triage, upstream notice refresh.
- TD-2 Soketi removal (ADR 0042 is Proposed; the owner approved the plans in batch).
- TD-3 tenant isolation coverage and the `storage.localhost` audit.
- TD-5 evals in CI on the fake driver.

### Batch B: mechanical but large, isolate in their own PRs

- TD-4 dependency holds in admin (ESLint 10, Prettier 3, sharp 0.35, pro-components).
- TD-6 OpenAPI attributes (291 files, eight package batches, the spec snapshot guards every batch).

### Batch C: new checks that need a short decision (defaults in section 9)

- TD-7 nightly journey E2E and the a11y route coverage.
- TD-8 learning-benefit claims register (ADR 0191).
- TD-9 commerce E2E harness (waits for the commerce plan's names; harness only).
- TD-10 README content standard.

### Close with a note (no code)

- Item 7 (after TD-1 removes the flag from two docs pages), item 13, and the first half of item 14.
  Following `CLAUDE.md`, the item text is never reworded: the note is appended in brackets, as with
  earlier obsolete items (for example the RevenueCat verifier, #46).

## 5. Work packages

### TD-1 Hygiene and notes (docs and config, one PR)

- `README.md` and `ADMIN.md` of `api/packages/*`: remove upstream CI badges (`github.com/EscolaLMS/*/actions`)
  and coverage badges; images hot-linked from `github.com/EscolaLMS/*` are downloaded into
  `api/packages/<pkg>/docs/img/` (each under 200 KB; the 2 MB CI guard applies) and linked relatively.
  Provenance stays in one place: the "Based on EscolaLMS" lines in `README.md` and `LICENSING.md` and the
  package `LICENSE` copyright lines. The `escolalms.github.io/headless-format/paper.pdf` citation in the
  docs data stays (it is a citation, not a dependency).
- Base domain from configuration: `admin/src/app.tsx:216` and the two other `docs.ulams.app` links point at
  the docs URL from the runtime settings (`ULAMS_DOCS_URL`, default `https://ulams-dev.github.io/ulams/`).
  `front/src/lib/tenant/resolveApiUrl.ts` already treats the production form as documentation only; leave it.
  Guard: `scripts/check-hosts.mjs` (no dependency) fails when a source file under `admin/src`, `front/src`,
  `front/web/src` or `api/packages/*/src` contains a `ulams.app` host that is not in an allow-list
  (`scripts/hosts-allowed.txt`: the public landing, sitemap and docs data). Runs in the `changes` job of
  `ci.yml` next to the ADR index check.
- `api/docker/php/Dockerfile`: write `apk info -v | sort` to `/usr/share/doc/ulams/apk-packages.txt` and
  the Alpine release to an OCI label `org.ulams.alpine-release`. `api/docker/php/NOTICE` gets a written
  offer (three years, from the project's security contact in `SECURITY.md`) for the corresponding source of
  the GPL programs, naming the file above. `publish.yml` attaches `apk-packages.txt` to each published
  image's run summary. Test: the `PHP image settings` CI job asserts the file exists and is non-empty.
- Docs: drop `--ignore-engines` from `developers/cli.mdx:35` and `developers/claude-code.mdx:20`.
- `docs/security/upstream-notice.md`: re-check each finding against upstream's current default branch,
  update the "Status" line with the date; the owner then sends it (#50).
- Dependabot triage: rebase or close #227, #232, #233, #234, #235, #252. Majors that touch the old front
  (`@ant-design/icons` 6 in admin is fine to try; `react-datepicker` 9, `scorm-again` 3) get one PR each;
  `scorm-again` 3 needs the SCORM player regression tests to stay green (H5P/SCORM behaviour unchanged).
  Red PRs that cannot be fixed in under an hour are closed with a comment and an ignore rule with a reason.
- `docs/ROADMAP-TODO.md` notes for items 2, 6, 7, 13 and 14 (see section 10).
- Tests: CI only (docs, config, the new host guard, the image check).

### TD-2 Remove Soketi and Pusher (ADR 0042)

As `leftovers-0-2.md` L0-04, which still matches the code: delete the `soketi` service, the `ws.localhost`
and metrics routes in `api/docker/conf/Caddyfile`, `pusher/pusher-php-server`, `BroadcastServiceProvider`
registration, the three channel files and their `loadRoutesFrom` calls, and `PUSHER_*` from env files;
set the broadcast default to `null` and `BROADCAST_DRIVER=null` in the env files. Update the seven docs
pages (`developers/architecture`, `developers/local-development`, `getting-started/{hosts,quick-start}`,
`operators/{licensing,self-hosting,storage}`) to say the studio and tutor stream over SSE (ADR 0011, 0105).
Tests: full PHPUnit, `php artisan route:list`, `config:cache`, `docker compose config`. Licence guard:
one GPL-free dependency less. Demo: `docker compose up` without the soketi container.

### TD-3 Tenant isolation coverage, two-tenant run, `storage.localhost` audit (ADR 0190)

- New `api/tests/Integrations/TenantIsolationCoverageTest.php`:
  1. Walks `Route::getRoutes()` the same way `TokenScopeMapCoverageTest` does.
  2. For every route behind `auth:api`, issues the request with a token signed by another tenant's key
     pair (the `switchToTenantWithOwnKeys()` technique, moved into a shared trait
     `api/packages/core/tests/Concerns/ForeignTenantToken.php`) with route parameters filled with `1` and an
     empty body, and expects 401. One test covers every authenticated route in one process.
  3. Every route that is not behind `auth:api` must be listed in
     `api/tests/fixtures/public-routes.php` with a reason (webhook with its own signature, public catalogue
     read from the tenant database chosen by host, LTI launch, health). A new public route without a reason
     fails the test.
  4. Routes with signed tokens of their own (LRS, H5P internal, LTI tool) are listed with the test that
     covers them.
- The existing per-package `TenantIsolationTest` files stay (they also check data, not only auth).
- Nightly: a `tenancy` job in `nightly-conformance.yml` that brings up the compose stack and runs
  `api/packages/tenancy/tests/Integration` with `TENANCY_INTEGRATION=1` (two real tenants over HTTP). It also
  covers the "cross-tenant builder check" of `leftovers-0-2.md` L2-04.
- `deploy` CI job: `caddy validate` of `front/docs-site/examples/production/Caddyfile` in both host layouts
  (the same `caddy:2.x-alpine` image `validate.sh` already uses).
- `storage.localhost` audit: list every server-side read of a URL built from `Storage::url`, `AWS_URL` or
  `APP_URL` (course import/export, `topic-types/src/Helpers/Markdown.php`, PDF rendering in `api/pdf`,
  e-mail inlining). Each is changed to read through the disk or the internal endpoint. Guard:
  `api/tests/Integrations/NoPublicStorageFetchTest.php` scans `api/packages/*/src` for `Http::get(`,
  `file_get_contents(` and `fopen(` on values from `Storage::url(` and fails with the file name.
- Tests: the new tests; full PHPUnit. Risk: some routes may answer 404 or 422 before authentication (route
  model binding runs after `auth` in the default middleware order, so 401 should come first; routes where
  it does not are listed and fixed, not allow-listed).

### TD-4 Dependency holds (one PR per hold)

1. sharp 0.35: bump `front/web` and `front/docs-site` to `sharp 0.35.x` (matching Astro 7.3.8's optional
   range); run the docs-site production build in CI (`docs.yml`) and the web build; remove the Dependabot
   ignore. If `MissingSharp` returns on the runner, keep the hold and update its comment with the run link.
2. pro-components: try the newest 2.8.x; if `admin` typecheck passes, remove the ignore; otherwise narrow the
   range to the versions actually tested and link the upstream issue in the comment.
3. ESLint 10 in admin: replace `.eslintrc.js` and `@umijs/fabric` with `admin/eslint.config.mjs` using the
   same packages as `front/web` (`@eslint/js`, `typescript-eslint`, plus `eslint-plugin-react-hooks` which
   admin already gets through fabric); `lint:js` calls `eslint` directly instead of `umi lint`. New direct
   dev dependencies: none beyond those already in the lockfile.
4. Prettier 3 in admin: bump, change the CI step to `require.resolve('prettier/bin/prettier.cjs')`, one
   formatting commit listed in a new `.git-blame-ignore-revs`.
5. Old front (`front/src`): ESLint flat config and Prettier 3 only if Q4 keeps the old front for more than
   one release; Stripe stays held either way (item 11).
- Dependabot: the `eslint`, `@eslint/js` and `prettier` major ignores come off only when admin and the old
  front are both migrated or the old front is retired.

### TD-5 Evals in CI on the fake driver

- New job `evals` in `ci.yml` (path filter: `api/packages/{ai,course-builder,living-course,tutor,learner-insights}/**`):
  prepares the database like the PHPUnit job, then runs the four eval commands with `--fixtures=all` and
  without `--live`. Reports are uploaded as an artifact. A failing check fails the job.
- Replace `coffee-handbook.pdf` with a medium PDF fixture (10–20 pages, generated from the existing
  `coffee-brewing.md` with the `api/pdf` service at fixture-build time and committed under 1 MB) so the spec's
  "medium PDF" case is real; add its cassette.
- A single summary command `ulams:eval {--live} {--max-usd=}` that runs the four and writes one Markdown
  report (`storage/app/evals/<date>.md`) with the table of checks per fixture. The spec asks for "a report";
  today there are four.
- Live runs stay manual (cost); the Phase 4 item keeps "no `--live` run yet".

### TD-6 OpenAPI attributes and `doctrine/annotations` removed (ADR 0047)

- Converter `api/tools/oa-to-attributes.php` (dev only, not autoloaded in production): parses each docblock
  with swagger-php's own annotation factory and writes the matching `#[OA\…]` attributes, removing the
  docblock lines. Files it cannot convert are listed and done by hand.
- Eight batches by package (about 35 files each, one commit each). After every batch:
  `php artisan l5-swagger:generate` and `node front/cli/scripts/sync-spec.mjs storage/api-docs/api-docs.json --check`
  must pass unchanged, and `sdk-php/generate.sh` must produce no diff. This is the snapshot ADR 0047 asked
  for; it already exists.
- Last commit: delete `DocBlockConfigFactory` and its binding, `composer remove doctrine/annotations`, add a
  CI grep that fails on any `@OA\` docblock in `api/packages` or `api/app`.
- Risk: parallel feature branches keep adding docblocks. Mitigation: the CI grep lands in the first commit
  as a ratchet (count may only go down), so new code uses attributes from day one.

### TD-7 Nightly journey E2E and the a11y route coverage

- New job `journey` in `nightly-conformance.yml` (also `workflow_dispatch`): API image with the fake driver,
  PostgreSQL, Valkey, the front built; runs `STUDIO_E2E=1` for `studio.spec.ts` and
  `studio-living-course.spec.ts`. Add one assertion that the published course answers on a tenant host
  (`<slug>.app.localhost` through Caddy), which is the "course live on subdomain" step.
- Order of the two suites is the journey; they stay two files (each is already 400+ lines).
- `front/web/tests/a11y/coverage.spec.ts`: reads the learner routes from `front/web/src/pages` and fails when
  a learner-facing route has no a11y spec (allow-list for admin-only and studio routes, which the studio e2e
  covers with axe).
- The old front gets no new a11y suite (Q4).

### TD-8 Learning-benefit claims register (ADR 0191)

- `docs/experiments/claims.json`: one entry per feature that claims a learning benefit (today: generative UI
  activities, remediations, the adaptive interface, delayed-retention quizzes, simulations), each with
  `feature`, `claim`, `flag`, `experiment` (arms, primary metric `retention_score` with delay 3–7 days,
  secondary metrics, minimum group size) and `status` (`defined`, `running`, `reported`).
- `scripts/check-claims.mjs` (no dependency): fails when a feature flag listed in
  `api/packages/*/config` with `learning_claim: true` (new config key) has no entry, or an entry has no
  primary metric. Runs in the `changes` job.
- Public copy: the landing and docs must not claim a learning benefit for a feature whose status is not
  `reported` (checked by the same script on the docs data files that carry feature status).
- The experiments package itself stays the Phase 2.7 item.

### TD-9 Commerce E2E harness (no commerce code)

- `api/packages/commerce/tests/Support/FakeCommerceProvider.php`: implements `CommerceProvider`, emits signed
  order events (`paid`, `refunded`) and can drop one to simulate a missed webhook.
- `api/packages/commerce/tests/Feature/CommerceJourneyTest.php` with the three scenarios of the quality bar as
  test methods marked skipped with "waits for entitlements (Phase 6.4)", so they show in every run.
- When the Sylius adapter lands, the commerce plan adds a nightly job against a Sylius container that runs the
  same scenarios. The names (`Entitlement`, reconciliation command) follow the commerce plan; this package
  renames them if they differ.

### TD-10 README content standard

- Standard: every package README has the sections "What it does", "Configuration", "Limitations" and
  "API" (endpoints, commands or "None, library only"), the four items the spec lists.
- `scripts/check-readmes.mjs` replaces the shell loop in `ci.yml:72`: checks the four headings in every
  `api/packages/*/README.md` and in each Yarn workspace README. Ratchet: a list of packages not yet compliant
  (`scripts/readmes-pending.txt`) may only shrink.
- Write the missing READMEs: `front/sdk`, `front/ui`, `front/docs-site`; fill `bulk-notifications`,
  `translations`, `permissions`, `cmi5` first, then the rest in batches of about ten packages.

## 6. Dependencies

No new runtime dependency. Development only:

| Dependency | Where | Why | Licence, maintenance, size |
|---|---|---|---|
| `sharp` 0.35.x | `front/web`, `front/docs-site` | Matches Astro 7.3.8's own range, removes the duplicate 0.34 copy | Apache-2.0, active, already installed in 0.35.5 through Astro |
| `prettier` 3.x | `admin` | Ends the major hold; other workspaces can share one version | MIT, active, 3.9.9 already in the lockfile for other workspaces |
| `eslint` 10, `@eslint/js`, `typescript-eslint` 8 | `admin` | Same toolchain as the five flat-config workspaces; drops the deprecated `@umijs/fabric` | MIT, active, already in the lockfile |
| `caddy` image | CI `deploy` job | Validates the docs example Caddyfile | Apache-2.0, already pulled by `validate.sh` |

Removed: `pusher/pusher-php-server`, `doctrine/annotations` (abandoned), `@umijs/fabric`, the Soketi image.

## 7. Tests

Each TD names its tests above. Across all of them: full PHPUnit (all shards), JS typecheck, lint and tests,
`web-a11y`, OpenAPI spec check and `sdk-php` regeneration, nightly conformance. H5P and SCORM behaviour is
unchanged: TD-1's `scorm-again` bump is the only change that touches a player and it needs the SCORM player
tests and one manual SCORM 1.2 and 2004 launch.

## 8. Risks

| Risk | Mitigation |
|---|---|
| The route-walking isolation test is flaky on routes with side effects in middleware | It sends no body and expects 401 before controllers run; routes that do not reject first are bugs, listed in the PR and fixed |
| The OpenAPI conversion conflicts with open feature branches | Ratchet grep first; batches by package so rebases are local; convert packages with no open PR first |
| The nightly journey job is slow or flaky (full stack) | Nightly only, one retry, Playwright trace uploaded; failure opens no PR and blocks nothing |
| sharp 0.35 fails on the runner again | Keep the hold with the run link; the bump is one revertible commit |
| README ratchet becomes busywork | Four headings only; "None, library only" is a valid API section |
| Removing Soketi breaks a hidden listener | No `ShouldBroadcast` class exists; the `null` driver turns any stray `broadcast()` into a no-op |

## 9. Open questions (each with the default that will be used)

1. **Q1 Multitenancy item**: tick "Multitenancy via `gecche/laravel-multidomain`" after TD-3 with a note that
   per-tenant storage credentials are tracked by the SeaweedFS item (Phase 8 plan) and real DNS/TLS by the
   first production deployment item? **Default: yes.**
2. **Q2 Item 13 (separate registrable content domain)**: close with a note "obsolete as worded: the owner chose
   same-site `*.content.ulams.app` (ADR 0014 amended); the separate-domain mode stays documented"? **Default: yes.**
3. **Q3 Item 14**: record the nightly runs as on since 2026-10-09 and keep the item open only for the saLTIre
   operator run (#141)? **Default: yes.**
4. **Q4 Old React front (`front/src`)**: retire it in favour of the Astro front, or keep maintaining it? It
   decides the ESLint 10, Prettier 3 and Stripe holds for `front`. **Default: keep it frozen (security fixes
   only) until a separate retirement plan; its three holds stay as they are, admin moves now.**
5. **Q5 Tenant isolation rule (ADR 0190)**: one route-walking test for authentication isolation plus an
   allow-list with reasons for public routes, instead of a hand-written isolation test per endpoint?
   **Default: yes; per-package data isolation tests stay where they exist.**
6. **Q6 Learning claims (ADR 0191)**: a register in `docs/experiments/claims.json`, a `learning_claim` config
   flag and a CI check, and no public benefit claim before a reported experiment? **Default: yes.**
7. **Q7 Evals in CI**: run the four evals on the fake driver on every PR that touches the AI packages (about
   4 minutes)? **Default: yes, path-filtered.**
8. **Q8 Journey E2E**: nightly only, not per PR? **Default: nightly and on demand.**
9. **Q9 Upstream images in package READMEs**: copy the hot-linked upstream images into the repo (they are
   the owner's code, MIT) instead of dropping them? **Default: copy the ones still accurate, drop the rest.**
10. **Q10 Docs URL**: `docs.ulams.app` does not serve the docs. Point links at
    `https://ulams-dev.github.io/ulams/` through `ULAMS_DOCS_URL` until the owner sets up the subdomain?
    **Default: yes.**
11. **Q11 Source offer**: a written offer plus the package list in the image, rather than publishing source
    tarballs next to each release? **Default: written offer plus package list.**

### Owner-only inputs (cannot be done by an agent)

- Send the upstream security notice (#50) after TD-1 refreshes it.
- Run the saLTIre job once with an operator (#141).
- Accept ADRs 0042, 0047, 0190 and 0191 (currently Proposed).
- Q4 if the default is not wanted: a retirement date for the old front.
- A live eval run needs `ANTHROPIC_API_KEY` in the eval environment and the spend cap (Phase 4 item).

## 10. TODO changes (when each milestone merges)

- TD-1: notes on items 2 (partial list updated), 6, 7 (close, obsolete, #44), 13 (close, obsolete as worded),
  14 (first half done).
- TD-2: tick item 3.
- TD-3: tick item 4 and "Tenant isolation tests for every endpoint"; item 1 per Q1.
- TD-4: notes on items 8, 9, 10; tick the sharp and pro-components parts when the holds come off.
- TD-5: note on "Mocked LLM in tests + eval command"; tick it.
- TD-6: tick item 12.
- TD-7: notes on the journey and WCAG items; tick both when the nightly job has passed three nights in a row.
- TD-8: note on the A/B item (register and check done, package pending in Phase 2.7).
- TD-9: note on the commerce E2E item (harness only).
- TD-10: tick "README per package" when the pending list is empty; until then a note with the count.
- New items marked `(new)` under Quality bar: "(new) Route-walking tenant isolation check (ADR 0190)",
  "(new) Learning-benefit claims register (ADR 0191)", "(new) Nightly journey E2E job".

## 11. Milestones

| Milestone | Content | Demo | Size |
|---|---|---|---|
| **TD-1** | Hygiene: badges and images, docs URL, host guard, source offer and package list, `--ignore-engines` docs, upstream notice refresh, Dependabot triage, TODO notes | CI green with the host guard; `docker run ghcr.io/ulams-dev/php cat /usr/share/doc/ulams/apk-packages.txt` | S |
| **TD-2** | Soketi and Pusher removed | `docker compose up` with no soketi container; route list and config cache pass | S |
| **TD-3** | Route-walking isolation test, public-route allow-list, nightly two-tenant run, Caddyfile validation, `storage.localhost` audit | The coverage test failing on a new unauthenticated route without a reason | M |
| **TD-4** | Dependency holds: sharp, pro-components, admin ESLint 10 and Prettier 3 | `dependabot.yml` with fewer ignores; admin lint on flat config | M |
| **TD-5** | Evals on the fake driver in CI, medium PDF fixture, one summary report | The `evals` job artifact with the report table | S |
| **TD-6** | OpenAPI attributes in eight batches, `doctrine/annotations` removed | Unchanged `front/cli/spec/openapi.json`; `composer.lock` without `doctrine/annotations` | L |
| **TD-7** | Nightly journey E2E, tenant-host assertion, a11y route coverage | A nightly run with both suites and their traces | M |
| **TD-8** | Claims register, `learning_claim` flag, check script | The script failing on a flagged feature without an entry | S |
| **TD-9** | Commerce E2E harness and skipped scenarios | The three skipped scenarios in the PHPUnit output | S |
| **TD-10** | README standard, check script with ratchet, the missing and stub READMEs | The pending list shrinking in CI output | M, can run in parallel |

**Recommended first milestone: TD-1, then TD-2 and TD-3 in parallel.** TD-1 is docs and config only, closes
three TODO items with notes and unblocks the owner's notice (#50); TD-3 turns the most important quality-bar
item into a CI check. TD-6 and TD-10 can run in parallel lanes at any time because they touch different files.
