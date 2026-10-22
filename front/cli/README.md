# ULAMS CLI

The `ulams` command operates ULAMS instances and serves the same command registry through MCP.
Host deployment commands are local CLI commands and are not exposed as MCP tools.

## Install and launch the whole stack

Node.js 22.12+ and Docker Compose v2, or kubectl connected to a cluster, are required.

```bash
npm install -g https://github.com/ulams-dev/ulams/releases/download/cli-v0.2.0/ulams-0.2.0.tgz
ulams stack up
ulams stack up --mode production --domain learn.example.org
ulams stack up --target kubernetes --mode production --domain learn.example.org
```

Run each independent installation with its own `--directory` and `--name`. Development uses
fake AI for Phase 2 authoring trials; production uses HTTPS and initially disables AI. SMTP
must be configured before sending real mail. The Kubernetes starter is a singleton, not HA.

```bash
ulams stack render --target kubernetes --directory ./review
ulams stack status --json
ulams stack down                 # retain data
ulams stack up                   # resume the same installation
ulams stack down --purge --yes   # deliberately delete persistent data
```

Keep `ulams-stack/credentials.json` and the persistent volumes backed up together. The CLI
pins one matching image release, preserves keys and checks resource ownership. It never prints
passwords. Generated files are regenerated on `stack up`; derive a separate deployment for
advanced configuration.

See the [complete installation guide](https://ulams-dev.github.io/ulams/operators/one-command-stack/)
and [command reference](https://ulams-dev.github.io/ulams/reference/cli/stack/).

## Courses as Markdown and YAML

`ulams course validate` and `ulams course fmt` check and format a [course folder](https://ulams-dev.github.io/ulams/developers/course-folder-format/)
offline: no server, no login, no network. Errors come with file, line and column, as text, JSON or SARIF.

```bash
ulams course validate examples/kubernetes-101          # exit 7 on errors
ulams course validate --format sarif --out ulams.sarif
ulams course fmt --check                               # exit 13 when files would change
```

`course init`, `pull`, `push`, `diff`, `status`, `apply` and `publish` sync a folder with a course on an academy. A push is always a proposed
version: nothing reaches learners until a person approves and applies it, and agent tokens can only propose
([guide](https://ulams-dev.github.io/ulams/developers/cli-course/)).

```bash
ulams course init ./kubernetes-101 --from-course 12
ulams course diff                                      # what changed since the last pull (offline)
ulams course push --message "Fix the install step"     # proposes a version
ulams course apply --approve && ulams course publish   # a person approves, applies, publishes
```

## Development and verification

```bash
corepack yarn workspace ulams build
corepack yarn workspace ulams typecheck
corepack yarn workspace ulams lint
corepack yarn workspace ulams test
```

Real lifecycle tests run only when requested:

```bash
ULAMS_STACK_E2E=1 ULAMS_STACK_E2E_TARGET=docker ULAMS_STACK_E2E_MODE=development \
  corepack yarn workspace ulams exec vitest run tests/e2e/stack.test.ts
```

Run the matrix for `docker`/`kubernetes` and `development`/`production`. Kubernetes uses the
selected kubeconfig context and its own namespace. Each test creates and deletes only its
own installation. Tests use deterministic AI, inspect frontend pages, render PDF/MJML, check
H5P and verify credentials and courses survive stop/resume. Set `ULAMS_STACK_E2E_CLI` to an
installed package's `dist/ulams.mjs` to test the release outside this checkout.
