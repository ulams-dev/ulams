# ulams

An AI-native, headless LMS. Courses stay in sync with their sources and adapt to each learner, with
every generated element cited. ulams started from Wellms (EscolaLMS) and is now one self-contained
monorepo: no external `escolalms/*` packages, every module lives here as source.

> Status: Phase 0 (foundation and audit) of [`docs/ROADMAP-PROMPT.md`](docs/ROADMAP-PROMPT.md).
> Progress is tracked in [`docs/ROADMAP-TODO.md`](docs/ROADMAP-TODO.md).

## What's inside

| Folder | What it is | Stack |
|---|---|---|
| [`api/`](api) | REST API, multi-tenant | Laravel 13, PHP 8.4, PostgreSQL, Valkey (Redis protocol), MinIO/S3 |
| [`api/packages/`](api/packages) | Domain modules (courses, topic types, quizzes, auth, payments, tenancy, …), autoloaded by the API | PHP, namespace `Ulams\` |
| [`api/h5p/`](api/h5p) | H5P service (player, editor, content API), isolated because H5P is GPL | Node 22, Express 5, [Lumi](https://github.com/lumieducation/h5p-nodejs-library) |
| [`api/pdf/`](api/pdf) | PDF renderer for certificates and templates | Node 22, Express 5, [pdfme](https://pdfme.com) |
| [`admin/`](admin) | Admin and author panel | React 18, umi/max, Ant Design |
| [`front/`](front) | Learner front-end, themed per tenant | React 18, Vite, CSS Modules + `--ulams-*` variables |
| [`docs/`](docs) | Roadmap, plans, decisions (ADRs), reports, design briefs | |

Shared front-end libraries (`components`, `sdk`, `ts-models`, `scorm-player`) live in
`front/src/lib` and are imported as `@ulams/*`; admin reuses them from there.

## Quick start

Requirements: Docker, Node 22 or 24 (`.nvmrc` pins 22, which CI uses), Yarn 1 via Corepack (`corepack enable`).

```bash
corepack yarn install                       # all JS workspaces, one yarn.lock
corepack yarn dev:api                       # API stack: php-fpm, Postgres, Valkey, MinIO, Caddy, H5P, PDF, …
corepack yarn dev                           # admin on :8000 and front on :3000
```

| URL | What |
|---|---|
| http://api.localhost/api/documentation | API (platform tenant) and Swagger |
| http://localhost:3000 | Learner front (platform) |
| http://localhost:8000 | Admin (platform), `admin@ulams.app` / `secret` |
| http://localhost:8025 | MailHog (outgoing e-mail) |
| http://localhost:8078 | Adminer (database) |

`*.localhost` resolves to 127.0.0.1 in current browsers; Caddy routes the hosts.

### Demo tenants

Six demo sites, each with its own database, bucket, theme, landing page and seeded course
(designs and content: [`front/docs/design/experiences.md`](front/docs/design/experiences.md)). The last three are free
and interactive: Gravity Lab (a live 3D solar system), Poland, Measured (a map and charts, in English and Polish) and The
Scottish Book (Stanisław Ulam and the Lwów School, with five small interactives). Their lessons play packages from
[`demo-content/`](demo-content), and every statement of their text is cited to a source. The platform landing
(http://app.localhost:4321 with the reference frontend) shows all six, each with "Open as learner" and "Open as admin":
demo mode logs you in with one click, and every tenant is wiped and seeded again every hour.

| Tenant | Learner site | Admin | API |
|---|---|---|---|
| The Coffee Atlas | http://coffee.app.localhost | http://coffee.admin.localhost | http://coffee.localhost |
| On-Call | http://oncall.app.localhost | http://oncall.admin.localhost | http://oncall.localhost |
| Night Sky Explorers | http://nightsky.app.localhost | http://nightsky.admin.localhost | http://nightsky.localhost |
| Gravity Lab | http://gravity.app.localhost | http://gravity.admin.localhost | http://gravity.localhost |
| Poland, Measured | http://poland.app.localhost | http://poland.admin.localhost | http://poland.localhost |
| The Scottish Book | http://ulam.app.localhost | http://ulam.admin.localhost | http://ulam.localhost |

Create them and their content (inside the API container):

```bash
docker compose -f api/docker-compose.yml exec api bash
php artisan ulams:tenant:create coffee --name="The Coffee Atlas" --theme=coffee --accent="#C2552D"
php artisan ulams:tenant:create oncall --name="On-Call" --theme=oncall --accent="#58A6FF"
php artisan ulams:tenant:create nightsky --name="Night Sky Explorers" --theme=nightsky --accent="#FFD23F"
php artisan ulams:tenant:create gravity --name="Gravity Lab" --theme=gravity --accent="#3DD6F5"
php artisan ulams:tenant:create poland --name="Poland, Measured" --theme=poland --accent="#C8102E"
php artisan ulams:tenant:create ulam --name="The Scottish Book" --theme=ulam --accent="#1D3B8F"
exit
make -C api demo-packages demo-seed demo-seed-tenants
```

`make -C api demo-create-tenants` runs the six create commands in one go and `make -C api demo-mode-on` turns demo mode
on for all six. `make -C api demo-packages` builds the interactive packages of Gravity Lab, Poland and The Scottish Book
on the host first (the API container cannot see `demo-content/`; Chromium is needed once for the gravity posters). To
start over: `make -C api demo-reset-all` resets the six tenants now, `make -C api demo-reset DOMAIN=ulam.localhost` one.

Demo users per tenant: `admin@<slug>.ulams.app`, `tutor@<slug>.ulams.app`,
`student1…5@<slug>.ulams.app`, password `TENANT_DEMO_PASSWORD` (dev only, see `api/.env.example`).

## Everyday commands

```bash
corepack yarn build                         # build all workspaces (turbo, cached)
corepack yarn typecheck                     # TypeScript in admin, front, api/h5p, api/pdf
corepack yarn lint
corepack yarn test                          # JS unit tests
corepack yarn test:api                      # PHPUnit inside the API container
node front/tests/visual/visual.mjs --help   # visual regression harness (front + admin)
```

PHPUnit suites (one per package) need a database; see [`api/README.md`](api/README.md).

## Container images

[`.github/workflows/publish.yml`](.github/workflows/publish.yml) builds the images below for
`linux/amd64` and `linux/arm64` and pushes them to GitHub Container Registry on every push to
`main`, on `v*` tags and on manual runs. Tags: `sha-<short>`, the branch name, `<version>` and
`<major>.<minor>` for release tags, `latest` for `main`. Every image has provenance and an SBOM.

| Image | Dockerfile | Licence |
|---|---|---|
| `ghcr.io/ulams-dev/php` | `api/docker/php/Dockerfile` (PHP runtime, also tagged `8.4`) | Apache-2.0, GPL tools listed in its `NOTICE` |
| `ghcr.io/ulams-dev/api` | `api/Dockerfile`, built on the `php` image above | Apache-2.0 |
| `ghcr.io/ulams-dev/h5p` | `api/h5p/Dockerfile` | **GPL-3.0-or-later**, see [`LICENSING.md`](LICENSING.md) |
| `ghcr.io/ulams-dev/pdf` | `api/pdf/Dockerfile` | MIT |
| `ghcr.io/ulams-dev/admin` | `admin/Dockerfile` (nginx-unprivileged, port 8080; `REACT_APP_*` env written to `runtime-config.json` at start) | MIT |
| `ghcr.io/ulams-dev/front` | `front/Dockerfile` (nginx-unprivileged, port 8080; `VITE_APP_*` env written to `runtime-config.json` at start) | MIT |
| `ghcr.io/ulams-dev/web` | `front/web/Dockerfile` (Astro SSR on Node, `ULAMS_*` env, port 4321) | MIT |

All Dockerfiles except `api/` and `api/docker/php/` build from the repository root, e.g.
`docker build -f front/web/Dockerfile -t ulams/web:dev .`.

## Architecture in one paragraph

The Laravel API is the system of record (courses, learners, progress, entitlements). It is
multi-tenant: one database, bucket and Passport key pair per tenant, selected from the request host
(`api/packages/tenancy`, ADR 0007). Admin and front are static SPAs that pick their tenant API from
the browser host. Copyleft components run as separate services reached only over HTTP or iframes:
H5P in `api/h5p` (GPL), with Admin and Front embedding its player and editor pages
([`LICENSING.md`](LICENSING.md), ADR 0003). PDFs are rendered by `api/pdf`. Themes are CSS custom
properties generated from presets and tenant settings (ADR 0004).

## Documentation

- The documentation site in [`front/docs-site`](front/docs-site) (Astro Starlight): guides for
  course authors, administrators, developers and operators, plus reference pages generated from the
  code. Run it with `corepack yarn dev:docs` (http://localhost:4322); `main` is published to GitHub
  Pages by [`.github/workflows/docs.yml`](.github/workflows/docs.yml).
- [`CLAUDE.md`](CLAUDE.md) and [`AGENTS.md`](AGENTS.md): how to work on this repo (people and agents)
- [`docs/ROADMAP-PROMPT.md`](docs/ROADMAP-PROMPT.md): product spec; [`docs/ROADMAP-TODO.md`](docs/ROADMAP-TODO.md): progress
- [`docs/decisions/`](docs/decisions): architecture decisions; `*/docs/adr`: history of each app before the monorepo
- [`docs/reports/phase-0-audit.md`](docs/reports/phase-0-audit.md) and [`docs/plans/phase-0.md`](docs/plans/phase-0.md)
- [`LICENSING.md`](LICENSING.md): licences per folder and the open-core rules
- Package READMEs: `api/packages/*/README.md`, `api/h5p/README.md`, `api/pdf/README.md`

## Licence

MIT for the root and new code ([`LICENSE`](LICENSE)); imported modules keep their own licences and
`api/h5p` is GPL-3.0-or-later. See [`LICENSING.md`](LICENSING.md).
