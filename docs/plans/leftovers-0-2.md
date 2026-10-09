# Plan: Phase 0–2 leftovers

Status: **draft, waiting for the product owner's approval**. Nothing in this plan is implemented.

This plan covers every item that is still open (`- [ ]`) in Phases 0, 1 and 2 of `docs/ROADMAP-TODO.md`
on `main` (`5d7ec700`, 2026-10-09): 26 in Phase 0, 13 in Phase 1 and 34 in Phase 2. For each item it
decides one of: **do** (a work package), **merge** (folded into another item's package), **done**
(already in code; tick it), **obsolete** (close with a note, pending the owner), **owner** (only the
product owner can act) or **moved** (to another phase's plan). Work packages are PR-sized, ordered,
and marked for parallel work.

It is written for implementers who were not in the planning session: every package names its files,
tests, migrations, risks and definition of done. Where a decision needs the product owner, the plan
uses the recommended default and says **"default, pending #N"**; the GitHub issue holds the question.

Related records, all **Proposed** with this plan: ADRs 0040–0056 (section 8). Phase 4 is planned
separately in `docs/plans/phase-4.md` (ADRs 0057–0062).

Facts come from reading `main` and these in-flight branches on 2026-10-09:

| Branch / PR | Relevance |
|---|---|
| `phase-3/living-course` (local, 16 commits) | Contains the Phase 2 **drift check** (`126d2342 feat(course-builder): detect admin edits before re-applying an element`) and splits `SourceIngestor`. Phase 2 work in `course-builder` must start after it merges |
| PR #34 `fix/post-phase-2` | Fixes admin on Node 24, adds web/ui/sdk to CI, scheduler lock, tenant AI settings. Its ADRs are numbered 0030–0037 and collide with `main`; they must be renumbered before merge (not this plan's job) |
| `feat/brand-orbital-folio` (local) | Brand tokens in admin, docs, web. The theme picker (L2-06) must rebase on it |

---

## 1. Rules for every work package

- Branch `leftovers/<package-id>-<short-name>` (e.g. `leftovers/l0-02-postgres-17`), one PR per
  package, Conventional Commits, tests in the same commit, `HUSKY=0` only if hooks cannot run, **no AI
  attribution** anywhere.
- Verify before the PR: the affected PHPUnit suites in the API container (`AGENTS.md`), `corepack yarn
  turbo run typecheck build lint test --filter=<workspace>` for touched workspaces, axe for any
  learner-facing UI.
- Docs rule (`AGENTS.md`): every user-, admin-, developer- or operator-visible change updates the docs
  site (`front/docs-site/src/content/docs/**`) in the same PR.
- Every new endpoint: policy, Swagger (attributes once L0-11 lands, docblocks before), tenant isolation
  test (pattern: `api/packages/example-plugin/tests/Api/TenantIsolationTest.php`).
- Tracker: tick an item only when merged; partial work gets a `(partial: …)` note. Never reword items.
- Definition of done (DoD) below always includes "CI green" and "tracker updated"; only extra
  conditions are listed per package.

---

## 2. Triage of every open item

Legend: **do** → package; **merge** → into the named package; **done** → tick in L0-01; **obsolete** /
**owner** → issue; **moved** → other plan.

### Phase 0 (26 open)

| # | TODO item (short) | Verdict | Package |
|---|---|---|---|
| 0-1 | 0.1 Multitenancy (remaining: video queue, storage credentials, DNS/TLS) | Video queue **done** (`93a9f250`); storage credentials **merge** → L0-03; DNS/TLS **do** | L0-03, L0-18 |
| 0-2 | 0.1 Tests, CI, code style… (baseline failures core 6, auth 3) | **do** | L0-17 |
| 0-3 | 0.1b H5P as isolated Lumi service (resolver in progress) | **done** (ADR 0015), default, pending #44 | L0-01 |
| 0-4 | 0.1b Root README, AGENTS.md, per-package READMEs | **do** (only `api/packages/h5p` lacks a README) | L0-20 |
| 0-5 | 0.1b Documentation site (branch not merged; Pages; vulnerability reporting) | **done** (site on `main`, #13 and #14 closed), default, pending #44 | L0-01 |
| 0-6 | 0.1b Remaining legacy references | **do**; domain choice is **owner** (#24) | L0-15 |
| 0-7 | 0.1b CI (Jest in admin/front, workflows at root) | **do** | L0-17 |
| 0-8 | 0.1b Swagger tracker path, Git LFS, exact pins | Swagger part **done**; LFS **obsolete** (default no LFS, pending #48); pins **do** | L0-17 |
| 0-9 | 0.1c Remove tracker Logs screen and leftovers | **do** | L0-12 |
| 0-10 | 0.1c `Relation::enforceMorphMap` | **do** | L0-10 |
| 0-11 | 0.1c PostgreSQL 12 → 16/17 with dump/restore | **do** (17, default, pending #41) | L0-02 |
| 0-12 | 0.1c Drop Soketi; Reverb after 0.2 | **do**: drop, no Reverb (default, pending #43) | L0-04 |
| 0-13 | 0.1c cmi5 for learners (permission, AU files from storage) | **do**, merged with Phase 1 cmi5 items | L0-09 |
| 0-14 | 0.1c Containers cannot reach `storage.localhost` | **merge** | L0-03 |
| 0-15 | 0.1c Security follow-ups (medium) | **do**, split in three | L0-06, L0-07, L0-08 |
| 0-16 | 0.1c Stripe 3-D Secure, webhook docs, RevenueCat verifier | 3DS + docs **do** (default, pending #47); RevenueCat **obsolete** (default, pending #46) | L0-14 |
| 0-17 | 0.1c Jitsi JaaS signature format, `JITSI_RECORDING_HOSTS` | **do** | L0-14 |
| 0-18 | 0.1c Drop `analyze_enabled`, clean meeting frames | **do** | L0-13 |
| 0-19 | 0.1c Remove the Stripe test key from env examples | **do** + **owner** rolls the key (#49) | L0-14 |
| 0-20 | 0.1c Responsible disclosure upstream | **owner** (#50); draft text in L0-14 | L0-14 |
| 0-21 | 0.1c mjml not on the network, `MJML_API_URL` unset | **do** | L0-05 |
| 0-22 | 0.1c Publish images to GHCR; base image `ulams/php:8.3` with source offers | GHCR **done** (`publish.yml`); `8.3` naming **obsolete** (PHP 8.4); source offer **do** | L0-15 |
| 0-23 | 0.1c Delete `front/src/style/` | **done** (`974776f0`), pending #44 | L0-01 |
| 0-24 | 0.1c Yarn on Node 23 needs `--ignore-engines` | **obsolete** (Node 23 EOL), pending #44 | L0-01 |
| 0-25 | 0.2 `@OA\` docblocks → PHP attributes, drop `doctrine/annotations` | **do** | L0-11 |
| 0-26 | 0.2 Smaller admin and front images (nginx-unprivileged) | **do** (approved 2026-10-09) | L0-16 |

### Phase 1 (13 open)

| # | TODO item (short) | Verdict | Package |
|---|---|---|---|
| 1-1 | Run `fetch-player.sh` in the dev container once | **do**: make it automatic | L1-01 |
| 1-2 | Adapt Path B (partial; admin screen pending) | **merge** with 1-10 | L1-02 |
| 1-3 | LTI Client-Side OIDC (tool), NRPS (platform), per-tool `frame-src` | **do** (three parts) | L1-03, L1-04, L1-05 |
| 1-4 | Run `ulams:lti:rotate-keys --init` for existing tenants | **merge** into the upgrade command | L0-19 |
| 1-5 | Isolated origin / strict CSP (cmi5 pending; CSP report-only) | **merge** | L0-09, L1-05 |
| 1-6 | SCORM/cmi5 per-tenant content origin (cmi5 pending) | **merge** | L0-09 |
| 1-7 | Production content origins on a separate domain; LTI tool origins in `frame-src` | domain is **owner** (#24); `frame-src` **merge** | L1-05, L1-07 |
| 1-8 | Enforce the CSP after a clean week; report collector | **do** | L1-05 |
| 1-9 | Turn on nightly conformance; run saLTIre once | **owner** (existing #23) | — |
| 1-10 | Adapt Path B admin screen | **do** | L1-02 |
| 1-11 | Astro front: H5P plays without a token, no learner state | **do** (BFF, default, pending #51) | L1-06 |
| 1-12 | Production: `H5P_SERVICE_CONFIG_DIR`, export config, `compose.h5p.prod.yml` | **do** (docs and production example) | L1-07 |
| 1-13 | `yarn install` on Node 24 fails in admin postinstall | **done** by PR #34; tick when it merges (#44) | L0-01 |

### Phase 2 (34 open)

| # | TODO item (short) | Verdict | Package |
|---|---|---|---|
| 2-1 | Studio: edit the Course Brief from the panel | **do** | L2-05 |
| 2-2 | Drift check before re-applying (ADR 0010) | **done on `phase-3/living-course`** (`126d2342`); tick when Phase 3 merges | — |
| 2-3 | Vendor the A2UI v0.9 JSON Schemas | **do** | L2-02 |
| 2-4 | Builder operations: FPM pool, Caddy route, prune schedule, Horizon queue | **do** | L2-01 |
| 2-5 | Run the opt-in cross-tenant builder check | **do**: in the nightly workflow | L2-04 |
| 2-6 | Regenerate OpenAPI and SDK path types for the builder | **merge** | L0-11 |
| 2-7 | Normalise `yarn.lock` | **obsolete** as a one-off; becomes a CI check (pending #44) | L0-17 |
| 2-8 | Delete the RichText/GIFT row when a topic is deleted | **do** | L2-03 |
| 2-9 | 2.3 Theme preset + accent, free/paid in the interview | **do** (M2.2) | L2-05, L2-06, L2-07 |
| 2-10 | 2.3 Editable Course Brief | **merge** with 2-1 | L2-05 |
| 2-11 | 2.4 Lessons from the component registry (LiaScript, H5P) | **do** (M2.3) | L2-11, L2-12 |
| 2-12 | 2.4 Metadata: pricing | **merge** | L2-07 |
| 2-13 | 2.4 Tenant provisioning (subdomain, theme, publish, commerce) | **do** (M2.2; default, pending #53) | L2-08, L2-09 |
| 2-14 | 2.5 Global edits through the queued pipeline | **do** (M2.3) | L2-15 |
| 2-15 | 2.6 Full sources panel in the workspace | **do** | L2-10 |
| 2-16 | 2.7 Catalogue: learner layout components | **do** (M2.5) | L2-20 |
| 2-17 | Builder: theme picker, drag-and-drop outline editor | **do** | L2-06, L2-14 |
| 2-18 | Builder: variant comparison | **do** | L2-13 |
| 2-19 | Builder: publish summary with warnings | **do** (v1 in M2.2, v2 in M2.4) | L2-08, L2-18 |
| 2-20 | AI-composed declarative lesson layouts (flag) | **do** (M2.5) | L2-21 |
| 2-21 | Mandatory scaffolding template | **do** (M2.5) | L2-20, L2-21 |
| 2-22 | Four pillars check | **merge** into the pedagogy critic | L2-16 |
| 2-23 | Critics with retry budget, flag to author | **do** (M2.4) | L2-16 |
| 2-24 | Playwright solvability check incl. adversarial actions | **do** (M2.4; default, pending #55) | L2-17 |
| 2-25 | Critique results and iterations in the publish summary | **do** | L2-18 |
| 2-26 | `simulation` component: sandbox, isolated origin, CSP, no network, typed postMessage | **do** (M2.5; default, pending #56) | L2-22 |
| 2-27 | Simulations: solvability, author approval, off by default | **merge** | L2-22 |
| 2-28 | Adaptive interface: density, chunking, navigation, hints | **moved** to Phase 4 M4.8 (default, pending #59) | phase-4.md |
| 2-29 | Remediation components (Feynman, elaborative questions, worked examples) | **moved** to Phase 4 M4.5 (pending #59) | phase-4.md |
| 2-30 | Surveys SUS, UEQ, NASA-TLX + behavioural metrics | **do** inside the experiments package | L2-23 |
| 2-31 | A/B experiments per course, delayed retention | **do** (M2.5; consent default, pending #58) | L2-23 |
| 2-32 | Experiment results for authors; opt-in per tenant; consent | **merge** | L2-23 |
| 2-33 | Component playground with model-facing descriptions | **do** in the docs site (default, pending #57) | L2-19 |
| 2-34 | Evals: component choice, no raw markup, simulation pass rate | **do** | L2-24 |

---

## 3. Work packages: Phase 0

Each package lists: **Items**, **Touches** (folders, for the parallelism check), **Steps**,
**Tests**, **Migrations**, **Risks**, **DoD**, **Depends on**.

### L0-01 Tracker hygiene (docs only)

- Items: 0-3, 0-5, 0-23, 0-24, 1-13 (after #34), notes for 0-1, 0-8, 0-22, 2-2, 2-7, 2-28, 2-29.
- Touches: `docs/ROADMAP-TODO.md` only.
- Steps: after #44 is answered, tick the "done" items with a short evidence note (commit or ADR);
  mark obsolete items `(obsolete: …, #44)` without deleting them; update the multitenancy note
  (video queue fixed); add notes "moved to `docs/plans/phase-4.md` M4.5/M4.8" on 2-28 and 2-29.
- DoD: every Phase 0–2 item has either `[x]`, a package reference or an issue reference.
- Depends on: #44, #46, #59 answers (each line can land as soon as its issue is answered).

### L0-02 PostgreSQL 17 (ADR 0040; default, pending #41)

- Items: 0-11.
- Touches: `api/docker-compose.yml`, `.github/workflows/{ci,nightly-conformance,h5p-integration}.yml`,
  `api/makefile`, `front/docs-site/src/content/docs/{operators,developers,getting-started}/*.mdx`,
  `api/docs/adr/0008-postgresql-as-primary-database.md` (note only).
- Steps:
  1. Replace `postgres:12` with `postgres:17-alpine` in compose (`api/docker-compose.yml:222`) and in
     the four workflow service definitions (`ci.yml:151`, `nightly-conformance.yml:64,135,236`,
     `h5p-integration.yml:23`).
  2. New volume path `./docker/postgres17-data` (the old `postgres-data` volume is never reused by a
     new major version).
  3. `make pg-upgrade` in `api/makefile`: (a) start the old server from the old volume as service
     `postgres12` (profile `upgrade`, image `postgres:12`); (b) `pg_dumpall --globals-only` and
     `pg_dump -Fc` for every database listed by `psql -Atc "select datname from pg_database where not
     datistemplate"`; (c) start `postgres:17` on the new volume; (d) restore globals, then
     `pg_restore --create --exit-on-error` per database; (e) `api/scripts/pg-verify.sh` compares
     `select count(*)` of every table per database between old and new and exits non-zero on any
     difference; (f) print the next steps (`php artisan migrate --force` per tenant through
     `ulams:upgrade`, L0-19).
  4. `operators/backups.mdx`: add "Major-version upgrade" with the same steps for non-compose installs;
     replace every "PostgreSQL 12" mention (list in the exploration: `self-hosting.mdx:129,132`,
     `hosts.mdx:74`, `ci.mdx:54,80`, `architecture.mdx:159`, `local-development.mdx:104`).
- Tests: CI runs the full PHPUnit matrix on 17. A new nightly job `pg-upgrade` seeds a 12 database
  (platform + two demo tenants), runs `make pg-upgrade` and `pg-verify.sh`, then runs the
  `TenantIsolationTest` integration suite against 17.
- Migrations: none. The `searchable_events` view is recreated by restore unchanged.
- Risks: collation changes between glibc versions (alpine uses musl; dump/restore rebuilds indexes,
  so no corrupted indexes); H5P schema created by `api/h5p/src/db/migrate.ts` is in the dump.
- DoD: `make pg-upgrade` verified on a copy of the demo stack; docs updated; nightly job green once.

### L0-03 Object storage: SeaweedFS, internal endpoint, per-tenant credentials (ADR 0041; default, pending #42)

- Items: 0-1 (storage credentials), 0-14; MinIO replacement (new item).
- Touches: `api/docker-compose.yml`, `api/docker/conf/Caddyfile` (storage routes only),
  `api/config/filesystems.php`, `api/packages/tenancy/src/{Support/TenantNaming.php,Services/S3BucketProvisioner.php,config.php}`,
  `api/packages/topic-types/src/{Models/TopicContent/Image.php,Helpers/Markdown.php}`, docs
  `operators/storage.mdx`, `LICENSING.md`.
- Steps:
  1. **Spike (first commit, `test:` only)**: `api/tests/Integration/S3CompatibilityTest.php` (skipped
     unless `S3_COMPAT=1`) covering put/get/delete, path-style URLs, presigned GET, public-read bucket
     policy, multipart upload over 8 MB, `ListObjectsV2` with prefix, copy, and per-identity access
     denial. Run it against MinIO (baseline), SeaweedFS `chrislusf/seaweedfs:3.x` (`weed server -s3`,
     pinned to the latest 3.x tag at implementation) and RustFS. Record results in the ADR. If
     SeaweedFS fails a check that RustFS passes, switch to RustFS and say so in the PR.
  2. Compose: service `storage` running `weed server -dir=/data -s3 -s3.port=8333 -s3.config=/etc/seaweedfs/s3.json`
     with `api/docker/conf/seaweedfs/s3.json` (admin identity from env). Keep the service name
     `minio` as a network alias for one release so old env files keep working. Remove the
     non-existent `./docker/conf/minio` mount.
  3. Caddy: `storage.localhost` → `storage:8333`; drop `minio.localhost` (console). Keep the
     nosniff/SVG headers.
  4. **Internal endpoint**: new config `filesystems.disks.s3.internal_endpoint` (`AWS_INTERNAL_ENDPOINT`,
     default = `AWS_ENDPOINT`). New helper `Ulams\Core\Storage\InternalUrl::read(string $disk, string $path): string`
     that streams via `Storage::disk($disk)->readStream($path)` for s3 disks (never through the public
     URL). `Image.php:70-76` uses `getimagesizefromstring(Storage::disk()->get($path))` instead of
     `getimagesize(<public URL>)`; `Markdown.php:109,120` likewise.
  5. **Per-tenant credentials**: `S3BucketProvisioner` gains `ensureIdentity(string $slug): array{key,secret}`.
     Implementation per backend behind `Contracts\StorageIdentityProvisioner`:
     `SeaweedFsIdentityProvisioner` creates an identity limited to `Read, Write, List, Tagging` on
     bucket `ulams-<slug>` (the equivalent of `weed shell s3.configure -user=<slug> -buckets=ulams-<slug>
     -actions=Read,Write,List,Tagging -apply`). The API container has no `weed` binary and must not
     `docker exec`, so the spike picks one network path and documents it in ADR 0041: the SeaweedFS
     IAM API (`CreateUser`/`CreateAccessKey`/`PutUserPolicy` on the S3 port) if it passes the checks,
     otherwise a write of the identity file on the filer (`/etc/iam/identity.json`) over the filer HTTP
     API. A `NullProvisioner` keeps today's shared keys for external S3 (AWS, R2), where operators
     manage IAM themselves. `TenantNaming::envValues` writes `AWS_ACCESS_KEY_ID` and
     `AWS_SECRET_ACCESS_KEY` per tenant. `ulams:tenant:sync-env --rotate-storage-keys` creates
     identities for existing tenants.
  6. Docs: `operators/storage.mdx` (SeaweedFS default, any S3 works, per-tenant identities, migration
     from MinIO with `rclone sync minio:ulams storage:ulams` per bucket), `LICENSING.md` row.
- Tests: `S3CompatibilityTest` (opt-in), unit tests for `InternalUrl` with `Storage::fake`, Image
  topic creation feature test on an s3 fake disk, provisioner test with a fake identity provisioner,
  tenancy test that tenant env files hold distinct keys.
- Migrations: none (data moves with `rclone`, documented).
- Risks: SeaweedFS identity API differences (spike first); existing dev volumes lose files (document
  `make storage-migrate` using `rclone`); presigned URLs signed for the public host (keep
  `AWS_URL` as the public host and sign with the public endpoint config).
- DoD: demo seed (`make demo-seed`) passes on the new storage including Image topics; per-tenant keys
  cannot read another tenant's bucket (integration test with two tenants).
- Depends on: L0-04 and L0-05 (same compose file, sequential lane A).

### L0-04 Remove Soketi and Pusher (ADR 0042; default, pending #43)

- Items: 0-12.
- Touches: `api/docker-compose.yml` (soketi service), `api/docker/conf/Caddyfile` (`ws.localhost`,
  `metrics.localhost`), `api/composer.json` (`pusher/pusher-php-server`), `api/config/broadcasting.php`,
  `api/app/Providers/BroadcastServiceProvider.php`, `api/config/app.php`, `api/routes/channels.php`,
  `api/packages/{webinar,consultations}/src/channels.php` and their providers, env files
  (`PUSHER_*`), docs pages listed in the exploration (architecture, local-development, hosts,
  quick-start, licensing, self-hosting, dns-and-tls).
- Steps: delete the service and routes; set `BROADCAST_CONNECTION=null` default; remove the provider
  registration and the channel files (and their `loadRoutesFrom` / `Broadcast::channel` calls);
  `composer remove pusher/pusher-php-server`; remove `PUSHER_*` from env examples and CI env files;
  docs say "no WebSocket server; the studio streams over SSE (ADR 0011)".
- Tests: full suite; `php artisan route:list` and `config:cache` succeed.
- Risks: a vendored package calls `broadcast()` somewhere at runtime → with the `null` driver it is a
  no-op; grep for `broadcast(` and `event(new …)` on `ShouldBroadcast` classes (none found).
- DoD: `docker compose up` without soketi; docs updated.

### L0-05 mjml on the network

- Items: 0-21.
- Touches: `api/docker-compose.yml` (mjml service), `api/.env.example`, `api/docker/envs/*.example`,
  `api/packages/templates-email/src/Services/MjmlService.php`, docs `operators/email.mdx` (create if
  missing; coverage check lists it).
- Steps: add `networks: [ulams]` to `mjml`, pin the image tag (`danihodovic/mjml-server:<current
  tag>`), set `LARAVEL_MJML_API_URL=http://mjml:15500/v1` in compose and `MJML_API_URL` in env
  examples; remove `MJML_BINARY_PATH` (the PHP image has no Node). In `MjmlService` replace the silent
  fallback to `BinaryRenderer` with: if neither the API URL nor hosted credentials are configured,
  log a warning once per process and send the plain-text part (`EmailChannel` already has the text
  body) instead of failing. Remove the hosted MJML credentials from `.env.ci.*`.
- Tests: `MjmlServiceTest` for the three configurations (URL, hosted, none → plain text + warning);
  an integration test against the compose `mjml` service in the nightly workflow.
- DoD: a password-reset mail in the dev stack renders HTML (checked in Mailpit/Mailhog).
- Depends on: L0-04 (lane A).

### L0-06 Security: tags and images routes

- Items: 0-15 (a, b).
- Touches: `api/packages/tags`, `api/packages/images`.
- Steps: (a) wrap `api/admin/tags` routes (`tags/src/routes.php:12-20`) in `auth:api`; add
  permission checks (`tag_list` for `uniqueAdmin`/`show`, new constant in the tags permission enum,
  seeded for admin and tutor) and make `TagInsertRequest`/`TagRemoveRequest::authorize()` return
  false instead of throwing for guests. (b) `POST api/images/img`: add `throttle:images.render` and a
  `paths` max of 20 items and max 4096 px per dimension in the request rules; apply the limiter
  regardless of `images.private.rate_limiter_status` for the POST.
- Tests: update `TagsApiTest.php:64,116` (guests get 401); new tests for the throttle and the limits
  in `ImagesTest.php`.
- DoD: OpenAPI annotations updated (security on admin tag routes).

### L0-07 Security: cart, payments, vouchers

- Items: 0-15 (d, e).
- Touches: `api/packages/cart`, `api/packages/payments`, `api/packages/vouchers`.
- Steps:
  1. `PaymentRequest::getAdditionalPaymentParameters()` returns only an allow-list:
     `gateway, payment_method, return_url, email, client_name, client_street, client_postal,
     client_city, client_country, client_company, client_taxid` (adjust to the fields the drivers read;
     list them in the PR).
  2. `ShopService::purchaseProduct` merges server values last (`array_merge($client, $server)`), so
     product price, currency and trial values always win; `PaymentProcessor` ignores
     `parameters['currency']` unless the gateway config allows multiple currencies (it does not).
  3. `has_trial`/refund: set from the product's own trial settings only.
  4. `payProduct`: call `ProductService::productIsBuyableByUser()` (as the cart does) and return 403
     when false.
  5. `CouponService` search (`:50-66`): group each `orWhere` branch in a closure; fix the `active_to`
     branch comparing with `getActiveFrom()`.
- Tests: client-sent `currency`, `has_trial`, `amount` are ignored (`PaymentApiTest`); `payProduct`
  on a non-purchasable product → 403; coupon search with name + type filters returns only matches.
- Risks: the legacy React front may send extra fields; they are now ignored, not rejected.
- DoD: no client field can change price, currency or refund behaviour (tests prove each).

### L0-08 Security: group traversal and debug exposure

- Items: 0-15 (f, g).
- Touches: `api/packages/courses/src/Models/Course.php`, `api/packages/course-access/src/Services/CourseAccessService.php`,
  `api/docker-compose.yml` (one env line), `docker-compose.demo.yml`.
- Steps: replace both `getChildGroups` copies with one breadth-first walk with a visited set and max
  depth 10, in a new `Ulams\Auth\Support\GroupTree::descendants(int $groupId): array` used by both
  (fixes the one-level bug in `CourseAccessService::getChildGroups`). Demo compose: install vendor
  without dev packages (`composer install --no-dev` in the demo image build) so `_ignition` routes do
  not exist; dev compose keeps `APP_DEBUG=true` but documents it as dev-only.
- Tests: cycle (A→B→A) terminates; three-level tree returns all descendants; demo stack smoke test
  `GET /_ignition/health-check` → 404.
- Lane: `courses` is also touched by L2-03; different files, no conflict.

### L0-09 cmi5 for learners on the content origin (ADR 0046)

- Items: 0-13, 1-5 (cmi5 part), 1-6 (cmi5 part), 0-15 (c, `POST api/cmi5/fetch`).
- Touches: `api/packages/cmi5`, `api/packages/lrs`, `api/packages/uploads` (content controller
  mapping), `front/web/src/lib/page-docs.ts`, `front/web/src/pages/learn/[courseId]/[topicId].astro`,
  `front/ui` (`PackageFrame` reuse), docs `creators/cmi5.mdx`.
- Steps:
  1. Permissions: students get `cmi5_read` (seeder pattern `scorm/database/seeders/PermissionTableSeeder.php:17,25`);
     fix `Cmi5Policy::delete` to check `cmi5_delete` (new permission, admin only).
  2. Storage: default `CMI5_DISK` to the tenant's bucket disk (`s3`), like SCORM; command
     `cmi5:move-to-bucket` copies existing `cmi5/<id>/` folders from `local` to the bucket (idempotent).
  3. Launch: `Cmi5Service::getPlayerData` builds the AU URL on the content origin
     (`config('scorm.content_origin')` + `/cmi5/<path>`), the Caddy snippet already proxies `/cmi5/*` to
     `/api/content`.
  4. **Scoped launch token** (ADR 0046): `LrsService::launchParams` stops putting the learner's
     Passport token into the `fetch` URL. New table `lrs_launch_tokens` (id, token_hash, user_id,
     registration, au_id, expires_at, used_at). `POST api/cmi5/fetch?token=<one-time>` exchanges the
     one-time launch token for an **LRS-only** token (HMAC-signed, `lrs:statements` scope, expires
     after `CMI5_SESSION_MINUTES` 120, bound to registration and AU) that the LRS guard accepts and the
     rest of the API rejects. The fetch URL can be used once (cmi5 spec: fetch returns the same token on
     repeat calls within the session; store and return the same LRS token for repeats, error 401 after
     expiry).
  5. Front: `topicDoc` renders cmi5 AUs with `PackageFrame` (launch URL from
     `GET /api/cmi5/player/{auId}` JSON variant), like SCORM; completion through the LRS statements
     handled by the existing listener (`completed`/`passed` → `TopicFinished`, ADR 0018).
- Tests: student can launch, cannot delete; AU URL is on the content origin; launch token single use,
  expiry, wrong AU; LRS token rejected by `auth:api` routes; tenant isolation on launch endpoints;
  Playwright: demo cmi5 AU launches and completes in `front/web`.
- Migrations: `lrs_launch_tokens` (tenant DB); permission seeder.
- Risks: AUs that call `fetch` twice (handled); AUs on a different origin than the LRS need CORS on
  `/api/lrs/*` limited to the content origin.
- DoD: the demo seeder's cmi5 course plays for a student in `front/web`; no Passport token appears
  in any URL (test asserts the launch URL has no `token=` other than the one-time token).

### L0-10 Enforced morph map (ADR 0043)

- Items: 0-10.
- Touches: every package with a polymorphic relation (table in the exploration: `courses`, `topic-types`,
  `topic-type-*`, `liascript`, `lti`, `scorm`, `notifications`, `model-fields`, `tags`, `cart`,
  `shopping-cart`, `payments`, `bookmarks_notes`, `questionnaire`, `consultation-access`, `reports`,
  `tasks`, `templates`, `templates-pdf`, `vouchers`, `permissions` model_type), new migration in
  `api/database/migrations`, `admin/src/services/ulams/enums.ts`, `front/sdk/src/topics.ts`,
  `front/web/src/pages/learn/[courseId]/[topicId].astro`.
- Steps:
  1. `Ulams\Core\Support\MorphMap::register(array $aliases)` collects aliases from each package
     provider (`boot()`); the app provider calls `Relation::enforceMorphMap(MorphMap::all())` after all
     providers booted (`$this->app->booted(...)`).
  2. Aliases are stable snake-case strings: topic contents `topic.rich_text`, `topic.video`,
     `topic.audio`, `topic.image`, `topic.pdf`, `topic.h5p`, `topic.scorm`, `topic.cmi5`,
     `topic.gift_quiz`, `topic.project`, `topic.liascript`, `topic.lti`, `topic.oembed`, …; other models
     `user`, `course`, `product`, `webinar`, `consultation`, `stationary_event`, …. Full list generated
     in the PR from `grep morphTo|morphMany|morphOne|morphToMany` and the `topicable_type` values found
     in the demo databases.
  3. Data migration rewrites every `*_type` column listed in the exploration from the FQCN to the
     alias (chunked updates; down migration reverses it). Spatie `model_has_permissions.model_type` and
     `model_has_roles.model_type` included.
  4. API compatibility: `TopicRepository::registerContentClass` keys by alias; topic resources return
     `topicable_type` as the alias **and** a new `topicable_class` with the FQCN for one release
     (deprecated); request validation accepts both. Admin enums and the SDK switch to aliases; the Astro
     page checks the alias (`topic.liascript`).
  5. Course export/import: exported `content.json` uses aliases; the importer maps old FQCNs.
- Tests: guard test fails if any `*_type` value in a seeded database is not in the map; every
  polymorphic relation round-trips; import of an old export; admin and front typecheck.
- Migrations: data only, in every tenant (through `ulams:upgrade`, L0-19).
- Risks: broad change touching admin, SDK and API together → one PR, run alone (no other package that
  touches models in parallel); external API consumers relying on FQCNs get one release of
  `topicable_class`.
- DoD: `Relation::enforceMorphMap` active; a class rename in a test does not orphan rows.
- Depends on: L0-11 finished (both touch many packages; avoid parallel rebases), L0-19 (to run the
  data migration on tenants).

### L0-11 OpenAPI as PHP attributes; drop `doctrine/annotations` (ADR 0047)

- Items: 0-25, 2-6.
- Touches: every `api/packages/*/src` file with `@OA\` (242 files), `api/app/Http/Controllers/Swagger`,
  `api/app/Support/Swagger/DocBlockConfigFactory.php`, `api/app/Providers/AppServiceProvider.php`,
  `api/config/l5-swagger.php`, `api/composer.json`, `front/sdk/src/generated/openapi.ts`,
  `front/sdk/src/course-builder.ts`, CI.
- Steps:
  1. Baseline: `php artisan l5-swagger:generate`, save `api/tests/fixtures/openapi-baseline.json`
     (normalised: keys sorted).
  2. Converter `api/tools/oa-to-attributes.php` (dev tool, not shipped): uses swagger-php's
     `DocBlockAnnotationFactory` to parse each docblock into annotation objects and prints equivalent
     `#[OA\…(…)]` attributes (named arguments, nested objects), inserting them before the class or
     method and removing the docblock lines. Files it cannot convert are listed and converted by hand.
  3. Convert in batches of packages (one commit each, parallel-safe because files differ): batch 1
     `cart, courses, auth`; batch 2 `topic-type-gift, questionnaire, consultations, tasks`; batch 3
     `dictionaries, course-access, webinar, consultation-access, stationary-events, topic-type-project,
     bookmarks_notes, templates`; batch 4 the rest. After each batch the generated spec must equal the
     baseline (`api/tests/Feature/OpenApiSnapshotTest.php`, normalised compare).
  4. Add the five unscanned packages to `l5-swagger.php` scan paths (`topic-types`, `course-builder`,
     `example-plugin`, `images`, `pencil-spaces`); update the baseline in a separate commit and review
     the diff (new paths only).
  5. Remove `DocBlockConfigFactory` and its binding; `composer remove doctrine/annotations`.
  6. Commit the generated spec? No: keep it generated, but CI uploads `api-docs.json` as an artifact
     and the SDK job regenerates `front/sdk/src/generated/openapi.ts` from it and fails on a diff
     (`yarn workspace @ulams/sdk generate && git diff --exit-code`). Replace the hand-written path types
     in `front/sdk/src/course-builder.ts` with the generated ones (response types stay hand-written
     until responses carry schemas).
- Tests: `OpenApiSnapshotTest`; SDK typecheck.
- Risks: subtle attribute semantics (`ref` vs `$ref`, `allOf`); the snapshot compare catches them.
- DoD: no `@OA\` docblocks left (`grep -r "@OA\\\\" api/packages api/app` empty); `doctrine/annotations`
  absent from `composer.lock`; builder paths typed in the SDK.

### L0-12 Remove tracker leftovers

- Items: 0-9.
- Touches: `admin/config/routes.ts:479-485`, `admin/src/pages/Logs`, `admin/src/components/LogsWidget`,
  `admin/src/pages/Users/User/index.tsx`, `admin/src/services/ulams/tracker.ts`,
  `admin/src/consts/{permissions,packages}.ts`, `admin/src/access.ts`, `admin/src/locales/*`,
  `api/packages/video/src/routes.php:3`, `api/database/seeds/PermissionsSeeder.php`, `api/phpunit.xml`,
  `.github/workflows/ci.yml` (remove `tracker` from the `platform` shard), env files (`TRACKER_ENABLED`).
- Tests: admin typecheck/build; CI shard list valid (the coverage grep must ignore commented suites).
- DoD: `grep -ri tracker admin/src api/packages api/database` returns only historical docs.

### L0-13 Drop `analyze_enabled`; clean stored meeting frames

- Items: 0-18.
- Touches: `api/packages/consultations`, `api/packages/webinar`, `admin/src/pages/{Consultations,Webinars}/form.tsx`,
  `front/sdk/src/generated/openapi.ts` (regenerated).
- Steps: migrations dropping both columns (down re-adds nullable boolean); remove fillable,
  resources, request rules, Swagger, admin form fields. Command `meetings:purge-frames {--dry-run}`
  deletes objects under `consultation/{id}/{term}/{user}/` and `webinar/{id}/{term}/{user}/` frame
  paths (never `…/{id}/images`), run per tenant by `ulams:upgrade` once (with `--dry-run` output
  logged first).
- Tests: purge command on `Storage::fake` keeps `images` prefixes; API resources no longer expose the
  field.

### L0-14 Payment and webhook housekeeping; disclosure draft

- Items: 0-16, 0-17, 0-19, 0-20 (draft only).
- Touches: `api/docker/envs/*`, `api/config/services.php`, `front/src/hooks/usePayment.ts`,
  `api/packages/jitsi`, docs (`admin/payments.mdx`, `admin/webinars.mdx`), new `docs/security/upstream-notice.md`.
- Steps:
  1. Replace the Stripe keys in the six env files with empty values (owner rolls the key, #49).
  2. Remove the unused `STRIPE_WEBHOOK_SECRET` from `services.php`; add
     `PAYMENTS_STRIPE_WEBHOOK_SECRET` to `.env.postgres.prod`; docs page: webhook URL
     `/api/payments-gateways/webhook/stripe`, events to subscribe to, secret.
  3. Legacy front 3DS (default, pending #47): in `usePayment.ts` after `payWithStripe`, if the response
     has `redirect_url`, `window.location.assign(redirect_url)`; otherwise keep the success path.
     Jest test with a mocked response.
  4. Jitsi: verify the `X-Jaas-Signature` format against the JaaS webhook docs (implementer reads the
     current JaaS documentation and records the URL and date in the PR); adjust
     `VerifyJitsiWebhook` if needed; add `JITSI_RECORDING_HOSTS` (default `*.jitsi.net, *.8x8.vc`
     only if the docs confirm these hosts) to env examples and docs.
  5. `docs/security/upstream-notice.md`: affected upstream packages and versions, issue summaries,
     fixes (commit links), recommended actions; the owner sends it (#50).
- Tests: Jest (front), Jitsi middleware tests with a captured real payload if available.

### L0-15 Legacy references, placeholder domain, NOTICE

- Items: 0-6, 0-22 (source offer).
- Touches: comments and docs mentioning `escolalms/php`, `api/docker/release_note.sh` (delete; GHCR
  release notes come from `publish.yml`), `api/packages/images/readme.md`, code using `ulams.app`
  (`admin/src/app.tsx`, `front/src/lib/tenant/resolveApiUrl.ts`, `front/web/src/lib/config.ts`,
  `api/packages/tenancy/src/config.php`, `api/packages/jitsi/config/jitsi.php`,
  `api/packages/lrs/src/Services/LrsService.php`, Swagger examples, `api/init.sh`,
  `api/docker/envs/.env.postgres.prod`), `api/docker/php/NOTICE`.
- Steps: code reads the base domain from config/env only (`ULAMS_BASE_DOMAIN`, default `localhost`);
  docs and examples use `example.com` until #24 decides the real domain; the LRS actor `homePage` uses
  `config('app.url')`; NOTICE gets the written source offer for the GPL CLI tools (text in the PR,
  pointing to the exact Alpine package versions and the upstream source URLs); upstream provenance:
  keep one "Based on EscolaLMS" line in `README.md` and `LICENSING.md` (already present), drop it
  elsewhere; ADR prose stays historical (ADRs are records; add nothing). The SQL view recreation for
  pre-rename databases moves into `ulams:upgrade` (L0-19, step "recreate views").
- Tests: grep guard in CI: `ulams.app` may appear only in `docs/` history and `front/docs-site`
  examples marked as placeholders.
- Depends on: #24 only for the final domain value (not blocking).

### L0-16 nginx-unprivileged images for admin and the legacy front (ADR 0056)

- Items: 0-26.
- Touches: `admin/Dockerfile`, `admin/entrypoint.sh`, `admin/config/php/index.php` (removed),
  `front/Dockerfile`, `front/entrypoint.sh`, `.github/workflows/publish.yml`, docs `operators/images.mdx`.
- Steps: final stage `nginxinc/nginx-unprivileged:1.29-alpine` (pin digest), port 8080, SPA fallback
  `try_files $uri /index.html`, cache headers for hashed assets; runtime settings: entrypoint writes
  `/usr/share/nginx/html/runtime-config.json` from env (`envsubst` of an allow-list of `ULAMS_*`
  variables), the apps fetch it before boot (admin `app.tsx`, front `index.html` script) instead of
  the PHP-injected globals; security headers and the CSP come from the reverse proxy (L1-05).
- Tests: container smoke test in CI (start image, `curl /`, `curl /runtime-config.json`); Playwright
  admin login on the new image in the nightly run.
- DoD: images run as non-root, under 60 MB each (record sizes in the PR).

### L0-17 CI completeness

- Items: 0-2, 0-7, 0-8 (pins), 2-7.
- Touches: `.github/workflows/ci.yml`, `admin/package.json`, `front/package.json`, failing tests in
  `api/packages/core` and `api/packages/auth`, `api/composer.json`.
- Steps: run admin and front Jest in the `js` job (fix the admin Jest baseline breakage noted in
  6.1 first, or quarantine named tests with an issue each); fix or quarantine the 6 `core` and 3
  `auth` baseline failures (each with a reason in `api/phpunit.quarantine.xml`); add
  `corepack yarn install --immutable` to CI; move `davidbadura/faker-markdown-generator` to
  `require-dev` (exact pin kept, it has no releases since); `tzsk/sms` stays `^10.0`; CI step that
  fails on new files over 2 MB (`git diff --name-only origin/main… | xargs du -k` check); replace the
  24 MB and 6.8 MB SCORM mocks with generated minimal SCORM 1.2/2004 zips if their tests allow it
  (default no LFS, pending #48).
- DoD: CI runs every JS and PHP suite with no unexplained quarantine.

### L0-18 Production DNS and TLS for tenants

- Items: 0-1 (DNS/TLS).
- Touches: `front/docs-site/examples/production/Caddyfile`, `front/docs-site/src/content/docs/operators/dns-and-tls.mdx`,
  `api/packages/tenancy` (ask endpoint).
- Steps: the guide (`dns-and-tls.mdx:65-82`) describes an on-demand TLS `ask` check, but planning did
  not confirm a matching route. First `grep -rn "tls-ask\|ask" api/packages/tenancy/src/routes*`; if it
  is missing, add `GET /internal/tls-ask?domain=` in the tenancy package: 200 when the host is the
  platform, a known tenant, its content origin or the H5P host; 404 otherwise; the production
  Caddyfile only calls it over the internal network (not routed publicly). Then add `caddy validate`
  of the example Caddyfile and the ask endpoint tests to CI, and remove `needsReview` from the page.
- Tests: ask endpoint unit tests (tenant host, unknown host, content origin); `caddy validate` job.

### L0-19 `ulams:upgrade` per-tenant upgrade command

- Items: 1-4, plus the re-run steps that several items need (Phase 1 permission re-seed, view
  recreation, cmi5 move, frame purge, morph data migration).
- Touches: `api/packages/tenancy/src/Console/UpgradeCommand.php`, docs `operators/upgrades.mdx`.
- Steps: idempotent ordered steps per tenant and for the platform: `migrate --force`,
  `db:seed --class=PermissionsSeeder`, `ulams:lti:rotate-keys --init`, `ulams:tenant:sync-env`,
  `cmi5:move-to-bucket`, `meetings:purge-frames`, recreate SQL views, package-registered steps
  (`UpgradeSteps::register(name, Closure, since: version)`); a `tenant_upgrade_steps` table records
  what ran, so each step runs once per tenant (`--force-step=` to re-run). Output per tenant with a
  summary; non-zero exit if any tenant failed.
- Tests: two fake tenants, steps run once, failure in one tenant does not stop others, `--dry-run`.
- Migrations: `tenant_upgrade_steps` (platform DB).

### L0-20 READMEs

- Items: 0-4.
- Touches: `api/packages/h5p/README.md` (new); rename `files/readme.md`, `images/readme.md` to
  `README.md`; add a CI check that every `api/packages/*` has a `README.md`.

---

## 4. Work packages: Phase 1

### L1-01 LiaScript player in the dev container

- Items: 1-1. Touches: `api/docker/dev-entrypoint.sh` (or the dev `init.sh`), `api/makefile`.
- Steps: on container start in dev, run `packages/liascript/bin/fetch-player.sh` when
  `resources/player/build/index.html` is missing (the script already checks the SHA-256); `make
  liascript-player` for manual runs. Tick the item when merged.

### L1-02 Adapt Path B admin screen

- Items: 1-2, 1-10.
- Touches: `admin/src/pages/Adapt/{index,editor}.tsx` (new), `admin/src/services/ulams/adapt.ts`,
  `admin/config/routes.ts`, `admin/src/access.ts`, locales; docs `admin/adapt.mdx`.
- Steps: list (title, status `draft|building|built|failed`, built version, last error, linked SCORM),
  create from a JSON upload or pasted JSON, version history with change notes, "Build" (202 → poll
  `GET {id}` every 3 s while `building`), link "Use in a topic" to the SCORM topic form with the built
  package preselected. Hidden when the API returns 404 (flag off). Pattern: `admin/src/pages/LiaScript`.
- Tests: admin Jest for the service; Playwright admin flow in nightly with `ADAPT_SOURCE_ENABLED=true`
  and the `adapt` compose profile.
- DoD: item 1-2 ticked (Path B complete), 1-10 ticked.

### L1-03 LTI NRPS on the platform side

- Items: 1-3 (NRPS).
- Touches: `api/packages/lti/src/{routes.php,Platform/*,Http/Controllers/*}`, tests.
- Steps: Names and Role Provisioning Services 2.0: claim
  `https://purl.imsglobal.org/spec/lti-nrps/claim/namesroleservice` with
  `context_memberships_url` in launches when the tool registration has scope
  `https://purl.imsglobal.org/spec/lti-nrps/scope/contextmembership.readonly`; endpoint
  `GET /api/lti/platform/nrps/{context}` (OAuth2 client-credentials token from the existing token
  endpoint, scope checked) returns `application/vnd.ims.lti-nrps.v2.membershipcontainer+json` with
  members of the course (user id as the LTI `sub` already used in launches, roles mapped, name/email
  only if the registration allows PII; default no PII), paging via `Link` headers (`limit` 100).
- Tests: scope required, other tenant's context 404, paging, PII off by default; nightly conformance
  against Moodle (Moodle as tool consumes NRPS? if not supported, saLTIre checks it).

### L1-04 LTI Client-Side OIDC on the tool side

- Items: 1-3 (client-side OIDC).
- Touches: `api/packages/lti/src/Tool/*`, `api/packages/lti/resources/views/*`.
- Steps: support the LTI Client Side postMessage Storage spec (`lti_storage_target` in the login
  request): when present, the login response renders a small page that stores `state`/`nonce` through
  `postMessage` to the platform frame (`lti.put_data`) and redirects; on launch, a page retrieves them
  (`lti.get_data`) and posts them to `/api/lti/tool/launch/verify` alongside the id_token. Keep the
  current server-side state (`lti_nonces`) as the source of truth: the postMessage value must match a
  stored, unused state. Falls back to the current flow when the platform does not offer storage.
- Tests: unit tests for both flows; Playwright test with a fake platform page that implements
  `lti.put_data`/`get_data`.
- Risk: spec details (origin checks, `lti.capabilities`) — implement against the IMS spec text and
  record the version in the PR.

### L1-05 CSP: report collector, enforcement, tool origins (ADR 0044; default, pending #52)

- Items: 1-3 (`frame-src` per tool), 1-5 (CSP part), 1-7 (`frame-src` part), 1-8.
- Touches: `api/packages/core` (report endpoint), `front/web/src/middleware.ts`, `api/docker/conf/Caddyfile`
  (CSP snippets), `front/docs-site/examples/production/Caddyfile`, docs `operators/security-headers.mdx`.
- Steps:
  1. Collector `POST /api/csp-report` (public, `throttle:60,1` per IP, body ≤ 16 KB, accepts
     `application/csp-report` and `application/reports+json`); stores aggregated rows in
     `csp_reports` (directive, blocked origin (host only), document path, first/last seen, count) — no
     full URLs with query strings; pruned after 30 days; admin read endpoint
     `GET /api/admin/csp-reports` (admin only).
  2. `report-uri /api/csp-report` and `report-to` in all CSP headers.
  3. Front (`front/web`): the CSP header moves from Caddy to Astro middleware so it can include
     `frame-src` with the registered LTI tool origins of the tenant (`GET /api/lti/frame-origins`,
     public per tenant, cached 5 min in the BFF SWR cache) plus content and H5P origins.
  4. Admin: `frame-src 'self' <content origins> https:` (default, pending #52), set by the proxy.
  5. Enforcement switch: env `CSP_ENFORCE` (default false); docs say: enforce after 7 days with no
     unexpected reports; the dev stack enforces by default once this lands, so developers see
     violations early.
- Tests: collector (formats, size limit, aggregation, isolation); middleware unit test (tool origins
  included, cache); Playwright run with enforcement on: learner pages, studio, LTI launch, H5P, SCORM,
  LiaScript, cmi5 produce no reports.
- Depends on: L0-09 (cmi5 on the content origin) and L1-06 (H5P via BFF) so the enforced policy holds.

### L1-06 H5P learner state through the BFF (ADR 0045; default, pending #51)

- Items: 1-11.
- Touches: `front/web/src/pages/h5p/[...path].ts`, `front/web/src/lib/session.ts`, `front/ui/src/elements/h5p.ts`,
  `api/h5p/src/routes/*` (no change expected), `front/sdk/src/h5p.ts`.
- Steps: the proxy adds `Authorization: Bearer <session token>` server-side for an allow-list of
  paths: content user data (`/h5p/contentUserData/*`), finished data (`/h5p/finishedData/*`), the
  embed/play page and its AJAX calls; the frame keeps receiving `token: null`; token refresh happens
  in the BFF session layer as for other calls. Also fix the H5P xAPI progress type bug found during
  planning: `ProgressService::h5p()` types `$event` as `string` while the SDK sends the statement
  object (`courses/src/Services/ProgressService.php:151`); accept an array and store it as JSON.
- Tests: proxy unit tests (header added only for allowed paths, never forwarded to other hosts);
  Playwright: answer half of an H5P activity, reload, state restored; API test for the h5p progress
  endpoint with an object payload.

### L1-07 Production deployment docs for content origins and H5P

- Items: 1-7 (docs), 1-12.
- Touches: `front/docs-site/examples/production/*`, `operators/h5p.mdx`, `operators/content-origin.mdx`.
- Steps: production compose example includes `compose.h5p.prod.yml` settings,
  `H5P_SERVICE_CONFIG_DIR`, a step "run `ulams:h5p:export-config` after creating tenants" (also as an
  `ulams:upgrade` step), the content origin on its own registrable domain with DNS/TLS steps (domain
  values from #24, placeholders until then).

---

## 5. Work packages: Phase 2

All Phase 2 packages that change `api/packages/course-builder` start **after `phase-3/living-course`
merges** (it refactors ingestion and versions). Packages marked *front-only* can start earlier.

### 5.1 Operations and hygiene

#### L2-01 Builder operations

- Items: 2-4. Touches: `api/packages/course-builder/src/UlamsCourseBuilderServiceProvider.php`,
  `api/config/horizon.php`, `api/workers.sh`, `api/docker/conf/supervisor/services/*`,
  `api/docker/conf/Caddyfile`, `api/docker/php/php-fpm.d/` (new pool), production Caddyfile example,
  docs `operators/course-builder.mdx`.
- Steps: schedule `course-builder:prune-events` daily at 03:10 (pattern `UlamsLtiServiceProvider.php:86-88`);
  default `COURSE_BUILDER_QUEUE=builder`, Horizon supervisor `builder` (processes 2, timeout 900),
  tenant workers listen on `builder` (`workers.sh`); second FPM pool `sse` on port 9001 (`pm=static`,
  `pm.max_children=16`, `request_terminate_timeout=35s`) and a Caddy route matching
  `path_regexp ^/api/admin/course-builder/sessions/[^/]+/events$` → the `sse` pool with
  `flush_interval -1`.
- Tests: schedule registered (`schedule:list` test); smoke test that the SSE route works through Caddy
  in the nightly stack.
- Lane: compose/Caddy → lane A after L0-03.

#### L2-02 Vendor the A2UI v0.9 schemas (*front-only*)

- Items: 2-3. Touches: `front/ui/vendor/a2ui/v0.9/*.json` (with `NOTICE`, Apache-2.0),
  `front/ui/src/builder/renderer.ts`, `front/ui/src/schema.ts` (if a draft 2020-12 subset is missing),
  `LICENSING.md`.
- Steps: fetch the v0.9 message schemas at a pinned commit (record URL and SHA in `NOTICE`);
  in dev mode (`import.meta.env.DEV`) and in tests validate every incoming `a2ui-surface` envelope;
  production skips the check. A test fixture of every surface kind the API emits is validated in CI.

#### L2-03 Delete topic contents with their topic

- Items: 2-8. Touches: `api/packages/courses/src/Repositories/TopicRepository.php`, `api/packages/topic-type-gift`.
- Steps: `deleteModel` deletes the topicable through a `TopicContentDeleter` contract (default: call
  `$topic->topicable?->delete()`; GIFT quiz deletes its questions through `GiftQuestionServiceContract`
  unless answers exist, in which case the quiz is kept and only detached — consistent with ADR 0033's
  rule "never delete answered questions"); `updateFromRequest` deletes the previous topicable when the
  type changes, under the same rule. Phase 3's `RemovalPolicy` (if merged first) wraps this.
- Tests: RichText row gone after topic delete; answered GIFT quiz kept; type change cleans up.

#### L2-04 Cross-tenant builder check in the nightly workflow

- Items: 2-5. Touches: `.github/workflows/nightly-conformance.yml`.
- Steps: job `tenancy-integration` that starts the stack, creates two probe tenants
  (`ulams:tenant:create probe-a`, `probe-b`), runs `TENANCY_INTEGRATION=1 phpunit --testsuite tenancy-integration`
  (includes `testCourseBuilderSessionsDoNotCrossTenants`), deletes the tenants. Runs when
  `NIGHTLY_CONFORMANCE=true` (#23).

### 5.2 M2.2 Own site

Goal: after building, the author picks a theme and a price, sees a publish summary, and publishes the
course with its generated landing page to the current site, or (platform admins) to a new site.

#### L2-05 Course Brief v2 and the brief editor

- Items: 2-1, 2-9 (interview part), 2-10.
- Touches: `api/packages/course-builder/resources/schemas/course-brief/v2.json`, `src/Brief/BriefService.php`,
  interview prompt `resources/prompts/interview/v2.md`, `front/web/src/studio/thread.ts`,
  `front/ui/src/builder/*` (`BriefEditor`).
- Steps: brief v2 adds optional `theme {preset, accent}`, `pricing {mode: free|paid, amountMinor,
  currency}`, `site {mode: current|new, slug}`; `BriefService::upgrade()` reads v1 documents as v2
  (no data rewrite). Interview fixed keys gain `pricing` (always) and `theme` (only when the author
  can change the site theme or chose a new site). The brief panel becomes editable: each row opens the
  same catalogue control used in the interview; save calls `PUT …/brief` (exists) and shows the
  "stale stages" notice it already returns.
- Tests: schema upgrade, PUT validation, interview eval check updated (`interview.components`
  includes `pricing`), UI tests (vitest + axe).

#### L2-06 Theme picker and site theme

- Items: 2-9 (theme), 2-17 (theme picker).
- Touches: `front/ui/src/builder/{catalogue,components}.ts` (`ThemePicker`), `front/ui/src/registry.ts`
  (exports `THEMES` and preset metadata), `api/packages/course-builder/src/Apply/*` (theme step),
  manifest regeneration.
- Steps: `ThemePicker` shows live preview cards of every preset (rendering a mini `Hero` + `Prose`
  in the preset's CSS variables) with an accent input; accent contrast is checked with the same AA
  function the front uses (move it to `front/ui/src/theme/contrast.ts` and import it in
  `front/web/src/lib/theme.ts`); an accent failing AA is adjusted and the adjusted value shown. Apply
  writes `theme.theme`/`theme.accent` through the settings service (`AdministrableConfig`) **only** if
  the author has `settings_manage` (admin) or the site is new; otherwise the theme step is skipped
  with a note in the apply summary.
- Tests: component tests; applier test with and without permission.
- Depends on: brand branch merged (rebase on its tokens).

#### L2-07 Pricing through a `CommerceProvider` (ADR 0049; default, pending #54)

- Items: 2-9 (free/paid), 2-12.
- Touches: new `api/packages/commerce` (`Ulams\Commerce`), `api/packages/cart` (adapter uses its
  services), `api/packages/course-builder` (metadata stage, applier step), `front/ui/src/builder`
  (`PriceInput` control), docs `creators/pricing.mdx`.
- Steps:
  1. Contract (the 6.4 shape, kept small):

     ```php
     interface CommerceProvider {
         public function key(): string;                                   // 'wellms', later 'sylius'
         public function syncProduct(SellableRef $ref, Price $price, bool $active): ProductRef;
         public function createCheckout(User $user, ProductRef $product, string $returnUrl): CheckoutRef;
         public function handleOrderEvent(Request $request): ?OrderEvent; // webhooks; null when not supported
     }
     ```

     `SellableRef` (`type: course|bundle`, id), `Price` (`amountMinor`, ISO currency), `ProductRef`
     (`provider`, external id). Bound from config `commerce.provider` (default `wellms`).
  2. `WellmsCartProvider`: `syncProduct` creates or updates a cart `Product` with the course as
     productable through `ProductServiceContract` (no table writes), stores the mapping in
     `commerce_product_links` (sellable type/id, provider, external id); `createCheckout` returns the
     existing cart URL for the front; `handleOrderEvent` returns null (the cart package grants access
     itself today).
  3. Metadata stage adds a suggested price only when the brief says paid and no amount was given
     (light model, cited to nothing; marked "suggested"); the author confirms in the publish summary.
  4. Applier step `commerce` runs after the course is applied: `syncProduct(..., active: false)`;
     publish activates it.
- Tests: contract tests with a fake provider; adapter test against the cart package; applier step;
  tenant isolation for any new endpoint (none planned beyond the builder's).
- Migrations: `commerce_product_links` (tenant DB).
- Risk: the Sylius adapter (6.4) may need more methods; the interface is versioned in the ADR.

#### L2-08 Landing on the site, publish check and publish summary v1

- Items: 2-13 (publish part), 2-19 (v1).
- Touches: `api/packages/course-builder/src/Http/Controllers/CourseBuilderController.php` (publish),
  new `src/Publish/PublishCheck.php`, `api/packages/pages` (public read of course landings),
  `front/web/src/pages/courses/[id].astro`, `front/ui/src/builder` (`PublishSummary`),
  `front/web/src/pages/studio/s/[id]/done.astro`.
- Steps:
  1. `GET sessions/{s}/publish-check` returns `{blocking: [...], warnings: [...], facts: {...}}`.
     Blocking: course not applied, current version not applied, drift conflicts, landing document
     invalid, paid without a confirmed price. Warnings: uncited elements, flagged lessons (grounding),
     theme accent adjusted, lessons over the brief's length, accessibility checks on generated
     Markdown (heading order, empty link text, images without alt, tables without headers), critique
     failures (M2.4).
  2. `POST sessions/{s}/publish` requires no blocking items and `acknowledgedWarnings: true` when
     warnings exist; it activates the landing page (`pages` service), the commerce product and the
     course.
  3. `front/web` course page: if an active page `course-<id>` exists, render its document (validated
     with `validateDocument`) instead of the code-built document; fall back otherwise.
  4. `PublishSummary` component: subdomain/URL, price, theme, checklist with blocking/warnings,
     "Publish" disabled until resolved; text fallback.
- Tests: publish check cases, publish guard, front page uses the landing (Playwright), axe.

#### L2-09 New site for a course (ADR 0048; default, pending #53)

- Items: 2-13 (provisioning).
- Touches: `api/packages/tenancy` (platform endpoint + job), `api/packages/course-builder`
  (export/import of a session), `front/web/src/pages/studio/*` (site choice), docs.
- Steps:
  1. Platform-only endpoint `POST /api/platform/tenants` (permission `tenancy_manage`, platform admins)
     queues `ProvisionTenantJob` around `TenantProvisioner::provision()` and returns a run id;
     `GET /api/platform/tenants/{slug}` reports step progress.
  2. `course-builder:session:export {session} {path}` writes a tar (manifest, brief, current and
     applied versions, sources with raw files and fragments); `course-builder:session:import {path}
     --author-email=` creates the session in the target tenant (new ULIDs mapped; fragment IDs kept,
     they are derived from source position and are re-derived identically).
  3. Flow: the author (platform admin) picks "New site" with a slug → provisioning (progress in the
     studio) → export in the source tenant → import in the new tenant through
     `ProcessTenantCommandRunner` → the author's account is created in the new tenant with an invite
     e-mail → the author continues in the new tenant's studio (apply, theme, publish there).
- Tests: export/import round trip (blueprint and citations identical); provisioning job with fake
  runner; permission tests.
- Risks: long-running provisioning (minutes) — job with progress and retry per step (the provisioner
  already has steps).

### 5.3 M2.3 Richer content

#### L2-10 Sources panel in the workspace

- Items: 2-15. Touches: `api/packages/course-builder` (`GET sessions/{s}/citations` reverse index),
  `front/web/src/studio/workspace.ts`, `front/ui/src/builder` (`SourcesPanel`).
- Steps: endpoint returns per source: section tree with fragment ids and, per fragment, the elements
  citing it; the panel shows sections, a count of citing elements, "uncovered" sections (no
  citations) and jumps to elements; selecting an element highlights its fragments.
- Tests: index endpoint, component tests, isolation.

#### L2-11 Content-type registry and LiaScript lessons (ADR 0050)

- Items: 2-11 (LiaScript).
- Touches: `api/packages/course-builder` (`ContentTypes/*`, blueprint schema `v2`, outline and lesson
  prompts, applier), `api/packages/liascript` (a `LiaScriptServiceContract` interface over the
  existing service).
- Steps:
  1. Blueprint schema `course-blueprint/v2.json`: `contentType` enum `richtext | liascript | h5p`,
     optional lesson `selfChecks` (LiaScript inline questions) and `interaction` (H5P, L2-12).
     `Blueprint::upgrade()` reads v1 as v2 (no change in data). Living Course code uses the same
     helpers; coordinate the schema bump in one commit with its tests.
  2. `ContentTypeRegistry` with `ContentType` implementations (`key`, `enabled()`, output schema
     fragment for the lesson task, `render(Lesson): RenderedTopic`, `topicClass`). The outline task
     may choose `liascript` only when enabled and the lesson has ≥ 2 objectives that benefit from
     self-checks (prompt guidance, author can change it in the outline).
  3. LiaScript rendering is **deterministic** (like GIFT): blocks → LiaScript Markdown; `selfChecks`
     (single/multiple choice, text input) → LiaScript quiz syntax; a "Sources" section with citations.
     The applier creates the document with `LiaScriptServiceContract::create()` and a LiaScript topic.
- Tests: renderer golden files; applier creates a LiaScript topic; re-apply updates the document
  version (LiaScript versions are kept); eval fixture with one LiaScript lesson.

#### L2-12 H5P lessons from an allow-list (ADR 0050)

- Items: 2-11 (H5P).
- Touches: `api/packages/h5p/src/Services/H5PServiceClient.php` (`create()`), `api/packages/course-builder`
  (`ContentTypes/H5p*`), config `course_builder.h5p_libraries`.
- Steps: allow-list of libraries installed platform-wide (checked at runtime through the H5P
  service's library list): `H5P.Blanks` (fill in the blanks), `H5P.DragText`, `H5P.Dialogcards`.
  Per library a small **own** JSON Schema the model fills (e.g. blanks: `{text, blanks:[{answer,
  alternatives}]}`), mapped deterministically to H5P `params`; every interaction cites fragments and
  passes the quiz support check. `H5PServiceClient::create(library, params, metadata)` calls
  `POST /h5p/contents` with the internal token; the applier creates an H5P topic.
- Tests: mapping golden files per library; client test with a fake service; applier test; H5P
  integration workflow creates one content per library.

#### L2-13 Variant comparison

- Items: 2-18. Touches: `api/packages/course-builder` (`PatchService::variants`), `front/ui/src/builder`
  (`VariantComparison`).
- Steps: `POST sessions/{s}/elements/{id}/variants {count: 2|3, instruction}` creates N proposed patch
  versions sharing `variant_group` (new nullable column on versions); the surface shows them side by
  side (word diff against the current element); choosing one approves it and rejects the others in one
  transaction. Cost: N calls, shown before starting.
- Migrations: `course_builder_versions.variant_group` (nullable ULID).

#### L2-14 Outline editor with drag and drop

- Items: 2-17 (outline editor).
- Touches: `front/ui/src/builder` (`OutlineEditor`), `api/packages/course-builder` (`outline.reorder`,
  `outline.move`, `outline.rename`, `outline.add`, `outline.remove` actions → author-origin version).
- Steps: tree with drag handles **and** keyboard alternatives (move up/down/into, WCAG 2.5.7);
  every change creates an author version (no approval needed, as for direct edits); live region
  announcements; citations and objectives move with their nodes; removing a lesson asks for
  confirmation.
- Tests: action validation, version created, keyboard-only test, axe.

#### L2-15 Global edits

- Items: 2-14. Touches: `api/packages/course-builder` (run kind `global`, prompt `global/v1.md`),
  studio chat (scope "whole course").
- Steps: supported instructions `translate` (target language), `change_level`, `change_tone`, free
  text with a confirmation; estimate first (same estimator approach as Phase 3 §8.3); one step per
  lesson (sliding window 4) producing replacements validated by the existing patch validator; one
  proposed version with the whole diff; approve → apply. Questions are re-checked with the quiz support
  check; translation keeps citations (fragments stay in the source language; noted in the UI).
- Tests: cassette-based run, estimate, partial failure retry, version diff.

### 5.4 M2.4 Quality loop

#### L2-16 Critics with a retry budget (ADR 0051)

- Items: 2-22, 2-23.
- Touches: `api/packages/course-builder` (`Quality/*`, prompts `critic-pedagogy/v1.md`,
  `critic-ux/v1.md`, output schemas), config `ai.tasks`.
- Steps: after each lesson is generated (and after grounding), run critics: **pedagogy** (LLM, light:
  objectives covered, difficulty increases, hints do not leak answers, and the four pillars as four
  required booleans with reasons), **grounding** (existing task), **mechanics** (deterministic: one
  correct answer for single choice, options distinct, explanations present, H5P/LiaScript render
  without errors), **visual/UX** (LLM, light, on the structured lesson: redundant or distracting
  elements), **accessibility** (deterministic: headings, alt text, link text, table headers, reading
  order of layout trees). Failures produce issues; the `fix` step calls the lesson patch task with the
  issues; up to `COURSE_BUILDER_REFINE_MAX_ITERATIONS` (2); remaining failures flag the element.
  Table `course_builder_critiques` (id, session_id, version_id, element_id, critic, iteration,
  verdict `pass|fail|skipped`, issues JSON, ai_call_id, created_at).
- Cost: critic budget per session `COURSE_BUILDER_CRITIC_USD` default USD 1 (pending #55); when
  reached, remaining critics are `skipped` and shown.
- Tests: each deterministic critic on fixtures; loop stops at the budget and iteration cap; flags.

#### L2-17 Solvability runner (ADR 0051; default, pending #55)

- Items: 2-24.
- Touches: new `api/solver` (Node 22, TypeScript, Playwright, MIT), `api/packages/course-builder`
  (`Quality/Solvability*`), compose profile `quality`, CI image build.
- Steps: the service exposes `POST /sessions {url, viewport}` → session id; `GET /sessions/{id}/snapshot`
  (accessibility tree, visible text, console errors, postMessage log); `POST /sessions/{id}/actions`
  (`click`, `fill`, `select`, `drag`, `press`, `wait`) ; `DELETE /sessions/{id}`. It only opens URLs on
  the configured content/preview origins (allow-list), blocks all other network, runs Chromium with
  `--disable-dev-shm-usage`, one page per session, 60 s cap. Laravel's `SolveJob` loops snapshot →
  LLM task `solver` (light, structured: next action or `done` with the believed answer) → action, max
  25 steps; then a deterministic **adversarial** script (extreme slider values, empty and invalid input,
  20 rapid clicks, keyboard-only pass) checking: no console errors, no `NaN`/`undefined` visible,
  completion event fires once and only after a correct solution. Results feed the critic loop as
  `mechanics` issues.
- Tests: service unit tests with a fixture page; Laravel loop on a fake runner; nightly job builds the
  service and solves the demo H5P and LiaScript fixtures.
- Self-hosting: optional; without it interactives show "not checked".

#### L2-18 Publish summary v2

- Items: 2-19 (v2), 2-25. Touches: `PublishCheck`, `PublishSummary`.
- Steps: add critique results per element (verdicts, iterations, flags) and solvability status;
  failing critics are warnings, never blocking (author decides).

#### L2-19 Component playground in the docs site (ADR 0054; default, pending #57) (*front-only*)

- Items: 2-33.
- Touches: `front/docs-site/src/pages/catalogue/[component].astro`, `front/ui/catalogue/examples/*.json`.
- Steps: one page per catalogue component (builder and learner): live render from example props,
  props table generated from the JSON Schema, the model-facing description, the text fallback, an
  "invalid props" example showing the fallback, and the axe result (run at build time with jsdom).
  The docs coverage check requires an example for every component.

### 5.5 M2.5 Learner layouts and research

#### L2-20 Learner layout and scaffolding components (*front-only*)

- Items: 2-16, 2-21 (components).
- Touches: `front/ui/src/registry.ts` and component files, manifest.
- Steps: new learner components with schemas, descriptions, fallbacks, tests: `Timeline`,
  `FlipCards`, `CodeBlock` (copy button; "Run" is Phase 7.2 and not shown), `PracticeActivity`
  (scaffolding container with required slots `intro`, `toolbox`, `challenges[]` (each with level 1–3),
  `hints[]` per challenge with tiers `nudge|pointer|near_solution`, `feedback` per answer explaining why,
  `workedSolution` revealed only after an attempt). Existing `Callout`, `Steps`, `ComparisonTable`,
  `H5PFrame`, `LiaScriptLesson` complete the approved set.
- Tests: schema, fallback, keyboard and axe per component; `PracticeActivity` hides the worked
  solution until an attempt event.

#### L2-21 Layout topic type and generated layouts (ADR 0052)

- Items: 2-20, 2-21 (generation).
- Touches: new `api/packages/topic-type-layout` (`Ulams\TopicTypeLayout`), `api/packages/course-builder`
  (lesson `layout` tree, prompt `layout/v1.md`, applier), `front/web/src/lib/page-docs.ts`, legacy
  front (Markdown fallback).
- Steps: topic content model `LayoutTopic` (`document` JSON in the learner catalogue format,
  `schema_version`, `markdown_fallback`), registered like other topic types (morph alias
  `topic.layout`), API returns both; `front/web` renders the document, the legacy front and exports use
  the fallback. Generation behind `COURSE_BUILDER_LAYOUTS_ENABLED` (tenant setting, default off): the
  `layout` task composes a tree only from approved components, every leaf carrying citations and
  objective ids; validated server-side against the manifest; practice activities must use
  `PracticeActivity` (schema enforces the scaffolding slots).
- Tests: topic type CRUD, rendering, fallback, applier, eval check "no raw markup, only approved
  components".

#### L2-22 Simulations (ADR 0053; default, pending #56)

- Items: 2-26, 2-27.
- Touches: `api/packages/course-builder` (element kind `simulation`, prompt `simulation/v1.md`),
  `api/packages/uploads` (content origin path `/simulation/*`), `api/docker/conf/Caddyfile` (content
  origin snippet), `front/ui` (`SimulationFrame`).
- Steps: generated HTML/JS stored as a versioned asset (`simulations/<element>/<version>/index.html`,
  ≤ 200 KB, single file, no external URLs — validated by a parser) and served only from the content
  origin with `Content-Security-Policy: default-src 'none'; script-src 'unsafe-inline'; style-src
  'unsafe-inline'; img-src data:; connect-src 'none'; frame-ancestors <app origins>`. The iframe uses
  `sandbox="allow-scripts"` (no `allow-same-origin`). Typed postMessage API
  `{type: 'ulams-sim:ready'|'progress'|'score'|'event', ...}` validated against a schema in the parent;
  anything else is ignored. Every simulation passes the critic loop and the solvability runner, is shown
  to the author as an element with preview and diff, and is applied only after explicit approval
  (`approved_by` stored). Tenant flag `simulations_enabled`, default false everywhere.
- Tests: CSP headers, sandbox attributes, message validation, approval required, flag off → kind not
  offered to the model.

#### L2-23 Experiments and surveys (ADR 0055; default, pending #58)

- Items: 2-30, 2-31, 2-32. Also used by Phase 4 (remediation holdout).
- Touches: new `api/packages/experiments` (`Ulams\Experiments`), studio pages, learner notices.
- Steps:
  1. Model: `experiments` (course_id, key, status `draft|running|stopped`, arms JSON with weights,
     primary metric `retention_score`, secondary `completion`, `satisfaction`, retention delay days
     3–7, min group 20), `experiment_assignments` (experiment, user, arm, assigned_at; deterministic by
     `hash(user_id, experiment salt)`), `experiment_exposures`, `experiment_outcomes`.
  2. Arms switch content variants: for M2.5 the arm decides between a lesson's static topic and its
     layout/simulation topic (both approved by the author, applied as sibling topics; the learner sees
     one).
  3. Delayed retention quiz: N days after the learner completes the lesson, an in-app + e-mail notice
     links to a short quiz built from the lesson's questions (questions the learner has not seen in that
     lesson's quiz first); score stored as outcome.
  4. Surveys: SUS (10 items), UEQ-S (8 items) and NASA-TLX raw (6 scales) as built-in questionnaires
     using the existing `questionnaire` package where possible; shown at most once per course.
  5. Results page in the studio: per arm means with 95 % bootstrap confidence intervals, sample size,
     "not enough data" below the minimum; no per-learner data.
  6. Consent (pending #58): tenant opt-in; course page notice; learner opt-out (gets control, excluded);
     tenant setting "require consent" asks before assignment.
- Tests: assignment stability, exclusion on opt-out, retention scheduling, statistics on fixtures,
  isolation.

#### L2-24 Evals

- Items: 2-34. Touches: `course-builder:eval`.
- Steps: checks `layout.components_allowed`, `layout.citations`, `practice.scaffolding_complete`,
  `simulation.solvable_rate` (with the runner), `simulation.no_network` (static check), component
  choice for the new surfaces (variant comparison after "give me options", outline editor after an
  outline edit request).

---

## 6. Order and parallelism

Lanes are sets of packages that touch the same files and must run **one after another**. Packages in
different lanes can run in parallel without merge conflicts (they touch different folders); the
tracker file is the only shared file — update it in a final commit of each PR and rebase.

| Lane | Packages (in order) | Shared files |
|---|---|---|
| A: compose and proxy | L0-04 → L0-05 → L0-02 → L0-03 → L2-01 → L0-18 | `api/docker-compose.yml`, `api/docker/conf/Caddyfile`, workflows |
| B: API security | L0-06 ∥ L0-07 ∥ L0-08 | separate packages; parallel inside the lane |
| C: content delivery | L0-09 → L1-06 → L1-05 | content origin, CSP, front learner pages |
| D: LTI | L1-03 ∥ L1-04 | `api/packages/lti` (different subfolders: Platform vs Tool) |
| E: broad API refactors | L0-11 (batches parallel by package) → L0-10 | many packages; run alone |
| F: admin | L0-12 → L1-02 → L0-13 (admin part) → L0-16 | `admin/` |
| G: housekeeping | L0-14, L0-15, L0-17, L0-19, L0-20, L1-01, L1-07 | mostly distinct files; L0-15 after L0-11 (Swagger examples) |
| H: front catalogue | L2-02 ∥ L2-19 ∥ L2-20 | `front/ui`, `front/docs-site` |
| I: builder (after Phase 3 merge) | L2-03 → L2-05 → L2-06 → L2-07 → L2-08 → L2-09 → L2-10 → L2-11 → L2-12 → L2-13 → L2-14 → L2-15 → L2-16 → L2-17 → L2-18 → L2-21 → L2-22 → L2-24 | `api/packages/course-builder`, `front/web/src/studio` |
| J: experiments | L2-23 | new package; needs L2-21 only for the layout arm |
| K: nightly | L2-04 | nightly workflow (coordinate with lane A edits of the same file) |

Suggested waves (each wave's packages in parallel):

1. **Wave 1**: L0-01, L0-06, L0-07, L0-08, L0-12, L0-20, L1-01, L1-03, L1-04, L2-02, L2-04, L0-14,
   L0-04 (lane A start).
2. **Wave 2**: L0-05 → L0-02 (lane A), L0-09, L1-02, L0-13, L0-17, L0-19, L2-19, L2-20.
3. **Wave 3**: L0-03 (lane A), L0-11 batches, L1-06, L0-16, L1-07.
4. **Wave 4**: L0-10 (alone in lane E), L1-05, L0-15, L2-01, L0-18.
5. **Phase 2 builder lane I** starts when `phase-3/living-course` is merged; M2.2 (L2-05…L2-09),
   then M2.3, M2.4, M2.5 in order; L2-23 can start with M2.4.

Critical path: Phase 3 merge → M2.2 → M2.3 → M2.4 → M2.5 (about 18 builder PRs). Phase 0/1 packages
are off the critical path and can be spread across agents.

---

## 7. New dependencies

| Package | Licence | Where | Why | Maintenance and size | Self-hosting impact |
|---|---|---|---|---|---|
| SeaweedFS image `chrislusf/seaweedfs` 3.x (L0-03) | Apache-2.0 | compose (replaces MinIO) | S3-compatible storage with per-identity keys | active since 2014, single Go binary (~60 MB image) | replaces an AGPL, unmaintained image; same footprint |
| `nginxinc/nginx-unprivileged` 1.29 (L0-16) | BSD-2-Clause | admin/front images | static serving without PHP | official NGINX image | smaller images, non-root |
| `playwright` + Chromium (L2-17) | Apache-2.0 | new optional `api/solver` service | headless solvability checks | Microsoft, monthly releases; ~400 MB image | optional compose profile `quality` |
| A2UI v0.9 schemas (L2-02) | Apache-2.0 | vendored JSON in `front/ui` | envelope validation in dev/tests | spec files | none |

Removed: `pusher/pusher-php-server` (L0-04), `doctrine/annotations` (L0-11), Soketi and MinIO images,
the PHP/Apache stage of the admin and legacy front images. Not added: Laravel Reverb (ADR 0042),
Storybook (ADR 0054), Git LFS (#48), `pgvector` (see Phase 4).

---

## 8. Architecture decision records (Proposed)

| ADR | Title | Package |
|---|---|---|
| 0040 | PostgreSQL 17 with a tested dump-and-restore upgrade | L0-02 |
| 0041 | SeaweedFS replaces MinIO; per-tenant S3 identities; server-side reads use the internal endpoint | L0-03 |
| 0042 | No WebSocket server: Soketi and Pusher removed, Reverb only when a feature needs push | L0-04 |
| 0043 | An enforced morph map with stable aliases for polymorphic types | L0-10 |
| 0044 | Content Security Policy: report collector, enforcement, tool origins from the API | L1-05 |
| 0045 | H5P learner state through the BFF; no API token in the browser | L1-06 |
| 0046 | cmi5 on the content origin with a one-time launch token and an LRS-only session token | L0-09 |
| 0047 | OpenAPI as PHP attributes; `doctrine/annotations` removed; spec snapshot test | L0-11 |
| 0048 | Course sites: publish into the current site by default; new sites through provisioning and session transfer | L2-09 |
| 0049 | A `CommerceProvider` interface before Sylius, with a Wellms cart adapter | L2-07 |
| 0050 | Lesson content-type registry: deterministic LiaScript rendering and allow-listed H5P libraries | L2-11, L2-12 |
| 0051 | Critic loop with a retry budget and an isolated Playwright solvability runner | L2-16, L2-17 |
| 0052 | Learner layouts as a Layout topic type rendered from the catalogue | L2-21 |
| 0053 | Simulations: single-file HTML on the content origin, sandboxed, typed postMessage, off by default | L2-22 |
| 0054 | Component playground in the docs site instead of Storybook | L2-19 |
| 0055 | Course experiments with delayed retention, surveys and a tenant-level consent model | L2-23 |
| 0056 | Admin and legacy front served by nginx-unprivileged with runtime JSON config | L0-16 |

---

## 9. Decisions taken (to confirm)

Each line names the default the plan uses and the issue that holds the question.

1. **PostgreSQL 17** (default, pending #41).
2. **SeaweedFS replaces MinIO**, RustFS as fallback after the spike (default, pending #42).
3. **Drop Soketi and Pusher, no Reverb** (default, pending #43).
4. **Nine items done or obsolete** as listed in section 2 (default, pending #44).
5. **RevenueCat verifier obsolete** (default, pending #46).
6. **Small Stripe 3-D Secure fix in the legacy front** plus webhook docs (default, pending #47).
7. **No Git LFS**; large mocks replaced where possible, 2 MB guard (default, pending #48).
8. **Owner actions**: roll the Stripe test key (#49), send the upstream notice (#50), domains (#24),
   nightly conformance (#23).
9. **H5P state through the BFF** (default, pending #51).
10. **CSP split**: exact tool origins in the front, `https:` frames in the admin (default, pending #52).
11. **Course sites**: current site by default, new site for platform admins (default, pending #53).
12. **`CommerceProvider` now with a Wellms cart adapter** (default, pending #54).
13. **Optional solvability runner, critic budget USD 1 per course** (default, pending #55).
14. **Simulations off by default everywhere** (default, pending #56).
15. **Playground in the docs site** (default, pending #57).
16. **Experiments consent model**: tenant opt-in, notice, opt-out, consent switch (default, pending #58).
17. **Adaptive interface and remediation components move to Phase 4** (default, pending #59).
18. **cmi5 one-time launch token** replaces the Passport token in AU URLs (ADR 0046; security fix,
    no product choice).
19. **Morph aliases** with one release of `topicable_class` for API consumers (ADR 0043).
20. **Phase 2 builder work waits for the Phase 3 merge**; front-only packages can start earlier.

---

## 10. TODO changes

`(new)` items this plan adds under Phase 0 (and, for the H5P progress bug, Phase 1) in `docs/ROADMAP-TODO.md`:

- Replace MinIO with SeaweedFS (or RustFS) and give each tenant its own S3 identity (ADR 0041)
- `ulams:upgrade`: one idempotent per-tenant upgrade command (L0-19)
- H5P xAPI progress endpoint rejects statement objects (`ProgressService::h5p()` typed `string`) (L1-06)
- Fix `Cmi5Policy::delete` checking the read permission (L0-09)
- Five packages with `@OA\` annotations are missing from the Swagger scan paths (L0-11)

The plan and issue links are added to the Phase 0, 1 and 2 headers.
