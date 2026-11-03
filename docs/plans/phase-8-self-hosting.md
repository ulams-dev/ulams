# Phase 8: the remaining self-hosting work

Status: proposed (2026-10-21). The owner approved the batch of plans in advance ("All approved"),
so every open question in section 7 is settled by its recommended default unless the owner says
otherwise. Things only the owner can provide are listed separately in section 8.

Related: `docs/plans/phase-8-stack-launcher.md` (the `ulams stack` launcher, ADR 0102),
`docs/plans/leftovers-0-2.md` (L0-02 PostgreSQL, L0-03 storage), ADR 0040 (PostgreSQL 17),
ADR 0041 (SeaweedFS), ADR 0021 (HA reference), ADR 0081 (`ulams:upgrade`), ADR 0092 (VPS),
`docs/plans/security-php-rce-2026-10.md` (PHP advisory; SEC2-SEC4 are handled there, not here).
New ADR drafts with this plan: **0108** (signed images and CVE scanning) and **0109** (Helm chart
next to the stack launcher), both Proposed.

## 1. Goal

A self-hosting operator can install ULAMS on Docker or Kubernetes from signed, scanned images,
on a supported PostgreSQL, with a permissively licensed object store that isolates tenants, can
back it up and restore it with one command each (proved in CI), watch it with standard probes,
metrics and JSON logs, configure it through a first-run wizard, pick any AI provider or none, and
run it without any outbound connection.

## 2. What already exists (not planned again)

Checked on `origin/main` at `4bde7dd1f`.

| Area | Already built | Where |
|---|---|---|
| PostgreSQL 17 | New installs from the launcher, the VPS reference and the docs production example run `postgres:17.11-alpine` / `17-alpine`; owner confirmed 17 in #41 | `front/cli/src/stack/config.ts:36`, `deploy/vps-cloudflare/compose.yml:329`, `front/docs-site/examples/production/docker-compose.yml:268` |
| Dump/restore scripts | `pg_dumpall --globals-only` + `pg_dump -Fc` per database, `restore.sh --remote latest`, backup before upgrade | `deploy/vps-cloudflare/scripts/{backup,restore,upgrade}.sh`, `deploy/mydevil/bin/backup.sh` |
| SeaweedFS | Launcher bundles `chrislusf/seaweedfs:4.37` (`weed mini`) with one admin key and a public-read platform bucket; owner confirmed SeaweedFS in #42 | `front/cli/src/stack/{config,render,kubernetes,runtime}.ts` |
| Server-side image read | Image topic reads through the disk, not the public URL | `api/packages/topic-types/src/Models/TopicContent/Image.php:67-82` |
| SBOM and provenance | BuildKit `sbom: true`, `provenance: mode=max` on all seven images, multi-arch, GHCR public | `.github/workflows/publish.yml` |
| Health endpoints | `/api/health`, `/api/healthcheck` (spatie, DB + optional Redis), `/h5p/health`, pdf `/health`, web and nginx `/healthz`, mjml `/healthz`; image HEALTHCHECKs; launcher startup and readiness probes | `api/routes/api.php:27-28`, `api/app/Providers/HealthCheckProvider.php`, `front/cli/src/stack/kubernetes.ts:21-32`, `operators/monitoring.mdx` |
| Request IDs | `X-Request-Id` taken or created, in the log context | `api/packages/core/src/Http/Middleware/RequestId.php` |
| LLM cost logging | `ai_calls` table (model, tokens, cost, latency) and `ai:usage` | `api/packages/ai` |
| AI disabled | `disabled` driver, `AiClient::enabled()`, 503 `ai_disabled` in the builder, living course, tutor and reflection; tests for the builder and living course | `api/packages/ai`, `course-builder`, `living-course`, `tutor`, `learner-insights` |
| Upgrades | `ulams:upgrade` per tenant with dry run; VPS `upgrade.sh` with rollback | ADR 0081, `deploy/vps-cloudflare/scripts/upgrade.sh` |
| Telemetry | Umami only on `ulams.app` hosts in the web app; Sentry only with a DSN; H5P usage statistics off; LiaScript served locally | `front/ui/src/lib/analytics.ts`, `api/config/sentry.php`, `api/h5p/src/config.ts` |
| Licence | No licence keys or activation: the project is open source without paid tiers | ADR 0099 |
| HA guidance | Roles from one image (web, worker, scheduler, migration job), scheduler minute lock, env files rebuilt from `tenants` | `operators/high-availability.mdx` |

## 3. Scope and TODO items covered

From `docs/ROADMAP-TODO.md`, quoted:

