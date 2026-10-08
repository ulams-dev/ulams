# TODO

Follow-up tasks for the ulams monorepo, grouped by stage. Tick an item off in the same commit that resolves it.

## Next stage: remove the remaining EscolaLMS / Wellms references

The project rename to **ulams** keeps these on purpose for now, because changing them would break the build or rewrite history.

- [ ] **PHP base image.** `api/Dockerfile` and `api/Dockerfile.develop` build `FROM escolalms/php:8.3-alpine`, published on Docker Hub. Build and publish our own `ulams/php` base image (PHP 8.3 alpine with the same extensions, plus `excimer` built from source), then switch both Dockerfiles to it.
- [ ] **Upstream provenance links.** `api/packages/README.md`, `front/src/lib/*/README.md` and `admin/src/lib/*/README.md` link to `github.com/EscolaLMS/*` at the commits the code was imported from. Decide whether to keep them as a historical "imported from" note or drop them once the packages have diverged.
- [ ] **ADR evidence.** `api/docs/adr`, `admin/docs/adr` and `front/docs/adr` cite commit messages and package names as they were (`escolalms/*`, `@escolalms/*`, Wellms). Reword the prose to ulams and keep the quoted commit evidence unchanged.
- [ ] **Leftover strings.** After the rename commit, run `git grep -niE 'escola|wellms'` and review every hit that isn't covered by the items above. Expected leftovers: upstream URLs, the Docker Hub base image, and the migration that rewrites old class names in stored data.
- [ ] **Stored data from older installs.** Confirm the namespace migration (`EscolaLms\` → `Ulams\`) covers every polymorphic column and settings row in a database created before the rename, by running it against a copy of a pre-rename database.

## Monorepo hardening

- [ ] **Upgrade Laravel 9 → 11** (the API is on Laravel 9.52, which is out of support), including `bootstrap/providers.php` and dropping the manual provider list where possible.
- [ ] **CI.** Per-project GitHub workflows need path filters and a root `yarn install`. The MySQL/MariaDB services still started by two API workflows can go (CI only tests Postgres).
- [ ] **Front tests.** `front` has a Jest config but Jest isn't installed and CI never runs it; Admin CI also skips Jest.
- [ ] **Front leftovers.** `entrypoint.sh` still runs `sed` on placeholders that `index.html` no longer contains.
- [ ] **Swagger.** `config/l5-swagger.php` scans `packages/tracker/src`, which never existed. Remove the entry.
- [ ] **Large test fixtures** in `api/packages/*/tests` and `database/mocks` (e.g. the 24 MB SCORM zip). Consider Git LFS.
- [ ] **Exact version pins.** `davidbadura/faker-markdown-generator 1.1.0` and `tzsk/sms 6.0.0` are pinned exactly, because upstream required it. Revisit when upgrading.

## H5P service (Lumi)

- [ ] **Multi-tenancy.** Resolve the tenant from the forwarded host and use that tenant's Passport key, database (an `h5p` schema inside the tenant database) and bucket. The `TenantResolver` interface is the extension point.
- [ ] **Short-lived tokens.** Passport tokens last about 5 minutes and are passed to H5P AJAX calls as `?_token=`. Front and Admin must re-fetch the player model when they refresh the token.
- [ ] **Token in Caddy access logs.** The service redacts it from its own logs, but Caddy logs the full URL. Add a log filter for the `_token` query parameter.
- [ ] **Permissions note.** Adding images to new content needs `h5p_update` (or `h5p_author_update`) in addition to `h5p_create`, because of how Lumi checks permissions when copying files.

## Multitenancy (before the AI Course Builder)

- [ ] **Per-tenant prefixes.** Give each tenant its own Redis, cache and Horizon prefix. Today all tenants share queue and cache keys, which lets data leak between tenants.
- [ ] **Worker loops.** Make queue and scheduler loops re-read the tenant list on every iteration instead of using `MULTI_DOMAINS` from boot.
- [ ] **Unknown hosts.** Return 404 for unknown hosts instead of falling back to the default `.env`.
- [ ] **Front/Admin API host.** Derive the API host from the browser's host, and fix the per-host injector, where global env vars override per-host values.

## Cloudflare re-architecture (R&D): TypeScript + Hono

Goal: run ulams on Cloudflare and deploy an R&D environment with one command (`yarn deploy:dev`, which runs `wrangler deploy --env dev` for every Worker through Turbo). Approach: an incremental strangler migration, not a big-bang rewrite.

- [ ] **Spike: run the existing Laravel image as a Cloudflare Container** behind a Hono gateway Worker, with Postgres reached through Hyperdrive and MinIO replaced by R2. If it works, one-command deploy is possible before any rewrite. Record the result as an ADR. *(about 1 week)*
- [ ] **Gateway Worker (Hono):** routes `/api/*` to Laravel (container or origin) and serves Admin and Front as Workers static assets. *(days)*
- [ ] **Storage:** move MinIO to R2. It is S3-compatible, so mostly configuration plus a data copy. *(days)*
- [ ] **Multi-tenancy in Hono:** tenant resolved from the host in middleware, with per-tenant database and R2 settings, replacing gecche multidomain. *(part of the API rewrite)*
- [ ] **Rewrite the API in Hono one domain at a time**, in this order: auth (JWT), then courses / lessons / topics, then progress, then the rest. Each route moves to TypeScript only when it passes the same API contract tests, and Laravel shrinks until it can be removed. *(the large part: roughly 6–12 engineer-months for the full 656-route surface)*
- [ ] **Async and realtime:** Horizon becomes Cloudflare Queues, the scheduler becomes Cron Triggers, and Soketi becomes Durable Objects (WebSockets). *(weeks, in step with the API rewrite)*
- [ ] **H5P (Lumi) and the Adapt builder** need Node filesystem APIs, so they likely run in Cloudflare Containers. Verify this in a spike. *(1–2 weeks each)*
- [ ] **Database:** stay on Postgres via Hyperdrive. Evaluate D1 per tenant only after the rewrite. *(decision, as an ADR)*
- [ ] **Decision needed:** build the AI Course Builder directly in TypeScript on Workers (Anthropic TS SDK, Queues for the pipeline, a Durable Object for progress, R2 for sources) instead of as a Laravel package, so it doesn't have to be ported later.

## MCP server

- [ ] **MCP server on Workers** (TypeScript, Cloudflare Agents SDK) with OAuth sign-in against the LMS. It talks to the current REST API first and moves to the Hono API as the rewrite progresses, without its tools changing. *(about 1–2 weeks for v1)*
  - **Hand-written tools:** create, edit and delete courses, lessons, topics and quiz questions; "create course from outline"; publish and unpublish; enrolments and users.
  - **Topic types:** list the types with their JSON schemas so clients can build valid content.
  - **Statistics and analytics:** course progress, completion, quiz results and other report data.
  - **Generated CRUD tools** built from the API's OpenAPI spec (338 paths), with types shared from `ts-models`.
  - **Tests:** contract tests against a seeded tenant, plus a per-tenant permission check on every tool.

## AI Course Builder

The plan is approved. Work starts after the monorepo steps land; the milestones are in the plan:

- [ ] M1 LLM layer, cost logging, source ingestion (MD, PDF, DOCX).
- [ ] M2 Blueprint schema, interview / Course Brief, outline, progress broadcasting.
- [ ] M3 Lesson and assessment generation (RichText, GIFT, H5P, LiaScript, Adapt) and the applier.
- [ ] M4 Tenant provisioning, theme and landing page, payments, demo users, the three demo tenants.
- [ ] M5 Element-level chat editing with versions.
- [ ] M6 Polish, evals, docs, E2E.

Open defaults to confirm: production base domain and TLS, Adapt plugin scope, LiaScript tracking, limits and budget, new dependencies.
