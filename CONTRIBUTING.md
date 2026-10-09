# Contributing to ulams

Thank you for helping build ulams, an AI-native, headless LMS. This guide covers how to report
problems, propose changes and get a pull request merged. The project's working rules live in
[`CLAUDE.md`](CLAUDE.md) (workflow and non-negotiables) and [`AGENTS.md`](AGENTS.md) (the practical
map of the repository); they apply to people and to coding agents alike.

Everyone taking part is expected to follow the [Code of conduct](CODE_OF_CONDUCT.md). Security
problems are never reported in public: see the [Security policy](SECURITY.md).

## Ways to contribute

- **Report a bug** with the [bug report form](https://github.com/ulams-dev/ulams/issues/new?template=bug_report.yml).
  Say what you did, what you expected and what happened, and which app (API, admin, front,
  reference frontend, H5P or PDF service) and commit or image tag you used.
- **Suggest a feature** with the [feature request form](https://github.com/ulams-dev/ulams/issues/new?template=feature_request.yml).
  Check the [roadmap](docs/ROADMAP-TODO.md) first: the item may already be planned.
- **Ask a question or discuss an idea** in [GitHub Discussions](https://github.com/ulams-dev/ulams/discussions),
  if they are enabled for the repository; otherwise open an issue.
- **Improve the documentation**: the documentation site lives in [`front/docs-site`](front/docs-site).
- **Send a pull request** for an open issue. For anything larger than a small fix, comment on the
  issue first so the work is not duplicated and the scope is agreed before you start.

## How changes are made

ulams follows the workflow in [`CLAUDE.md`](CLAUDE.md):

1. **Explore**: read the relevant code, packages and tests.
2. **Plan**: for a milestone or any non-trivial change, write the plan in `docs/plans/phase-N.md`
   (goal, scope, design, new dependencies with justification, migrations, tests, risks, open
   questions) or in the issue. **The product owner approves the plan before implementation
   starts.** If the plan changes significantly during implementation, stop and say so in the
   pull request instead of improvising.
3. **Implement** in small commits, one concern per commit, with the tests in the same commit.
4. **Verify**: the full test suite of what you touched, linters, the docs coverage check, and
   accessibility checks for learner-facing UI.
5. **Update** [`docs/ROADMAP-TODO.md`](docs/ROADMAP-TODO.md) when your change completes or advances
   a roadmap item (tick an item only when it is implemented, tested and merged; never delete or
   reword items).

The spec ([`docs/ROADMAP-PROMPT.md`](docs/ROADMAP-PROMPT.md)) is changed only with the product
owner's approval.

## Branches and commits

- Branch from `main` and name the branch `phase-N/short-description` (for example
  `phase-1/lti-deep-linking`), where `N` is the roadmap phase the work belongs to.
- Use [Conventional Commits](https://www.conventionalcommits.org/): `feat:`, `fix:`, `refactor:`,
  `test:`, `docs:`, `chore:`, optionally with a scope (`feat(lti): …`). The pull request title
  follows the same format.
- One concern per commit; tests go in the same commit as the code they cover.
- No AI attribution anywhere: no `Co-Authored-By` trailers for AI tools, no "Generated with …"
  footers in commits or pull requests, no tool-named branch prefixes, no generated-by headers in
  files.

## What every pull request needs

- **Tests.** New behaviour has tests; existing tests stay green. Mock the LLM in unit and feature
  tests (real-model runs only through the eval command).
- **Tenant isolation.** Every new API endpoint has policies, Swagger annotations and a tenant
  isolation test.
- **Accessibility.** Learner-facing UI meets WCAG 2.2 AA.
- **No regressions in content formats.** H5P and SCORM behaviour stays unchanged.
- **Documentation.** Every significant change updates the documentation site
  ([`front/docs-site`](front/docs-site)) in the same branch. The coverage check fails when a module,
  admin route, learner route or topic type has no page that documents it:

  ```bash
  corepack yarn workspace @ulams/docs coverage
  ```

- **A decision record when you made a decision.** Every decision gets an ADR in
  [`docs/decisions`](docs/decisions) (see below).

The [pull request template](.github/pull_request_template.md) repeats this list as a checklist.

### Project rules that reviewers check

- LMS entities are created and changed through the package repositories and services, never by
  writing tables directly.
- Do not use, extend or depend on `escolalms/recommender`.
- AI changes to course content are reviewable diffs the author approves, and every generated
  element cites its source fragments. Uploaded content is untrusted input.
- No model names hardcoded outside config; every LLM call logs model, tokens and cost.
- Styling in admin and front: CSS Modules and `var(--ulams-*)` custom properties only; no
  styled-components.
- New modules are new packages in `api/packages/<name>`, following an existing small package.
- Every new dependency is justified in the plan or pull request: licence, maintenance, size and
  impact on self-hosting.

## Decisions (ADRs)

Every decision gets an architecture decision record: anything architectural, a new dependency,
a change to a licence boundary, the data model or a public API, or anything hard to reverse.
Write it in [`docs/decisions`](docs/decisions) as `NNNN-short-title.md` with the next free number,
following the existing records ([`docs/decisions/README.md`](docs/decisions/README.md)). A
contributor proposes it with status **Proposed**; the product owner approves it, which changes the
status to **Accepted**. The documentation site lists every ADR with its status automatically.
More detail: the "Decisions and documentation" page in the Contributing section of the
documentation site.

## Review

- CI must be green. The `CI OK` job of [`ci.yml`](.github/workflows/ci.yml) sums up the JS and
  PHPUnit jobs; the docs build checks coverage and links.
- A maintainer reviews every pull request. The product owner approves plans and ADRs; a pull
  request that depends on an unapproved plan or ADR waits for that approval.
- Keep pull requests small and focused. Answer review comments with a new commit rather than a
  force-push while the review is in progress, so reviewers can see what changed.

## Running the checks locally

Requirements: Docker, Node 22 (`.nvmrc`) or 24 and Yarn 1 via Corepack (`corepack enable`). Setup is in
the [README](README.md#quick-start).

```bash
corepack yarn install
corepack yarn turbo run typecheck lint build test --filter=<workspace>   # admin, front, api-h5p, api-pdf, @ulams/web, @ulams/ui, @ulams/sdk
corepack yarn test:api                                                   # PHPUnit inside the API container
corepack yarn workspace @ulams/docs coverage                             # docs coverage check
corepack yarn dev:docs                                                   # documentation site on http://localhost:4322
```

To run a single PHPUnit suite, see [`AGENTS.md`](AGENTS.md#running-things). The pre-commit hook
(Husky and lint-staged, [`.lintstagedrc.mjs`](.lintstagedrc.mjs)) formats staged admin and front
files with each app's own Prettier and runs ESLint on admin; it does not replace the checks above.

## Licence of contributions

Contributions are accepted under the licence of the files they change (inbound = outbound): by
submitting a pull request you agree that your contribution is licensed under the licence that
already covers those files. The nearest `LICENSE` file in a folder or its parents wins
([`LICENSING.md`](LICENSING.md) has the full table):

| Path | Licence |
|---|---|
| Root files, `docs/`, `front/`, `admin/` | MIT ([`LICENSE`](LICENSE), [`admin/LICENSE`](admin/LICENSE)) |
| `api/` (the Laravel application) | Apache-2.0 ([`api/LICENSE`](api/LICENSE)) |
| `api/packages/*` | Each package's own `LICENSE` (MIT for nearly all of them) |
| `api/pdf` | MIT ([`api/pdf/LICENSE`](api/pdf/LICENSE)) |
| `api/h5p` | GPL-3.0-or-later ([`api/h5p/LICENSE`](api/h5p/LICENSE)) |
| `admin/src/lib/markdown-editor` | BSD-3-Clause |

A new package in `api/packages` gets an MIT `LICENSE` file. Imported code keeps its original
copyright notices.

**No GPL or AGPL code outside `api/h5p`.** Copyleft components run as separate programs reached
only over HTTP, CLI or an iframe. Never copy or port code from `api/h5p` (or any GPL project) into
other folders, and never import it from the API, admin or front. Check the licence of every new
dependency before adding it.