- Phase 0: "Upgrade PostgreSQL 12 (EOL) to 16/17 with a tested dump/restore path" (P8-1).
- Phase 0: "Replace MinIO with SeaweedFS (or RustFS) and give each tenant its own S3 identity (ADR 0041, plan L0-03)" (P8-3).
- Phase 0: "Containers cannot reach `storage.localhost` (it resolves to the container itself); use the internal MinIO endpoint for server-side fetches (e.g. Image topic creation)" (P8-3, finishes the remaining callers).
- Phase 0: "Publish images to GHCR (...)" (publishing is done; P8-2 adds signatures; the GPL source offer stays in L0-15).
- Phase 8: "`docker compose up` and Helm chart with sane defaults" (P8-5).
- Phase 8: "(new) Step-by-step Kubernetes YAML tutorial with tenant-aware manifests and validated examples" (P8-5).
- Phase 8: "Setup wizard (admin, domain, mail, storage, AI provider or none, commerce link)" (P8-7).
- Phase 8: "Safe migrations, stable/LTS channels, backup/restore tested in CI" (backup/restore in P8-6; migration safety check in P8-5; channels in P8-6).
- Phase 8: "Health checks, Prometheus metrics, structured logs, zero-downtime guidance" (P8-4, guidance in P8-5).
- Phase 8: "SBOM, signed images, CVE scanning, hardening guide, ISO 27001 / GDPR audit docs" (P8-2; audit docs P8-10).
- Phase 8: "Any AI provider (OpenAI-compatible, Anthropic, Ollama/vLLM); full function without AI" (P8-8).
- Phase 8: "Air-gapped: no telemetry by default, no external CDNs, offline licence, data residency" (P8-9).

Not in this plan: "One app image + PostgreSQL + optional Redis (DB fallback)" beyond what the Helm
values need (Redis is already optional in the health check; a DB-queue small-install profile is a
separate item), "Commerce as optional profile" (waits for Phase 6.4 Sylius; the wizard only leaves
a slot for it), the Cloudflare R&D spike, MyDevil and the first production deployment, and
"Drop Soketi" (L0-04: the service is gone, `pusher/pusher-php-server` is still in
`api/composer.json`; it stays in the leftovers lane).

## 4. Design per item

### 4.1 PostgreSQL 12 to 17 (P8-1)

Who still runs 12: the developer compose stack (`api/docker-compose.yml:227`, volume
`./docker/postgres-data`), all CI service containers (`ci.yml:285,455`,
`h5p-integration.yml:23`, `nightly-conformance.yml:66,153,279`), and any operator who followed the
older docs (`operators/self-hosting.mdx:141` still lists 12 as current). Nothing deployed by us
runs 12. Code is not version-specific: no extensions, `tsvector` + GIN in the tutor package
(rebuilt by restore), `regconfig` casts, H5P schema per tenant.

1. **Images.** Pin `postgres:17.11-alpine` (same tag as the launcher and VPS) in
   `api/docker-compose.yml` with a new volume `./docker/postgres17-data`, and in the five workflow
   service definitions. One tag in four places is checked by a CI grep (`scripts/check-pinned-images.mjs`,
   new, also used for Valkey and SeaweedFS tags).
2. **One upgrade script for every compose install**, `deploy/scripts/pg-major-upgrade.sh` (POSIX
   sh, shellcheck-clean): inputs are the old data directory or a running old server, and the new
   server. Steps: start `postgres:12` read-only on the old volume (profile `upgrade` in compose);
   `pg_dumpall --globals-only`; list databases (`select datname from pg_database where not
   datistemplate`); `pg_dump -Fc` each; start 17 on the new volume; restore globals, then
   `pg_restore --create --exit-on-error` per database; run `deploy/scripts/pg-verify.sh`
   (row counts per table, sequence values, index count, `h5p` schema present per tenant; non-zero
   exit on any difference); print the follow-up (`php artisan ulams:upgrade`). The old volume is
   never modified or deleted. `api/makefile` gets `pg-upgrade` calling it. The VPS scripts already
   dump the same way; they get a `--major-upgrade` flag on `restore.sh` that restores into a fresh
   17 volume (only needed by someone who ran a 12 VPS by hand).
3. **Logical-replication path (documented, for large installs that cannot take the dump window).**
   `operators/upgrades.mdx` "Upgrade PostgreSQL with logical replication": `wal_level=logical` on 12,
   schema via `pg_dump --schema-only`, one publication `FOR ALL TABLES` and one subscription per
   database (platform plus every `ulams_<slug>`), `max_replication_slots`/`max_wal_senders` sized
   to the tenant count, `REPLICA IDENTITY FULL` for tables without a primary key (the script lists
   them), maintenance mode, sequence copy (`setval` script; logical replication does not carry
   sequences), switch `DB_HOST`, `ulams:upgrade`. A helper `deploy/scripts/pg-logical-upgrade.sh`
   prints and runs these steps per database. Tested once a week in the nightly job (4.1 tests),
   not on every PR.
4. **Docs.** Replace every "PostgreSQL 12" mention (self-hosting, hosts, ci, architecture,
   local-development pages), add "Major-version upgrade" to `operators/backups.mdx` and
   `operators/upgrades.mdx`, note in `api/docs/adr/0008`. ADR 0040 status to Accepted (decided in #41).
5. **Follow-up allowed after 17 everywhere:** `api/h5p/src/db/migrate.ts:48` can use
   `NULLS NOT DISTINCT` instead of the `''` sentinel; left as is (no behaviour gain, data migration
   risk).

### 4.2 Supply chain: SBOM, signing, scanning, hardening guide (P8-2, ADR 0108)

- **Signing.** `publish.yml`: `permissions: id-token: write`, `sigstore/cosign-installer`
  (pinned SHA), after each `build-push-action` run `cosign sign --yes $IMAGE@$DIGEST`, and
  `cosign attest --type spdxjson` with the SBOM pulled from the BuildKit attestation
  (`docker buildx imagetools inspect --format '{{ json .SBOM }}'`). Signed by digest, so every tag
  of that digest is covered.
- **SBOM per release.** New `release.yml` on `v*` tags: export the SPDX SBOM of each image,
  `sha256sum` them, attach to the GitHub release (`gh release upload`). Also an SBOM of the
  source tree (`trivy fs --format spdx-json`) for people who build their own images.
- **Scanning (Trivy, Apache-2.0).** `ci.yml` job `security-scan`: `trivy fs` (composer.lock,
  yarn.lock, Dockerfiles, Compose, later the Helm chart) failing on fixable CRITICAL/HIGH, SARIF to
  code scanning; `trivy image` on the images CI already builds (`php-image`, `images` jobs) failing
  on fixable CRITICAL. `nightly-security.yml` (new, daily): `trivy image` on every
  `ghcr.io/ulams-dev/*:latest`, opens or updates one issue per image labelled `security` (same
  pattern as the PHP freshness workflow in SEC4). Trivy DB cached with `actions/cache`.
- **Exceptions.** `.trivyignore.yaml` with `id`, `paths`, `statement`, `expired_at`; CI fails on an
  expired entry.
- **Patch policy** in `SECURITY.md` ("Supported versions" + "Patch policy") and the hardening
  guide: fixable CRITICAL in a published image within 2 days, HIGH within 14, the rest with the
  next release; base images pulled fresh on every publish (`pull: true`, already the case for php).
- **Hardening guide**, new `operators/hardening.mdx`: verify images (`cosign verify
  ghcr.io/ulams-dev/api@sha256:... --certificate-identity-regexp
  '^https://github.com/ulams-dev/ulams/.github/workflows/publish.yml@' --certificate-oidc-issuer
  https://token.actions.githubusercontent.com`), pin by digest, TLS (Caddy ACME or cert-manager),
  secrets (generated by the launcher, file mode 0600, Kubernetes Secrets with encryption at rest,
  rotation of `APP_KEY` is not supported, Passport keys can be rotated), network exposure (only the
  gateway public; Postgres, Valkey, S3 admin, Horizon, metrics internal), NetworkPolicies (from the
  chart), least privilege (non-root images, read-only root FS where it works, per-tenant DB roles
  and S3 identities), backups (encrypted, off-site, restore drill), upload scanning (ClamAV
  profile), PHP ini notes from the advisory. Links from `operators/security.mdx`.
- **Not added:** CodeQL (no PHP support; JS/TS covered by existing lint and tests; can be switched on
  later at no cost for a public repo), Grype (overlaps Trivy), OpenSSF Scorecard (optional later).

### 4.3 Object storage: SeaweedFS with per-tenant identities (P8-3, ADR 0041)

**Comparison** (re-checked for this plan; the owner already chose SeaweedFS in #42):

| | SeaweedFS | RustFS | MinIO community | Garage |
|---|---|---|---|---|
| Licence | Apache-2.0 | Apache-2.0 | AGPL-3.0 | AGPL-3.0 |
| Maturity, maintenance | Since 2015, frequent releases, 4.x current, large user base | Started 2024, fast-moving, pre-1.0 maturity | Upstream stopped community binaries/images in 2025; we run `bitnamilegacy/minio:latest` (unmaintained) | Active, small team |
| S3 API we use (below) | Covered in the launcher's real-API checks for put/get/list/policy; ACLs and IAM to be confirmed by the spike | Claims MinIO compatibility incl. admin API; to be confirmed by the spike | Baseline | Partial (no bucket policies) |
| Per-identity limits | Identities with actions per bucket (`s3.configure`), embedded IAM API on the S3 port | MinIO-style users and policies | Users and policies | Keys per bucket |
| Footprint | One container (`weed mini`), Go | One container, Rust | One container | One container |
| Risk for us | Identity API differs from AWS IAM | Young project | Licence and no images | Licence |

Recommendation: **SeaweedFS**, RustFS as the recorded fallback if the spike shows SeaweedFS failing
a check that RustFS passes. MinIO and Garage are out on licence grounds (AGPL in the default
distribution).

**S3 operations the code uses** (inventory for the spike): PutObject with `x-amz-acl: public-read`,
Content-Type and Content-Disposition (`ActiveContentSafeS3Adapter`, visibility public by default),
multipart over 16 MB (SDK `upload()`, H5P `lib-storage`), GetObject, streamed reads, HeadObject,
DeleteObject, DeleteObjects, CopyObject (copy, move), ListObjectsV2 with prefix, PutObjectAcl
(`video/src/Jobs/ProcessVideo.php:184`), HeadBucket, CreateBucket, PutBucketPolicy, DeleteBucket.
No presigned URLs, CORS or tagging.

Steps:

1. **Spike first** (`test:` commit): `api/tests/Integration/S3CompatibilityTest.php`, skipped
   unless `S3_COMPAT=1`, covering every operation above plus path-style URLs, a public-read policy
   seen anonymously, ACL accepted or ignored without error, and denial of another tenant's bucket
   for a limited identity. A script `deploy/scripts/s3-compat.sh` runs it against SeaweedFS 4.37
   (`weed mini` and `weed server -s3 -s3.config`), RustFS and, for the baseline, MinIO. Results
   recorded in ADR 0041, which then moves to Accepted.
2. **Identity contract** in `api/packages/tenancy`: `Contracts/StorageIdentityProvisioner`
   (`ensure(string $slug, string $bucket): StorageCredentials`, `revoke(string $slug)`), with
   `SeaweedFsIdentityProvisioner` (embedded IAM `CreateUser` / `CreateAccessKey` /
   `PutUserPolicy` over HTTP with the admin key; if the spike shows the IAM API is not usable, the
   filer identity file over the filer HTTP API, decided in the spike), `NullStorageIdentityProvisioner`
   (shared keys, for AWS, R2 and other external S3 where operators manage IAM). Selected by
   `TENANCY_S3_IDENTITY_DRIVER=seaweedfs|none`, default `none`; the launcher, dev compose and the
   chart set `seaweedfs`.
3. **Persist per-tenant keys** so replicas can rebuild env files: migration in the tenancy package
   adding `storage_key` and `storage_secret` (encrypted cast) to `tenants`. `TenantProvisioner`
   calls the identity provisioner after `S3BucketProvisioner::ensure`; `TenantNaming::envValues`
   writes `AWS_ACCESS_KEY_ID`/`AWS_SECRET_ACCESS_KEY` when present (H5P already reads them from the
   tenant env, `EnvFileTenantResolver.ts:224-271`). Tenant deletion revokes the identity.
   `ulams:tenant:sync-env --rotate-storage-keys` creates identities for existing tenants and
   rotates on request. Through domain services only, never direct table writes from outside.
4. **Internal reads, remaining callers.** `filesystems.disks.s3.internal_endpoint`
   (`AWS_INTERNAL_ENDPOINT`, default `AWS_ENDPOINT`) is not needed when every server read goes
   through the disk; instead fix the callers that assume a local path or fetch the public URL:
   `topic-types/src/Helpers/Markdown.php:109,120`, `TopicContent/PDF.php:68`,
   `TopicContent/H5P.php:91`, `images/src/Services/ImagesService.php:49-66`,
   `laravel-scorm/src/Manager/ScormDisk.php:49`, `CourseRepository.php:331`,
   `TopicRepository.php:382`. One helper `Ulams\Core\Storage\LocalCopy::with($disk, $path, fn)`
   that downloads to a temp file for libraries that need a path, and deletes it after.
5. **Compose and Caddy.** `api/docker-compose.yml`: service `storage` running
   `chrislusf/seaweedfs:4.37` (same tag as the launcher) with network alias `minio` for one release,
   remove the dead `./docker/conf/minio` mount; `api/docker/conf/Caddyfile`: `storage.localhost` to
   `storage:8333`, drop `minio.localhost`. Same switch in `deploy/vps-cloudflare/compose.local.yml`,
   `install.sh --local`, `api/h5p/compose.h5p.yml`, `h5p-integration.yml`, the docs production
   example (profile renamed `storage`), and H5P defaults in `api/h5p/src/config.ts:229,232`.
6. **Migration of existing data.** `deploy/scripts/storage-migrate.sh` (rclone
   `rclone/rclone:1.75.2`, already used by the VPS scripts): sync every `ulams*` bucket from the old
   MinIO to SeaweedFS, re-apply bucket policies through `S3BucketProvisioner`, then
   `--rotate-storage-keys`. `make storage-migrate` wraps it.
7. **Docs.** `operators/storage.mdx` rewrite (SeaweedFS default, any S3 works, per-tenant
   identities, migration), `LICENSING.md`, the MinIO mentions listed in the exploration
   (architecture, hosts, quick-start, local-development, self-hosting, requirements, vps-cloudflare).

### 4.4 Health checks, Prometheus metrics, structured logs (P8-4)

- **Liveness vs readiness.** Liveness: Laravel's built-in `/up` (`bootstrap/app.php` `health:`
  route; no dependency checks) for php-fpm through Caddy. Readiness: `/api/health` as today.
  Schedule `health:check` every minute in the platform scheduler so `/api/health` is fresh outside
  Docker HEALTHCHECK too. New checks in `HealthCheckProvider`: queue heartbeat (a scheduled job
  writes a cache timestamp; stale after 5 minutes), scheduler heartbeat, S3 (HeadBucket on the
  platform bucket), PDF and MJML reachability (warning, not failure). Horizon check enabled when
  Horizon runs. Fix the VPS admin healthcheck to probe `/healthz`.
- **Metrics.** No new PHP dependency. `GET /metrics` on the platform API only, served only when the
  request comes from `METRICS_ALLOWED_CIDRS` (default private ranges) or carries
  `METRICS_TOKEN`; Caddy does not route it on public hosts. Gauges computed at scrape time in
  Prometheus text format: queue length per queue and tenant (Redis `LLEN` or `jobs` count),
  failed jobs, oldest pending job age, tenants by status, scheduler and queue heartbeat age,
  `ai_calls` tokens and cost per model and task over the last hour and day, DB connections
  (`pg_stat_activity`), storage health. Request rates, latencies and status codes come from Caddy's
  built-in Prometheus metrics (`metrics` directive) per upstream, so no per-request counter storage
  is needed in PHP-FPM. H5P and PDF get a `/metrics` with `prom-client` (Apache-2.0, already a
  common Node dependency, about 100 kB) for event-loop lag and request histograms.
- **Structured logs.** New `stderr_json` channel in `api/config/logging.php` with Monolog's
  `JsonFormatter`; images, the launcher, the VPS compose and the chart set `LOG_CHANNEL=stderr_json`
  (the file channel stays the default for a bare checkout). Context on every line: request id,
  tenant host, user id (no e-mail), route; Laravel `Context` carries the request id into queued
  jobs. The API passes `X-Request-Id` to H5P and PDF calls; their pino logs include it.
- **Docs.** `operators/monitoring.mdx`: probes table split into liveness and readiness, metrics
  list, a sample Prometheus scrape config and Grafana dashboard JSON under `deploy/observability/`.

### 4.5 Helm chart, Kubernetes tutorial, zero-downtime guidance (P8-5, ADR 0109)

- **Chart** `deploy/helm/ulams/` (`Chart.yaml`, `values.yaml`, `values.schema.json`, templates):
  Deployments `api-web` (php-fpm + Caddy sidecar), `api-worker`, `api-scheduler` (one replica,
  minute lock allows more), `h5p`, `pdf`, `mjml`, `web`, `admin`; a pre-install/pre-upgrade Job
  running `php artisan migrate --force && php artisan ulams:upgrade`; Services; Ingress (class and
  annotations as values) or Gateway API HTTPRoute; optional cert-manager `Certificate` for the
  wildcard host families; ConfigMap for `LARAVEL_*`; Secrets generated once (`lookup` keeps them on
  upgrade) or taken from `existingSecret`; HPA for web and workers (CPU, off by default);
  PodDisruptionBudgets; liveness/readiness/startup probes from 4.4; `securityContext` with
  `runAsNonRoot`, dropped capabilities, `seccompProfile: RuntimeDefault`; NetworkPolicies (only
  the gateway reachable from outside, databases only from the app); optional ServiceMonitor.
  Bundled `postgresql`, `valkey`, `seaweedfs` single-replica StatefulSets (our templates, no
  Bitnami subcharts) with `enabled: true` for small clusters; `external*` values for managed
  PostgreSQL / CloudNativePG and external S3.
- **H5P shared files**: one H5P replica by default with an RWO volume; `h5p.replicas > 1` requires
  `h5p.libraries.storageClass` with ReadWriteMany (schema-validated).
- **Relation to the launcher**: the launcher keeps generating its own manifests for trials;
  `front/cli/test/stack-helm-parity.test.ts` renders both (launcher render plus `helm template`
  with matching values, only when `helm` is on PATH in CI) and compares image references,
  environment keys, ports and probe paths. Docs: "Try it" = launcher, "Run it in production" = chart.
- **Kubernetes tutorial** `operators/kubernetes.mdx`: step by step (namespace, secrets, values,
  install, DNS and certificates, first tenant with `ulams:tenant:create` as a Job, upgrade,
  backup), examples generated by `scripts/render-k8s-examples.mjs` from `helm template` into
  `front/docs-site/examples/kubernetes/`, validated in CI with `kubeconform` (Apache-2.0).
  The "Coming" section of `operators/high-availability.mdx` links to it.
- **Zero downtime**: rolling updates with `maxUnavailable: 0`, `preStop` sleep for php-fpm and
  Caddy, `queue:work` and Horizon handle SIGTERM within `terminationGracePeriodSeconds` above the
  longest job timeout, migration Job before rollout. **Expand/contract rule** for migrations
  written into `CONTRIBUTING`/developer docs, enforced by `scripts/check-migrations.mjs` in CI:
  a migration that drops or renames a column or table fails unless it carries a
  `// contract: <release>` marker and the column was unused in the previous release.
- **CI**: `helm lint`, `kubeconform`, Trivy config scan of the chart, and a kind job (nightly,
  `chart-testing` install with bundled services, then a tenant create and a health check).
  Publish: `helm package` + `helm push oci://ghcr.io/ulams-dev/charts` on `v*` tags, signed with
  cosign.

### 4.6 Backup and restore commands, tested in CI; release channels (P8-6)

- **CLI**: `ulams stack backup [--to <dir>|--remote <rclone-remote>]` and
  `ulams stack restore <archive> [--restore-secrets]` for launcher installs (Docker and
  Kubernetes): globals plus `pg_dump -Fc` per database via `docker compose exec` / `kubectl exec`,
  every `ulams*` bucket via rclone in a one-shot container, the install directory (rendered config,
  generated secrets encrypted with a passphrase using the AES-256 `openssl enc` approach of the
  VPS scripts, no new tool), a manifest with versions and checksums. Restore
  refuses a backup from a newer release and runs `ulams:upgrade` after. Registered in the CLI
  registry with MCP disabled, like the other host-management commands (ADR 0102).
- **VPS scripts** stay; they gain SeaweedFS bucket backup when the local profile is used.
- **CI**: `nightly-ops.yml` (new, nightly and on changes to `deploy/**` or `front/cli/src/stack/**`):
  launcher `stack up` (Docker, development), create content (a course, an uploaded image, an H5P
  package, a learner with progress), `stack backup`, `stack down --volumes`, `stack restore`, then
  assert counts, file fetch over the storage host, H5P state and learner progress. The same
  workflow runs the PG 12 to 17 upgrade test from 4.1.
- **Release channels**: semantic versions `vX.Y.Z` for the whole stack (images, chart, CLI share
  the version), `CHANGELOG.md` generated from Conventional Commits by `release-please`
  (Apache-2.0, a GitHub Action, no runtime dependency); image tags `X.Y.Z`, `X.Y`, `stable`
  (latest release) and `lts` (the LTS line, once declared); `latest` keeps meaning `main`.
  LTS: one minor per year, security fixes for 12 months. `SECURITY.md` "Supported versions" lists
  them. The first `v0.x` release is cut by the owner (section 8).

### 4.7 Setup wizard (P8-7)

Two stages, both writing through existing services:

1. **Install-time (CLI).** `ulams stack up` and `npx create-ulams` prompt for missing values when
   run in a TTY (flags still win, `--yes` keeps it non-interactive): domain, admin e-mail, mail
   (SMTP host/port/user/password/from, or `log` for trials, with a test send), storage (bundled
   SeaweedFS or external S3 endpoint/keys with a write/read/delete probe), AI provider (none,
   Anthropic, OpenAI-compatible URL + key + model, Ollama URL; a one-token test call), commerce
   (none for now; "link Sylius" appears when the commerce profile exists). Uses the existing
   `zod` schema; prompts with Node's `readline/promises`, no new dependency.
2. **First run (admin).** A platform "Setup" checklist page in `admin/` shown to the platform
   admin until dismissed: steps for mail, AI provider, storage status (read-only: changing storage
   after content exists needs a migration), first academy, commerce link (slot). Mail and AI
   settings become runtime-editable through `AdministrableConfig` (ADR 0005), stored encrypted,
   env values remain the override and the source for tenants (ADR 0063 inheritance unchanged).
   Each step has a "Test" action (send a test mail, which the templates package already provides;
   a 1-token AI call logged in `ai_calls`). A fresh install without an admin is protected by a
   one-time setup token printed by the installer (hashed in settings, 24 h validity), so nobody
   else can claim the instance.

### 4.8 Any AI provider, and full function without AI (P8-8)

- **Driver** `OpenAiCompatibleDriver` in `api/packages/ai/src/Drivers` on Laravel's HTTP client
  (no new dependency), against `/v1/chat/completions`: structured output via `response_format:
  json_schema` when the profile declares support, else JSON mode plus our `JsonSchemaValidator`
  and one repair retry; tool calls mapped to our `LlmRequest`; streaming for the tutor SSE path;
  usage mapped to `Usage`. Covers OpenAI, vLLM, Ollama (`/v1`), LiteLLM and most gateways.
  Anthropic stays on its SDK driver. Config: `AI_DRIVER=openai_compatible`,
  `AI_OPENAI_BASE_URL`, `AI_OPENAI_API_KEY`, profiles map to model names (no model names in code),
  per-profile capability flags (`json_schema`, `tools`, `context_tokens`).
- **Prices** configurable: `ai.prices.<model>` (input/output/cache per million tokens) with the
  Claude table as shipped defaults; unknown model logs cost 0 and a warning; local models price 0.
- **Evals**: `ai:eval` gains `--driver`; a nightly opt-in job runs the golden fixtures against a
  small Ollama model on the runner (CPU) to check schema validity and citation coverage only;
  quality numbers go to a report, not a gate.
- **Docs**: `operators/ai-providers.mdx` with recommended models per task (course builder, tutor,
  critics, insights), known trade-offs of small local models, and the per-tenant override.
- **Without AI**: a PHPUnit feature suite `tests/Feature/NoAiTest.php` with `AI_DRIVER=disabled`
  covering manual course authoring, enrolment, quiz attempt, certificate, tutor returns
  `ai_disabled` and the UI flag, learner insights rule-based nudges still produced, living course
  shows source diffs without AI proposals; plus a Playwright run of the web E2E happy path with
  AI disabled (skipping the builder chat steps). Docs list what each feature does without AI.
- An ADR for the driver is written in the milestone (number from the 0110-0119 range) if the
  structured-output mapping needs a decision; otherwise ADR 0009 already foresees it.

### 4.9 Air-gapped mode (P8-9)

- **No outbound by default** in self-hosted builds: `H5P_HUB_ENABLED` default becomes `false` in
  the launcher, chart and VPS env (stays configurable; libraries installed from an offline bundle,
  `ulams h5p libraries import <zip>`, which wraps the existing H5P CLI); docs-site Umami only when
  `PUBLIC_UMAMI_WEBSITE_ID` is set at build time (owner's site sets it).
- **No external CDNs**: pdf.js worker bundled in admin (replace cdnjs in
  `admin/src/components/ProgramForm/ThreeColProgram/TopicForm/media/upload.tsx:13` and
  `admin/src/service-worker.js:41`), Swagger view fonts self-hosted
  (`api/resources/views/vendor/l5-swagger/index.blade.php:7`), legacy front theme fonts
  self-hosted, and the web CSP no longer allows Google Fonts. CI check
  `scripts/check-no-external-urls.mjs` over built bundles and served HTML (allow list: none in
  self-hosted mode; Sentry DSN host only when configured).
- **Offline install**: `ulams stack images save <dir>` / `load` (OCI archives of a pinned release)
  and `--image-registry` for a private mirror (flag exists); the CLI's GHCR tag resolution is
  skipped when `--image-tag` is an exact version.
- **Telemetry statement**: `operators/privacy-and-telemetry.mdx`: no telemetry, no phone-home,
  what talks to the outside only when configured (AI provider, SMTP, Sentry, Stripe/Przelewy24,
  H5P Hub, external S3, LTI platforms), data residency (all data, files and `ai_calls` stay in
  the operator's database and buckets).
- **Offline licence**: not applicable (ADR 0099, no paid modules); the roadmap item gets a note
  instead of new code.
- **Test**: nightly job runs the launcher stack on a Docker network with `internal: true` (no
  egress) after `images load`, and runs the backup/restore E2E plus the no-AI suite inside it.

### 4.10 Audit documentation pack (P8-10, docs only)

`operators/compliance/`: data-flow diagram (including LLM calls and what is sent), data retention
and deletion (per table group, tenant deletion, learner erasure), access-control model (roles,
platform vs tenant, agent permissions), audit logs (agent audit log, Living Course history),
sub-processors list template (the project offers no cloud service, ADR 0099), ISO 27001 Annex A
and GDPR article mapping to the features. Builds on `dpia-learner-insights.mdx` and the Phase 6
compliance plan.

## 5. Dependencies, with justification

| Dependency | Where | Licence | Why, and footprint |
|---|---|---|---|
| `postgres:17.11-alpine` | compose, CI, chart | PostgreSQL | Replaces EOL 12; already used by launcher and VPS |
| `chrislusf/seaweedfs:4.37` | compose, CI, chart | Apache-2.0 | Replaces unmaintained AGPL MinIO image; already in the launcher |
| cosign (CI only) | `publish.yml`, `release.yml` | Apache-2.0 | Keyless image signatures; no runtime impact |
| Trivy (CI only) | `ci.yml`, nightly | Apache-2.0 | One scanner for images, lockfiles, IaC, secrets |
| Helm (operator tool), kubeconform, chart-testing, kind (CI only) | chart | Apache-2.0 | Chart validation and install test; not needed by launcher users |
| `prom-client` | `api/h5p`, `api/pdf` | Apache-2.0 | Standard Node metrics, small, no native code |
| release-please (CI only) | `release.yml` | Apache-2.0 | Changelog and version bumps from Conventional Commits |
| rclone image (already used) | backup, storage migration | MIT | Bucket copy |

No new PHP or frontend runtime dependency. Every action pinned by commit SHA.

## 6. Migrations, tests, risks

**Migrations**: one, in the tenancy package (`storage_key`, `storage_secret` on `tenants`,
encrypted). Settings for mail and AI use the existing settings storage. PostgreSQL and object
storage move data with scripts, not migrations.

**Tests** (per milestone, all in CI):

- P8-1: full PHPUnit matrix and H5P integration on 17; nightly `pg-upgrade` job seeds a 12 stack
  (platform + two demo tenants with H5P content), runs `pg-major-upgrade.sh`, `pg-verify.sh`, then
  the tenant isolation suite on 17; weekly the logical path.
- P8-2: workflow dry runs on a branch with `workflow_dispatch`; `cosign verify` of the pushed
  digest in the same workflow; a deliberately vulnerable test image proves the gate fails.
- P8-3: `S3CompatibilityTest` (opt-in, run in the nightly against SeaweedFS), unit tests for
  `LocalCopy`, feature tests for Markdown/PDF/H5P topic creation on an s3 fake disk, provisioner
  tests with a fake identity driver, two-tenant integration test: tenant A's keys cannot read
  tenant B's bucket; tenant isolation tests unchanged and green; H5P and SCORM behaviour unchanged.
- P8-4: feature tests for `/up`, `/metrics` access control (denied publicly, allowed with token or
  CIDR) and output format (parsed with a Prometheus text parser test helper), JSON log line shape,
  request id propagation into a queued job.
- P8-5: `helm lint`, `kubeconform`, parity test, kind install nightly, `check-migrations.mjs` unit tests.
- P8-6: nightly backup/restore E2E as above; CLI unit tests for manifest, refusal of newer backups,
  secret redaction.
- P8-7: CLI prompt tests with a fake TTY; admin page tests; setup token tests (expiry, single use);
  tenant isolation for the new settings endpoints; WCAG checks for the admin page.
- P8-8: driver unit tests against recorded OpenAI-compatible responses (no live model in tests),
  `NoAiTest`, AI-disabled Playwright run.
- P8-9: external-URL check, egress-free nightly run.

**Risks**

- Collation differences between the Debian-based `postgres:12` and Alpine (musl) 17 change text
  sort order; dump/restore rebuilds indexes so nothing is corrupted, but ordering of names may shift.
  Mitigation: release note; ICU option in section 7.
- SeaweedFS ACL or IAM behaviour differs from AWS; mitigated by the spike before any switch and the
  RustFS fallback.
- Per-tenant keys must reach every replica and H5P: keys live in `tenants` and env files are rebuilt
  on start (existing mechanism); new tenants still need a sync on other replicas (documented, as today).
- Keyless signing depends on Sigstore public infrastructure at publish time; a Sigstore outage
  delays publishing, not running.
- Two Kubernetes renderings drift; the parity test limits it.
- Scanner noise from unfixable base-image CVEs; gate only on fixable findings.
- Small local models produce invalid structured output often; the driver's repair retry and the
  critics limit damage, and docs state the trade-off.

## 7. Open questions, each with the default that will be used

1. **PostgreSQL major**: stay on 17 (decided in #41; 17.11 already on every new install) rather than
   jump to 18. *Default: 17.* A later 17 to 18 move reuses the same script.
2. **Image flavour and collation**: Alpine `17.11-alpine` (matches launcher and VPS) with the libc
   default, vs Debian or ICU (`POSTGRES_INITDB_ARGS=--locale-provider=icu --icu-locale=und`).
   *Default: Alpine, libc default*, ICU documented as an option for new installs.
3. **Logical-replication path**: documented with a helper script and tested weekly, not offered as
   the default. *Default: dump/restore is the supported path; logical is documented and tested.*
4. **Object store**: SeaweedFS (decided in #42), RustFS fallback only on a failed spike check.
   *Default: SeaweedFS 4.37.*
5. **Identity driver default for external S3** (AWS, R2): shared keys, operator manages IAM.
   *Default: `none` for external S3, `seaweedfs` for the bundled store.*
6. **Scanner**: Trivy only. *Default: Trivy*, Grype not added.
7. **Signing**: keyless cosign. *Default: keyless (ADR 0108).*
8. **Gate thresholds**: PRs fail on fixable CRITICAL/HIGH in lockfiles and config, images fail on
   fixable CRITICAL. *Default: as stated*, patch policy 2 days CRITICAL / 14 days HIGH.
9. **Helm vs launcher**: chart is the production path, launcher stays for trials (ADR 0109).
   *Default: both, with a parity test.*
10. **Bundled databases in the chart**: our own single-replica StatefulSets, on by default, with
    external values. *Default: bundled on, documented as non-HA.*
11. **Metrics endpoint protection**: private CIDRs or a token, never routed publicly.
    *Default: both checks, `METRICS_ALLOWED_CIDRS` = RFC 1918 + loopback.*
12. **Log default**: `stderr_json` in all images and generated deployments; file channel only for a
    bare checkout. *Default: JSON to stderr.*
13. **Wizard scope**: CLI prompts plus an admin checklist; storage read-only in the admin.
    *Default: as designed in 4.7.*
14. **Runtime-editable mail and AI settings** via `AdministrableConfig` with env as override.
    *Default: yes.*
15. **Versioning**: one version for images, chart and CLI; `stable` and `lts` tags; `latest` stays
    `main`. *Default: as in 4.6*, LTS declared from the first 1.0.
16. **H5P Hub in self-hosted installs**: off by default, offline library import.
    *Default: off* (the dev compose and demos keep it on).
17. **Offline licence item**: mark not applicable (ADR 0099). *Default: note in the TODO.*
18. **Expand/contract migration check**: CI gate with a marker. *Default: gate on.*
19. **Local-model eval in nightly**: CPU Ollama small model, report only. *Default: on, not a gate.*

## 8. Needed from the owner (cannot be defaulted)

- **First release cut**: approve tagging `v0.1.0` (images, chart, CLI) once P8-2 and P8-6 land; it
  publishes the first signed release and SBOMs. Nothing is published before that beyond today's
  `main` images.
- **Repository settings** (admin rights): enable GitHub code scanning (SARIF upload) and confirm
  Actions may request `id-token: write` for `publish.yml` and `release.yml`.
- **GHCR package for the chart**: the first push creates `ghcr.io/ulams-dev/charts/ulams`; making it
  public is an organisation-owner action, like the image packages.
- **CI minutes**: the nightly kind, backup/restore, egress-free and local-model jobs add roughly
  one to two runner hours per night on public-repo runners (free for public repositories; no
  spend expected unless larger runners are wanted).

No credentials, accounts or paid services are needed for P8-1 to P8-5.

## 9. Milestones

Each milestone is one PR (or two small ones), demoable on its own.

| # | Milestone | Covers | Demo |
|---|---|---|---|
| **P8-1** | PostgreSQL 17 everywhere + tested upgrade | 4.1 | `make pg-upgrade` moves a seeded 12 dev stack to 17 with `pg-verify.sh` green; CI on 17; nightly upgrade job green |
| P8-2 | Supply chain | 4.2, ADR 0108 | `cosign verify` of a published image; Trivy results in code scanning; hardening guide page |
| P8-3 | SeaweedFS + per-tenant identities | 4.3, ADR 0041 | Spike report; dev stack on SeaweedFS; tenant A's keys denied on tenant B's bucket; Markdown/PDF topic creation works in containers |
| P8-4 | Health, metrics, JSON logs | 4.4 | Prometheus scraping `/metrics` and Caddy; JSON log line with request id across API and a queued job |
| P8-5 | Helm chart + Kubernetes tutorial + zero-downtime guide | 4.5, ADR 0109 | `helm install` on kind, rolling upgrade without failed requests, tutorial examples validated |
| P8-6 | Backup/restore commands in CI + release channels | 4.6 | Nightly backup, wipe, restore with learner progress intact; release-please PR with changelog |
| P8-7 | Setup wizard | 4.7 | Interactive `create-ulams`; admin checklist sends a test mail and a test AI call |
| P8-8 | Any AI provider + no-AI verification | 4.8 | Course builder on an Ollama model; whole LMS suite green with AI disabled |
| P8-9 | Air-gapped mode | 4.9 | Stack runs and passes E2E on a network without egress after `images load` |
| P8-10 | Audit documentation pack | 4.10 | Compliance pages published in the docs |

**Recommended first: P8-1.** PostgreSQL 12 has been end of life since November 2024, the change is
small and self-contained (images, one script, CI, docs), and the dump/restore tooling it adds is
reused by P8-6. Then P8-2 (cheap, and it would have flagged the PHP advisory on our images), then
P8-3. P8-4 comes before the chart because the chart's probes and ServiceMonitor use its endpoints;
P8-6 follows the chart because the backup must cover SeaweedFS buckets (P8-3) and both targets.

TODO updates when each milestone lands: tick the quoted items only when implemented, tested and
merged; partial notes otherwise; ADR 0040 and 0041 to Accepted with P8-1 and P8-3.
