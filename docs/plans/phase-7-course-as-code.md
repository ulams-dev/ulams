# Phase 7: course-as-code, Git sync and the CLI release (M5, M6)

Status: draft, waiting for owner approval. Nothing here is implemented. Every open question has a
recommended default that implementers use until the owner says otherwise (section 13).

This plan finishes milestones M5 and M6 of `docs/plans/cli.md` and does not replace it. `cli.md` stays the
reference for the registry, the output contract, exit codes, auth and the MCP server (M1 to M4, all
implemented). Where this plan differs from `cli.md` section 9 or 11, this plan wins, and the difference is
named in place.

ADRs proposed with this plan: **0160** (course-as-code sync through Blueprint v3 import versions; amends the
Proposed ADR 0079) and **0161** (CLI release engineering; amends the Proposed ADR 0077).

## 1. TODO items covered

From `docs/ROADMAP-TODO.md`, Phase 7, 7.1 (quoted):

- `- [ ] Markdown + YAML/JSON format with JSON Schema`
- `- [ ] CLI ulams: init, validate, preview, push, pull, diff, publish`
- `- [ ] Two-way Git sync with diff-based conflicts`
- `- [ ] GitHub Action + Docker image; preview deployment per PR`
- `- [ ] Git merge triggers Living Course update proposal`
- `- [ ] (new) M5 course-as-code: Blueprint v2, Markdown + directives format, sync base and conflict diffs (after Phase 3; citations for author blocks pending #78)`
- `- [ ] (new) M6 CLI release: npm ulams (pending #77), bun-compiled binaries (signing pending #76), Docker image`
- `- [ ] (new) Endpoints to import a blueprint as a builder version and export a course as a blueprint (for M5)`

Owner question **#78** "Course-as-code: may hand-written blocks have no citations?" is answered with a
recommended default in section 6. #76 (signing) and #77 (npm name and token) are owner inputs, listed in
section 12.

Spec: `docs/ROADMAP-PROMPT.md` 7.1, principles 2 (every AI change is a reviewable diff), 3 (grounded and
cited), 6 (developer-first), 8 (works without AI).

## 2. Facts this plan is built on (read on `main`, 2026-10-21)

| Area | Fact | Where |
|---|---|---|
| Blueprint | **A v2 already exists** (`schemaVersion: 2`): adds lesson `contentType` `richtext \| liascript \| h5p \| interactive`, `selfChecks`, `interaction`. v1 is still read. `Blueprint::upgrade()` only bumps the number | `api/packages/course-builder/resources/schemas/course-blueprint/v2.json`, `src/Blueprint/Blueprint.php`, `src/Blueprint/SchemaRegistry.php` |
| Ids | Element ids are lowercase ULIDs `^[0-9a-z]{26}$`, minted by the server (`Blueprint::newId`). Fragment ids `^frg_[a-z2-7]{12}$`, derived from source id + heading path + ordinal (position, not text) | `Blueprint.php`, `src/Ingestion/FragmentId.php` |
| Citations | `minItems: 1` on block, lesson, question and interaction citations; every id must be a known fragment of the session (`Checks::citations`). No `origin` field on elements; `origin` exists only on the version row (`ai \| author \| restore`) | `v2.json`, `src/Blueprint/Checks.php` |
| Versions | `course_builder_versions` stores the **full document** per version, kinds `outline \| content \| patch \| author \| restore \| update`, status `proposed \| approved \| rejected \| superseded`, `parent_id`, `diff_from_parent`, `source_revisions` | migration `2026_10_10_100000_create_course_builder_tables.php`, `src/Models/Version.php` |
| Diff | `BlueprintDiff::compare` flattens to `id → fields` and reports `added \| changed \| removed` per element with field before/after. **Gap:** `selfChecks` and `interaction` are not flattened, so their changes are invisible | `src/Blueprint/BlueprintDiff.php` |
| Apply | `BlueprintApplier` maps the document to Course, Lesson, Topic, GIFT questions and Page through domain services, keeps `course_builder_entity_map` (element id → entity id + fingerprint), refuses with 409 on **drift** (an admin edited an entity after the last apply) unless `overwrite` | `src/Apply/BlueprintApplier.php` |
| Routes | Every course-builder route has `EnsureAiEnabled` (503 when AI is off). No endpoint exports a course as a blueprint or imports a document as a version | `api/packages/course-builder/src/routes.php` |
| Hand-made courses | Have **no blueprint** ("Courses written by hand have no blueprint", `knowledge-export/src/Services/CitationResolver.php`) | |
| Session archive | `course-builder:session:export/import` (artisan, `ulams-builder-session/1` tar) moves a whole session between installs; no LMS data | `src/Transfer/SessionArchive.php` |
| Living Course | Connectors `upload`, `git` (GitHub, GitLab, Gitea/Forgejo APIs), `git-cli` (any smart-HTTP host, off by default), `url`, Drive, Notion. Git webhooks accept **push** events on the configured branch, filter by changed paths, debounce 600 s, then `CheckSourceJob` → revision → fragment diff → proposal; analysis starts automatically under `LIVING_COURSE_AUTO_ANALYSE_USD` (0.50). Applying a proposal writes a version of kind `update` through the applier and checks conflicts by element fingerprint | `api/packages/living-course/src/Connectors/*`, `src/Services/ApplyService.php`, `config/living_course.php` |
| Webhooks out | Catalogue has `update_proposal.ready`, `update_proposal.applied`, `course.published`, `knowledge.exported`; deliveries are signed, no custom auth headers | `api/packages/webhooks/src/Support/EventCatalog.php` |
| Scopes | Route map has `api/admin/course-builder*` → `builder`, `api/admin/living-course*` → `living-course`; token `kind` is `cli \| agent \| ci \| integration` | `api/packages/auth/resources/token-scopes.php`, `src/Services/PersonalAccessTokenService.php` |
| CLI | `front/cli`, package `ulams` 0.2.0, tsup bundle `dist/ulams.mjs`, runtime deps `zod`, `yaml`, MCP SDK v2. Commands: core, nouns, `apply`, `builder` (incl. `versions list/get/diff/restore`), `living` (29 commands), `tenants`, `stack`, `mcp`, `operations`, `topics`. No course-as-code commands. Tag `cli-v0.2.0` exists; nothing is published | `front/cli/package.json`, `front/cli/src/commands/*` |
| create-ulams | `front/create-ulams` 0.1.0 depends on `ulams` 0.2.0, wraps `ulams stack up` | `front/create-ulams/package.json` |
| Release CI | `publish.yml` pushes multi-arch images to `ghcr.io/ulams-dev/*` with provenance and SBOM on `main` and `v*` tags. No CLI image, no npm publish, no binaries, no changelog tooling, no reusable action besides `.github/actions/docker-hub-mirror` | `.github/workflows/publish.yml` |

What changes against `cli.md` section 9 because of these facts:

1. The name "Blueprint v2" is taken. The course-as-code additions become **Blueprint v3** (section 4). The
   TODO item text says "v2"; it is not reworded (TODO rules), a note is added when CAC-1 lands.
2. Course-as-code must work with AI off (principle 8), so its endpoints get their own route group without
   `EnsureAiEnabled` (section 7).
3. Hand-made courses need an **adoption** step that builds a first blueprint and entity map from the live
   course (section 7.3). Without it, `ulams init --from-course` cannot work for most existing courses.
4. Admin edits after an apply are drift. Two-way sync must absorb drift into a version instead of only
   refusing (section 7.4).

## 3. Goal and scope

**Goal.** An author keeps a course in a Git repository as Markdown + YAML, validates it locally and in CI,
gets a preview academy per pull request, and on merge the course in ulams changes through a reviewable
version. Edits made in the studio or by Living Course flow back to Git as a pull request. The CLI ships on
npm, as binaries and as a Docker image, with a GitHub Action on top.

**In scope:** Blueprint v3 schema and v2 → v3 migration; the course folder format and its JSON Schemas;
`ulams course init|validate|fmt|preview|push|pull|diff|status|resolve|apply|publish`; import, export, adopt and
drift endpoints; the three-way sync model; the citation rule for hand-written blocks; the GitHub Action, the
`ulams-cli` image and preview academies per PR; Git merges → Living Course proposals with PR links; the CLI
release pipeline (npm with provenance, binaries, signing options, image, versioning, changelog).

**Out of scope:** code exercises (7.2, separate plan; the format reserves `exercises/`), Marketplace listing of
the Action (later, needs its own repository), the hosted MCP server, Homebrew core (a tap is in scope),
GitLab CI templates (the image works there; a template is a follow-up).

## 4. Blueprint v3

### 4.1 Changes from v2

`course-blueprint/v3.json`, `$id https://ulams.dev/schemas/course-blueprint/v3.json`, draft 2020-12.
**Every v2 document is a valid v3 document after `schemaVersion` is bumped**, so the migration is lossless.

| Change | Where | Why |
|---|---|---|
| `origin: "ai" \| "author"` (optional, default `ai`) | block, lesson, question, interaction, faq item | the #78 rule (section 6) needs it per element, not per version |
| `citations` `minItems: 0` when `origin: author`, else `1` (`if/then` in the schema) | block, lesson, question, interaction | same |
| `contentType` adds `scorm`, `cmi5`, `video`, `file`, `embed` | lesson | non-RichText topics in Git (cli.md 9.1) |
| `asset: { path, sha256, bytes, mediaType }` (required for the new content types; `path` relative to `assets/`) and `assetRef: { entityType, entityId }` (set by the server after upload or adoption) | lesson | the file travels in Git, the server keeps the mapping |
| `video: { provider: "upload" \| "youtube" \| "vimeo", url? }`, `embed: { url, title }` | lesson | video and embed topics |
| `key` (optional slug, `^[a-z0-9][a-z0-9-]{0,63}$`, unique among siblings) | module, lesson | readable folder and file names; ids stay the identity |
| `meta: { createdBy?, updatedAt? }` (optional, ignored by apply) | any element | room for the CLI; not diffed |
| `exercises: []` reserved, `maxItems: 0` | lesson | 7.2 later without a schema break |

Not changed: element and fragment id formats, block kinds, quiz and question shapes, `pages`, `finalTest`.

### 4.2 Server changes

- `SchemaRegistry` loads v3; `Blueprint::SCHEMA_VERSION = 3`; `Blueprint::upgrade()` bumps 1 → 2 → 3 (v1 and v2
  stay readable; documents are upgraded on write, never in bulk).
- `Checks::citations` takes `origin` into account (section 6). `Checks::blueprint` adds: asset present for the
  new content types, `key` uniqueness, no duplicated element ids, ids match the format.
- New content types in `src/ContentTypes/`: `ScormType`, `Cmi5Type`, `VideoType`, `FileType`, `EmbedType`,
  each implementing the existing `ContentType` contract (`topics()`, `check()`), creating topics through
  `TopicRepositoryContract` with the existing upload services (`scorm`, `cmi5`, `video`, `files` packages).
  Their `check()` refuses missing assets and unknown asset refs.
- `BlueprintDiff::flatten` adds `selfChecks` (as questions under the lesson) and `interaction` (one element),
  closing the gap in section 2; plus `origin` and `asset.sha256` as diffable fields.
- The AI pipeline keeps writing `origin: ai` implicitly (absent = `ai`), so no prompt or schema change for the
  model is needed. `PatchService::merge` sets `origin: ai` on any element the model changed (section 6).
- Migration: none for data (documents stay JSON). One test proves every fixture blueprint of v1 and v2 in
  `api/packages/course-builder/tests/fixtures` validates as v3 after `upgrade()` and diffs empty against itself.

Public schema: `front/docs-site/public/schemas/course-blueprint/v3.json` (copied by the existing public
schemas generator from ADR 0104).

## 5. The course folder format

### 5.1 Layout (amends cli.md 9.1)

```
kubernetes-101/
  ulams.yaml                  # format version, profile name, course binding (no secrets)
  course.yaml                 # course fields, objectives, faq, pages
  modules/
    01-basics/
      module.yaml             # id, key, title, summary
      01-install.md           # lesson: front matter + directives
      01-install.quiz.yaml    # lesson quiz (optional)
      02-pods.md
  final-test.quiz.yaml        # optional
  assets/                     # images and packages referenced by lessons
  sources/                    # optional: source documents (Living Course inputs, section 9)
  .ulams/
    base.json                 # sync base (committed)
    conflicts/                # only while a sync conflict is open; removed by `resolve`
  .github/workflows/ulams.yml # written by `ulams course init --ci github`
```

`ulams.yaml`:

```yaml
format: 1                     # course folder format version (independent of the blueprint version)
blueprint: 3
profile: coffee               # optional default profile; CI uses ULAMS_URL + ULAMS_TOKEN
course:
  id: 42                      # set by the first push or by init --from-course
  session: 01jb...            # the builder session that owns the course
language: en
lint:
  uncitedAuthorBlocks: warn   # warn | error | off (section 6)
```

Ordering comes from the numeric file and folder prefixes; the CLI renumbers with `ulams course fmt`.
Moving a lesson file between modules is a move (same id), not a delete plus add.

### 5.2 Lesson file grammar

```markdown
---
id: 01j9z8x7w6v5t4s3r2q1p0n9m8
key: install
title: Install kubectl
summary: Install and verify kubectl.
minutes: 15
objectives: [01j9z8...]          # objective ids from course.yaml
contentType: richtext            # default; scorm|cmi5|video|file|embed|liascript|h5p|interactive
citations: [frg_abcdefabcdef]    # lesson-level citations
---

:::paragraph{#01j9z8aaaaaaaaaaaaaaaaaaaa cite="frg_abcdefabcdef"}
kubectl is the command-line tool for Kubernetes clusters.
:::

:::code{#01j9z9bbbbbbbbbbbbbbbbbbbb origin=author}
```bash
kubectl version --client
```
:::

Plain Markdown here, outside any directive, becomes an author paragraph on `ulams course fmt`.
```

Grammar (normative, implemented in `front/cli/src/course/parse.ts`; EBNF in `front/cli/src/course/GRAMMAR.md`
is the docs source):

- File = YAML front matter (`---` fenced, first line) + body.
- Body = sequence of **block directives** and **loose Markdown**.
- Block directive = line `:::<kind>{<attrs>}` … line `:::` (exactly three colons, alone on the line). A
  longer fence (`::::`) opens a directive that may contain a `:::` line inside, like Markdown fences.
- `<kind>` ∈ v3 block kinds `paragraph | callout | steps | code | table | example`.
- `<attrs>`: `#<element id>`, `cite="frg_…,frg_…"` (comma separated, no spaces required), `origin=ai|author`,
  `objectives="id,id"`, `variant=info|warning|tip` (callout only). Unknown attributes are an error.
- Directive content is Markdown stored verbatim in `markdown` (trailing newline normalised). Fenced code
  inside a directive is opaque: `:::` lines inside a backtick fence do not close the directive.
- Loose Markdown between directives: split on blank-line-separated top-level blocks into `paragraph` blocks
  with `origin: author` and a new id. `validate` reports them as `LOOSE_MARKDOWN` (info), `fmt` wraps them.
- Lessons of non-RichText types have no body blocks except an optional intro; their payload is in front
  matter (`asset: scorm/k8s-intro.zip`, `video: {provider: youtube, url: …}`).
- Images: `![alt](../../assets/diagram.png)` only (relative to the lesson file, resolving inside `assets/`).
  Remote images and HTML outside code fail, matching `Checks::markup`.

Why own container directives and not `remark-directive`: the syntax is the same (generic directives proposal),
so editors highlight it, but we need a **line-exact, lossless round trip** with source positions for error
messages, and only one directive form. A 300-line line-based parser plus `yaml` (already a dependency) is
smaller than `remark` + `micromark` + `mdast` (≈ 40 packages), and block content stays a raw string, which is
what the blueprint stores. Re-evaluate only if inline directives are ever needed.

Quiz YAML (`*.quiz.yaml`):

```yaml
id: 01j9...
passScore: 70
questions:
  - id: 01j9...
    type: single            # single | multiple | truefalse | short
    stem: Which command prints the client version?
    origin: author
    citations: []
    options:
      - { id: 01j9..., text: kubectl version --client, correct: true }
      - { id: 01j9..., text: kubectl get version }
      - { id: 01j9..., text: kube --v }
    explanation: The --client flag skips the server call.
```

### 5.3 Mapping and JSON Schemas

- `front/cli/src/course/to-blueprint.ts` (folder → v3 document) and `from-blueprint.ts` (v3 document →
  files). The pair is **round-trip stable**: `from(to(folder))` is byte-identical after `fmt`, and
  `to(from(doc))` deep-equals `doc` minus `meta`. This is the main property test (section 11).
- Course-level fields not in the blueprint (category, tags, price, access) are out of course-as-code v1; the
  `apply -f` manifests (cli.md 7.3) stay the way to manage them.
- JSON Schemas for the files, generated from zod with `z.toJSONSchema` and committed:
  `front/cli/schemas/course-folder/ulams.schema.json`, `course.schema.json`, `module.schema.json`,
  `lesson-front-matter.schema.json`, `quiz.schema.json`, published under
  `https://ulams.dev/schemas/course-folder/1/…` by the public schemas generator. `init` writes
  `# yaml-language-server: $schema=…` headers so editors validate while typing.

### 5.4 Validator

`ulams course validate [--fix] [--strict] [--format text|json|sarif]` runs, without a network call:

1. File schemas (5.3) with source positions (`yaml` CST gives line and column).
2. Grammar errors (unclosed directive, unknown kind or attribute, bad id format).
3. Folder → v3 document, then the bundled `v3.json` (ajv, already a CLI dev dependency → moved to runtime,
   see section 10) for anything the file schemas cannot see.
4. Semantic checks ported from `Checks.php` to `front/cli/src/course/checks.ts`: markup, question shape,
   objectives per lesson, objective ownership, id uniqueness, asset existence and hash, the citation rule.
   A shared fixture set `api/packages/course-builder/tests/fixtures/checks/*.json` with expected issues is run by
   **both** PHPUnit and Vitest so the two implementations cannot drift.
5. Online checks only with `--online`: cited fragments exist on the server, `quizSupport` (needs fragment text).

Issues: `{code, severity: error|warning|info, file, line, column, element?, message, hint}`. Exit 7
`VALIDATION_FAILED` on errors (and on warnings with `--strict`). `--format sarif` lets the GitHub Action show
issues inline on the PR. `--fix` adds missing ids (client-minted ULIDs, lowercase), wraps loose Markdown,
renumbers prefixes and fills `asset.sha256`.

## 6. Citations for hand-written blocks (#78)

**Recommended default (used until the owner says otherwise): yes, hand-written blocks may have no citations,
under five conditions.**

1. Only elements with `origin: author` may have zero citations. Absent `origin` means `ai`.
2. Any element the AI creates or changes (generation, chat patch, variant, Living Course update) is written
   with `origin: ai` and must cite at least one fragment; `PatchService::merge` and `ApplyService::replace`
   set it. An AI edit of an author block therefore needs citations, as the issue proposes.
3. Changing `origin` from `ai` to `author` is allowed only in an import version (a human attests "I wrote
   this") and is shown in the diff as its own field change; the audit log of the import records it. The AI
   pipeline never sets `author`.
4. `ulams course validate` reports uncited author blocks as `UNCITED_AUTHOR_BLOCK` **warning** (`lint.
   uncitedAuthorBlocks: warn`; a team can make it `error`). The studio shows them with a "Written by the
   author" label instead of citation chips; the learner view shows no source chip for them.
5. Publish check and knowledge export say what this costs: "6 blocks have no sources, so Living Course cannot
   keep them up to date" (publish check info), and knowledge export already flags them `uncited: true`.

Why: the non-negotiable is "every **generated** element cites source fragments"; author text is not
generated. The alternatives (refuse uncited pushes, or make authors invent sources) either block the main
course-as-code use case or produce fake citations, which is worse for trust.

Later (CAC-8, optional): `cite="sources/k8s.md#install-kubectl"` resolves a path + heading in the repository's
`sources/` folder to fragment ids at push time, so hand-written courses can cite without knowing fragment ids.

## 7. Server endpoints

All in `api/packages/course-builder`, new controller `CourseAsCodeController`, new route group
`api/admin/course-as-code/*` with `auth:api` and **without** `EnsureAiEnabled`; scope area `builder` (one new
line in `token-scopes.php`, regenerated JSON); permission `course_builder_use`, plus the existing course policy
for the course. Each endpoint gets OpenAPI annotations with typed responses and `operationId` equal to the CLI
id, a tenant isolation test (pattern `api/packages/example-plugin/tests/Api/TenantIsolationTest.php`) and a
docs entry in `developers/course-builder-api.mdx`.

| Method and path | operationId | Does |
|---|---|---|
| `GET /api/admin/course-as-code/courses/{course}/blueprint` | `course.export` | the current blueprint of the course: the session's current version document upgraded to v3, `versionId`, `appliedVersionId`, `drift: [...]`; 409 `NOT_ADOPTED` when the course has no session |
| `POST /api/admin/course-as-code/courses/{course}/adopt` | `course.adopt` | builds a session + an `author` version + entity map from the live course (7.3). Idempotent (returns the existing session) |
| `POST /api/admin/course-as-code/courses` | `course.create` | creates an empty session for a new course (title, language) and returns ids; the course row is created on the first apply |
| `POST /api/admin/course-as-code/sessions/{session}/imports` | `course.import` | body `{document, baseVersionId, message, assets[]}` → validates (schema + `Checks`), computes the diff against `baseVersionId` and the current version, stores a **proposed** version of the new kind `import`, origin `author`. 409 `BASE_OUTDATED` with the remote diff when `baseVersionId` is not the current version and the changes overlap (7.4) |
| `POST /api/admin/course-as-code/sessions/{session}/assets` | `course.assets-upload` | multipart, content-addressed by sha256 (skip when known); returns `{sha256, assetRef}`; stored through the existing `files`/`scorm`/`cmi5`/`video` services |
| `POST /api/admin/course-as-code/sessions/{session}/drift` | `course.absorb-drift` | reads the drifted entities back into the document (7.4) and stores an `author` version `reason: "Admin edits since v12"`, status proposed |
| existing `GET versions/{v}/diff`, `POST versions/{v}/approve`, `POST sessions/{s}/apply`, `POST sessions/{s}/publish` | | duplicated into the new group (same controllers) so they work with AI off; the AI-gated originals stay |

### 7.1 "Author approves" rule

Every push is a reviewable diff. An import version is always created as **proposed**; nothing reaches
learners until it is approved and applied.

- Approval paths: the studio (existing version review UI, which already shows `BlueprintDiff`), or
  `ulams course apply --approve`, or `push --apply`.
- Who may approve through the API: a user with `course_builder_use` on that course, with a token whose `kind`
  is `cli` (device login or created by a human) or a session token. **`agent` tokens can never approve**
  (403 `APPROVAL_REQUIRES_HUMAN`), and neither can a request carrying `X-Ulams-Client: mcp`. **`ci` tokens may
  approve only when the tenant setting `course_as_code.ci_may_approve` is on** (default off; the merged PR is
  then the human review, and the audit log records the PR URL sent as `X-Ulams-Change-Url`).
- Import versions that change an `origin: ai` element's text without the AI pipeline are flagged in the diff
  ("edited by hand, was AI-generated") so reviewers see it.
- Audit: every import, approve and apply writes a `course_builder` audit event with actor, token id and kind,
  commit SHA (`X-Ulams-Commit`) and change URL; Living Course's audit chain links to it when an import
  supersedes a pending proposal.

### 7.2 Version kind `import`

Added to `Version::KINDS` and `VersionService::CONTENT_KINDS`. Migration: none (kind is a string column);
the studio version list gets a label and icon for it (admin and web studio, i18n EN/PL/ZH).

### 7.3 Adopting a hand-made course

`CourseAdopter` (new, `src/Transfer/CourseAdopter.php`) reads the course through the repository contracts
(never raw tables), and builds:

- course fields → `course`; each Lesson → module; each Topic → lesson with one content type:
  RichText → `richtext` with its Markdown split into blocks at top-level headings (all `origin: author`, no
  citations); SCORM, cmi5, video, file, H5P, LiaScript → the matching content type with `assetRef` to the
  existing entity (no file download; `pull` downloads assets lazily with `--assets`); GIFT quiz topics →
  `quiz` with questions parsed by the existing GIFT parser; anything else → `embed`-like opaque lesson
  `contentType: "external"` that apply leaves untouched (listed in `export` warnings).
- an entity map row per element with the current fingerprint, so the first apply after adoption changes
  nothing (test: adopt then apply produces zero writes).

### 7.4 Drift and the three-way rule on the server

- Push carries `baseVersionId`. If the session's current version is the base, the import is a plain child.
- If not (studio edit, Living Course update or another push happened since), the server runs the same
  element-level three-way merge as the CLI (7.5) between base, current and the pushed document. Disjoint
  changes → the import is stored as a merge (`parent_id` = current) and the response lists what came from
  each side. Overlapping changes → 409 `BASE_OUTDATED` with `{conflicts: [{elementId, field, base, ours,
  theirs}]}`; nothing is stored. The CLI turns that into `SYNC_CONFLICT` (exit 6).
- Drift (admin edited Wellms entities directly): `export` reports it; `absorb-drift` reverse-maps the changed
  entity fields (title, RichText Markdown, question text) into a proposed `author` version, which the author
  approves like any other. Fields that cannot be mapped back are listed and keep the blueprint value.

### 7.5 Sync model in the CLI (amends cli.md 9.3)

`.ulams/base.json` (committed): `{format, sessionId, courseId, baseVersionId, baseVersionNumber,
documentHash, elements: {<id>: <sha256 of the canonical element without children>}, assets: {<path>: sha256}}`.
The base is a hash snapshot, not a copy; the full base document is fetched by id (`GET versions/{v}`) when a
merge needs field values. Canonical JSON = sorted keys, normalised Markdown line endings, no `meta`.

Per element id, comparing base (B), local (L) and remote (R):

| B→L | B→R | Result |
|---|---|---|
| same | same | unchanged |
| changed | same | take local |
| same | changed | take remote |
| changed | changed, equal | take either |
| changed | changed, different | **conflict** |
| deleted | changed (or the reverse) | **conflict** (delete vs edit) |
| added on both with the same id | | impossible (ULIDs); different ids are both added |

Moves: an element whose parent or position changed on one side only is moved; position changes on both
sides take remote order and append local-only elements (reported as info). Renames are field changes (ids
are the identity). Field-level merge inside an element is **not** done in v1: one conflict per element keeps
the model simple and matches the studio diff.

Conflicts are written as `.ulams/conflicts/<elementId>.md` with base, local and remote rendered in the
folder syntax plus a unified diff, and `ulams course status` lists them. Resolution:
`ulams course resolve <elementId> --take local|remote|file` (file = the edited conflict file), then `push`
again. Never overwritten silently; `--force` does not exist.

Commands (all `kind: local` or `write` in the registry, exposed over MCP stdio only for local ones):

| Command | Does |
|---|---|
| `ulams course init [dir] [--from-course <id>] [--ci github]` | scaffold, or adopt + export + write files; writes `ulams.yaml`, `base.json`, optional workflow |
| `ulams course validate [--fix] [--strict] [--online] [--format]` | 5.4 |
| `ulams course fmt [--check]` | canonical formatting, ids, renumbering |
| `ulams course diff [--remote] [--exit-code] [--format text|json]` | local vs base (default) or vs remote current version; element-aware, exits 13 with changes |
| `ulams course status` | ahead/behind, drift, open conflicts, pending proposals |
| `ulams course pull [--assets]` | export → three-way merge into files → new base |
| `ulams course push [--message] [--apply] [--approve]` | validate → upload changed assets → import → print the diff and the studio review URL; `--apply` approves (if allowed, 7.1) and applies, `--wait` |
| `ulams course preview [--tenant <slug>]` | push as a proposed version and apply it to a **preview academy** (section 8.3); without `--tenant` it returns the studio preview URL of the proposed version |
| `ulams course resolve <id> --take …` | 7.5 |
| `ulams course apply [--version <id>] --approve` | approve + apply a proposed version |
| `ulams course publish` | publish check, then publish |

Error codes reused from cli.md 4.6: `VALIDATION_FAILED` (7), `SYNC_CONFLICT` (6), `DIFF_FOUND` (13),
`FEATURE_DISABLED` (12, server without course-as-code, detected from `/api/meta.features.courseAsCode`).

## 8. GitHub Action, Docker image and preview academies

### 8.1 Image `ghcr.io/ulams-dev/ulams-cli`

`front/cli/Dockerfile`: multi-stage, build with `node:22-alpine` and yarn workspaces, runtime
`node:22-alpine` with `dist/ulams.mjs` + production deps, `git` installed (for `course` commands that read the
commit), non-root `ulams` user, `ENTRYPOINT ["ulams"]`, labels as in `publish.yml`, multi-arch, provenance and
SBOM. Built by the release workflow (section 10) on `cli-v*` tags, tags `<version>`, `<major>.<minor>`,
`<major>`, `latest`. Size target < 80 MB.

### 8.2 Action

`actions/course/action.yml` in this repository (composite action, so it runs on any runner):
`uses: ulams-dev/ulams/actions/course@cli-v1`. Inputs: `command` (`validate | preview | push | pull-pr`),
`path` (course folder), `url`, `token`, `cli-version` (default the action's own tag), `comment` (bool).
It runs the pinned `ulams-cli` image (`docker run`), so the Node version on the runner does not matter.

Workflow written by `ulams course init --ci github` (`.github/workflows/ulams.yml`):

| Trigger | Steps |
|---|---|
| `pull_request` | `validate --format sarif` (upload to code scanning, so issues show inline), `diff --remote` as job summary, `preview` → comment with the preview URL and the element diff (one sticky comment, updated) |
| `push` to the default branch | `push --message "<commit subject>"` with `X-Ulams-Commit` and change URL; `--apply` only when the tenant allows `ci` approval (7.1), else comment the studio review link on the merged PR |
| `schedule` (hourly) + `workflow_dispatch` + `repository_dispatch: ulams-course-changed` | `pull-pr`: `course pull`; if files changed, open or update the branch `ulams/sync` and a PR "Sync from ulams: <reasons>" (uses `gh`, no extra action) |
| `pull_request: closed` | delete the preview academy |

Secrets the repository owner sets: `ULAMS_URL`, `ULAMS_TOKEN` (a `ci` token, preset `ci`), and for previews
`ULAMS_PREVIEW_URL` + `ULAMS_PREVIEW_TOKEN` (platform token with `platform:write`).

### 8.3 Preview academy per PR

A preview is a throwaway tenant on a **preview platform** (any ulams install with `TENANCY_PLATFORM_API=true`,
ADR 0078): `ulams tenants create pr-<repo-hash>-<n> --demo --ttl 7d --wait`, then `course push --apply` into it
with a tenant token returned by the create call, then the comment links `https://pr-…-<n>.<preview host>`.
On close or after the TTL the tenant is deleted. Needs two small S3 additions: a `ttl` field with a scheduled
reaper (`ulams:tenant:reap`), and `POST /api/platform/tenants` returning a short-lived admin token for the new
tenant (one-time, 24 h, scope `builder:write`). Without a preview platform the Action falls back to the studio
preview URL of the proposed version (no new tenant), so previews work on a single-tenant install too.

### 8.4 Studio and Living Course → Git

- New outgoing webhook event `course.version_applied` (`EventCatalog`): `{course_id, session_id, version_id,
  number, kind, origin, reason}` on every apply (builder, import, Living Course update, drift absorption).
- GitHub cannot take our signed webhook directly (repository_dispatch needs a GitHub token in the
  `Authorization` header). v1 therefore relies on the hourly scheduled `pull-pr` plus manual dispatch. A tiny
  relay is not built; the docs show a 20-line Cloudflare Worker or GitHub App as an option. (Open question 6.)
- The sync PR body lists the versions pulled, their reasons (e.g. "Source update r4 → r5: 3 changes", from the
  Living Course proposal) and links to the studio, so the Git reviewer sees why content changed.

## 9. Git merge → Living Course update proposal

What already works: a `git`/`git-cli` connection on a docs repository creates a revision and a proposal on
every push to its branch (a merge is a push). What this plan adds:

1. **Merge metadata.** `GitConnector::verifyWebhook` keeps the `push` event but also reads `head_commit`
   (`id`, `message`, `url`) and, on GitHub, the PR number from the merge commit message
   (`Merge pull request #123` / squash `(#123)`). Stored in `Revision.metadata.change` and shown on the
   proposal ("from PR #123: Rename --namespace flag"). No new event type subscribed.
2. **Same repository for course and sources.** A course repo with `sources/` connects Living Course to its own
   repository with `paths: [sources/**]`, so a merged change to `sources/` creates a proposal while a merged
   change to `modules/` is pushed by the Action. `ulams course init` offers it (`--sources-connector`), calling
   `ulams living sources connect` with the repo URL and branch.
3. **No webhook needed from CI.** For hosts where the LMS cannot be reached by a webhook (private network),
   the Action on merge runs `ulams living connections check <id> --commit $GITHUB_SHA` (new optional `commit`
   field on the existing `check` endpoint, recorded in metadata), which triggers the same detection.
4. **Loop guard.** A Living Course proposal applied in ulams is pulled to Git by the sync PR; merging that PR
   triggers a push whose document equals the current version, so `import` returns `unchanged` (no version).
   Sync PRs never touch `sources/`, so they never trigger a new proposal. Test: full loop with the fake driver
   creates exactly one proposal and one version.
5. **Pending proposals vs pushes.** `push` refuses (409 `PROPOSAL_PENDING`, exit 6) when a proposal touches an
   element the push also changes, with the proposal link; `--allow-pending` stores the import anyway and the
   proposal's items for those elements become `conflict` (existing fingerprint rule).

## 10. M6: CLI release engineering

### 10.1 Versioning and changelog

- SemVer, CLI versioned independently of the API (ADR 0077). Tags `cli-v<semver>` (exists: `cli-v0.2.0`) and
  `create-ulams-v<semver>`. 0.x until course-as-code ships, 1.0.0 with CAC-6. The contract integer (cli.md 4.5)
  is separate from the package version.
- Changelog from Conventional Commits with **release-please** (`googleapis/release-please-action`, Apache-2.0,
  a GitHub Action, nothing shipped): manifest config `release-please-config.json` + `.release-please-manifest.json`
  with components `front/cli` and `front/create-ulams`, scoped to commits touching them (`feat(cli)`,
  `fix(cli)`…). It opens a release PR with `front/cli/CHANGELOG.md` and the version bump; merging it creates
  the tag and the GitHub release. Alternative rejected: changesets (needs a changeset file per PR, a habit the
  repo does not have); git-cliff (fine, but no release PR).
- `create-ulams` pins the exact `ulams` version; release-please's `node-workspace` plugin bumps it.
- Server compatibility: `/api/meta.contract` and a `cliMin` field; the CLI exits 12 `UNSUPPORTED_SERVER` with
  the version it needs.

### 10.2 npm with provenance

- Workflow `.github/workflows/release-cli.yml`, triggered by `release: published` with a `cli-v*` tag.
- **npm trusted publishing (OIDC)** instead of a long-lived token: `permissions: id-token: write`, `npm publish
  --provenance --access public` from `front/cli` (and `front/create-ulams`). Provenance is attached
  automatically. A trusted publisher can only be configured on a package that exists, so the **first** publish
  needs the owner: either a one-time manual `npm publish` of `0.2.0` from their machine, or a short-lived
  granular `NPM_TOKEN` (publish rights on `ulams` and `create-ulams` only, 7-day expiry) used once and deleted
  after trusted publishing is set up. This replaces the long-lived `NPM_TOKEN` of #77 (ADR 0161).
- `npm pack --dry-run` and `publint` already-style checks in CI on every PR touching `front/cli` (file list
  must be `dist/`, `LICENSE`, `README.md`, `package.json`; no source maps, no tests).
- Package metadata: `repository`, `homepage`, `bugs`, `keywords`, `publishConfig.provenance: true`.

### 10.3 Binaries

- `bun build --compile --minify --bytecode` for `bun-linux-x64`, `bun-linux-arm64`, `bun-linux-x64-musl`,
  `bun-darwin-arm64`, `bun-darwin-x64`, `bun-windows-x64`, from one `ubuntu-latest` job (bun pinned in
  `.bun-version`; dev tool, MIT, not shipped as a dependency).
- Smoke tests in a matrix on real runners (`ubuntu-latest`, `ubuntu-24.04-arm`, `macos-latest`,
  `windows-latest`): `ulams --version`, `ulams schema --json | head`, `ulams course validate` on the example
  course, `ulams api GET /api/meta` against a mock server. A darwin-arm64 binary must at least carry an
  ad-hoc signature to run; if bun's cross-compiled output lacks one, the macOS smoke job signs ad hoc with
  `codesign -s -` before upload.
- Artifacts on the GitHub release: `ulams-<os>-<arch>[.exe]`, `SHA256SUMS`, and **Sigstore** signatures via
  GitHub artifact attestations (`actions/attest-build-provenance`), verifiable with `gh attestation verify`.
  No keys to manage.
- Install paths: `curl -fsSL https://ulams.dev/install.sh | sh` (downloads, checks SHA256, no sudo, installs
  to `~/.ulams/bin`; files fetched with curl are not quarantined by macOS, so Gatekeeper does not block
  them), a Homebrew tap `ulams-dev/homebrew-tap` (formula updated by the release job, needs a repo-scoped
  token, see 12), Scoop manifest later. Until the `ulams.dev` docs domain is live (publishing is gated), the
  script is served from the GitHub release.

### 10.4 Code signing options (#76)

| Platform | Option | Cost | Effect | Owner provides |
|---|---|---|---|---|
| macOS | Apple Developer Program (Developer ID + notarisation, done from Linux with `rcodesign`, Apache-2.0/MIT) | 99 USD/year | no Gatekeeper warning for browser downloads | Apple account; secrets `APPLE_DEVELOPER_ID_P12` (base64), `APPLE_DEVELOPER_ID_P12_PASSWORD`, `APPLE_API_KEY_ID`, `APPLE_API_ISSUER_ID`, `APPLE_API_KEY_P8` (App Store Connect API key for notarisation) |
| macOS | none (ad-hoc signature only) | 0 | curl, Homebrew tap, npm and Docker installs work; a browser download shows a warning | nothing |
| Windows | SignPath Foundation (free code signing for qualifying open-source projects, certificate held by SignPath, signing from GitHub Actions) | 0 | signed binaries, SmartScreen reputation builds over time | application to SignPath Foundation, approval |
| Windows | Azure Artifact Signing (formerly Trusted Signing) | about 10 USD/month (Basic) | signed, Microsoft-rooted; identity validation required (organisation, or eligible individual) | Azure subscription, validated identity; secrets `AZURE_TENANT_ID`, `AZURE_CLIENT_ID`, `AZURE_CLIENT_SECRET` (or OIDC federation) |
| Windows | OV/EV certificate from a CA (key on HSM/cloud HSM since 2023) | about 200 to 600 USD/year | signed; EV no longer gives instant SmartScreen trust | certificate + cloud HSM credentials |
| Windows | none | 0 | SmartScreen warning on first run of downloaded `.exe`; `npm`, Scoop and Docker installs unaffected | nothing |

**Recommended default:** ship unsigned (ad-hoc on macOS) with SHA256 sums and Sigstore attestations now; apply
to SignPath Foundation for Windows (free, no secrets in our repo); buy the Apple Developer Program only if
people download the macOS binary from the browser in meaningful numbers. The release workflow has the signing
steps behind `if: secrets.X != ''`, so adding the secrets later turns signing on without a code change.

### 10.5 Docker image

Section 8.1; built in `release-cli.yml` (not `publish.yml`, which builds server images on `main`). The image
gets the same provenance and SBOM settings; `cosign` is not needed because GHCR shows the attestations.

### 10.6 Release checklist (automated, in `release-cli.yml`)

1. release-please PR merged → tag `cli-vX.Y.Z` → GitHub release (notes from the changelog).
2. Build `dist/` once; run unit tests again; `npm pack`.
3. Publish `ulams` then `create-ulams` to npm with provenance.
4. Build binaries, smoke test the matrix, attach binaries + `SHA256SUMS`, attest.
5. Build and push `ghcr.io/ulams-dev/ulams-cli`.
6. Move the floating action tag `cli-v<major>` to the new tag.
7. Update the Homebrew tap formula.
8. Docs: the install page reads the latest version from the release at build time.

## 11. Tests

- **CLI unit (Vitest, `front/cli/tests/course/`)**: grammar (every attribute, nested fences, code fences
  containing `:::`, CRLF, BOM, empty lesson), error positions; folder ↔ blueprint round trip as a
  **property test** over generated documents (fast-check is not added; a seeded generator of 200 random v3
  documents built from the fixtures is enough); three-way merge table (every row of 7.5, moves, delete vs
  edit); conflict files; `fmt` idempotence; `validate` issue codes and SARIF snapshot; asset hashing.
- **Shared check fixtures**: `api/packages/course-builder/tests/fixtures/checks/*.json` run by PHPUnit and
  Vitest with the same expected issues.
- **PHP (PHPUnit, course-builder)**: v3 schema and v2 → v3 upgrade over all fixtures; new content types create
  topics through the domain services (guard test that no table is written directly, as `GuardsTest` in
  living-course); import (proposed, diff, unchanged push creates no version, `BASE_OUTDATED` with overlapping
  changes, merge with disjoint changes); approval rules per token kind (`agent` 403, `ci` only with the
  setting, MCP header 403); adoption (every topic type; adopt then apply = zero writes); drift absorption;
  asset upload dedupe; AI off: every course-as-code route works with `AI_ENABLED=false`; tenant isolation for
  every new route; `BlueprintDiff` now reports `selfChecks` and `interaction` changes.
- **Living Course**: merge metadata parsed from GitHub, GitLab and Gitea push payload fixtures; `check` with
  `commit`; loop test (proposal applied → pull → push → no new version, no new proposal); `PROPOSAL_PENDING`.
- **E2E (opt-in `ULAMS_E2E=1`, nightly)**: `course init --from-course` on a seeded demo course → edit a lesson
  file → `push --apply` → studio shows the version → edit in the studio → `pull` merges → edit the same block
  locally and in the studio → `SYNC_CONFLICT` → `resolve` → `push`. Plus the hand-written course flow on a tenant
  with AI off.
- **Action**: a workflow in this repo (`.github/workflows/course-action-test.yml`, on changes under
  `actions/course` and `front/cli`) runs the action's `validate` against `front/cli/examples/kubernetes-101`.
- **Release**: `release-cli.yml` has a `workflow_dispatch` dry run (`--dry-run` for npm, no tag move, image
  pushed only as `:dry-run-<sha>`).
- **Accessibility**: the studio "import" version label and the "Written by the author" chip pass the existing
  axe checks (WCAG 2.2 AA).

## 12. Owner-only inputs

Nothing in CAC-1 to CAC-5 needs them; M6 publishing does.

| Input | Needed for | Issue | What exactly |
|---|---|---|---|
| npm names `ulams` and `create-ulams` (+ org `ulams`) | npm publish | #77 | create the npm account/org; either publish `0.2.0` once by hand or add a short-lived granular `NPM_TOKEN` (publish only, 7 days); then add the trusted publisher `ulams-dev/ulams` + `release-cli.yml` on both packages; no long-lived token stays |
| Signing identities | signed macOS/Windows binaries | #76 | optional; see 10.4 (Apple 99 USD/year; SignPath Foundation application; or Azure Artifact Signing about 10 USD/month) and the secret names listed there |
| `HOMEBREW_TAP_TOKEN` | tap updates | new | fine-grained token with contents write on `ulams-dev/homebrew-tap` only (create the repo) |
| Preview platform | preview academies per PR | new | a ulams install with `TENANCY_PLATFORM_API=true` and a wildcard DNS host, plus a platform token; stays local until the publishing gate opens (owner's "work locally" rule) |
| `ulams.dev` install script hosting | `curl … \| sh` | — | waits for the publishing gate; the GitHub release serves it meanwhile |

## 13. Open questions (recommended defaults are used until the owner answers)

1. **#78: may hand-written blocks have no citations?** Default: yes for `origin: author`, under the five
   conditions of section 6 (AI-touched elements must cite, `ai → author` only by a human import and visible in
   the diff, warning in `validate`, author label in UI, Living Course limits stated in the publish check).
2. **Blueprint version name.** "v2" is taken by content types. Default: the course-as-code additions are
   **Blueprint v3**; the TODO item keeps its text with a note.
3. **May CI approve imports?** Default: no; `ci` tokens create proposed versions; a tenant setting
   `course_as_code.ci_may_approve` (off) lets a merged, reviewed PR count as the approval. `agent` tokens and MCP
   clients can never approve.
4. **Conflict granularity.** Default: one conflict per element (no field-level merge in v1).
5. **Adoption of hand-made courses.** Default: yes, `adopt` builds a blueprint from the live course, RichText
   split at top-level headings, other topic types kept by reference.
6. **Studio → Git trigger.** Default: hourly scheduled `pull-pr` plus manual dispatch; no relay service. A GitHub
   App can come later.
7. **Where the Action lives.** Default: `actions/course` in this repository, used as
   `ulams-dev/ulams/actions/course@cli-v1`; a separate Marketplace repository later.
8. **Preview academies.** Default: throwaway tenants on a preview platform with a 7-day TTL; fallback to the
   studio preview URL when no platform is configured.
9. **npm auth.** Default: trusted publishing (OIDC) after one bootstrap publish; no long-lived `NPM_TOKEN`.
10. **Changelog tool.** Default: release-please with components `front/cli` and `front/create-ulams`.
11. **Signing.** Default: unsigned binaries with SHA256 and Sigstore attestations; apply to SignPath Foundation
    for Windows; Apple Developer Program only on demand. Signing steps are in the workflow behind secrets.
12. **Install channels.** Default: npm, Docker, GitHub release binaries, install script, Homebrew tap. Scoop and
    winget later.
13. **1.0.0.** Default: the CLI goes 1.0.0 when CAC-6 lands; before that 0.x minors may break flags (the JSON
    contract still follows cli.md 4.5).
14. **Markdown parser.** Default: own line-based directive parser (no remark/micromark dependency).

## 14. Milestones (small, demoable; one branch and PR each, based on `main`)

| # | Milestone | Demo | Depends on | Size |
|---|---|---|---|---|
| **CAC-1** | Blueprint v3 schema, upgrade, `origin` + citation rule, `BlueprintDiff` gap fixed; `import` version kind; route group without AI; `export` and `import` endpoints (proposed versions only) | `curl` export of a builder course, edit JSON, import → studio shows the import diff; works with AI off | — | ~1 day |
| **CAC-2** | Folder format, grammar, mapping, file JSON Schemas, `course validate/fmt` (offline), shared check fixtures | `ulams course validate` on `front/cli/examples/kubernetes-101` prints file:line issues; SARIF output | CAC-1 (schema only) | ~1 day |
| **CAC-3** | `course init --from-course`, `pull`, `push`, `diff`, `status`, `apply`, `publish` with the base snapshot; no conflicts yet (push refuses when behind) | init from a builder course → edit Markdown → `push --apply` → learner sees the change | CAC-1, CAC-2 | ~1 day |
| **CAC-4** | Three-way merge (CLI and server), conflict files, `resolve`, `BASE_OUTDATED`, drift absorption, approval rules per token kind | edit the same block in studio and Git → conflict → resolve → push | CAC-3 | ~1 day |
| **CAC-5** | Adoption of hand-made courses + new content types (scorm, cmi5, video, file, embed) with asset upload | `course init --from-course` on a seeded non-builder course with a SCORM topic; adopt then apply = zero writes | CAC-3 | ~1.5 days |
| **CAC-6** | `ulams-cli` image, `actions/course`, init `--ci github`, sync PR flow, `course.version_applied` webhook | a PR in a sample repo gets a validation report, a diff summary and (fallback) studio preview link; merge pushes a proposed version | CAC-4, M6-a | ~1 day |
| **CAC-7** | Preview academies (tenant TTL, reaper, one-time token) and Git merge metadata + same-repo sources + `check --commit` + loop guard | PR comment links a live preview academy; a merged docs change shows "from PR #123" on the proposal | CAC-6 | ~1 day |
| **CAC-8** (optional) | `cite="sources/…#heading"` resolution | hand-written course in Git cites its own `sources/` | CAC-5 | ~0.5 day |
| **M6-a** | Release pipeline without owner inputs: release-please, `release-cli.yml` dry run, binaries + smoke matrix + attestations, image to GHCR, install script on the release | `workflow_dispatch` dry run produces all artifacts; `docker run ghcr.io/ulams-dev/ulams-cli:dry-run-… --version` | — | ~0.5 day |
| **M6-b** | Publishing turned on: npm trusted publishing, Homebrew tap, signing steps behind secrets | `npx ulams --version`; `brew install ulams-dev/tap/ulams` | M6-a + owner inputs (#77) | ~2 h |

**Recommended first milestone: CAC-1.** It is server-only, small, unblocks every other CAC milestone, closes the
existing diff gap for interactions, and answers the TODO item "Endpoints to import a blueprint as a builder
version and export a course as a blueprint" on its own. M6-a can run in parallel (different files, no owner
input).

Commit order inside CAC-1 (branch `phase-7/cac-1-blueprint-v3`):
1. `feat(course-builder): Blueprint v3 schema with element origin and author citations` (+ fixtures upgrade test)
2. `fix(course-builder): show self-check and interaction changes in the version diff`
3. `feat(course-builder): import version kind and the course-as-code route group without the AI gate`
4. `feat(course-builder): export a course as a blueprint and import a blueprint as a proposed version` (+ OpenAPI, isolation tests)
5. `feat(cli): course export and course import commands` (thin, until CAC-3 replaces them with pull/push)
6. `docs: course-as-code API and the citation rule for author blocks`

TODO updates when merged: tick "Endpoints to import a blueprint…", add a note on the M5 item ("Blueprint v3,
since v2 is the content-type schema"), add `(new)` items for adoption, drift absorption, preview academies and
`course.version_applied`.

## 15. New dependencies

| Dependency | Where | Licence | Maintenance | Size / impact | Why |
|---|---|---|---|---|---|
| `ajv` 8 (moved from dev to runtime) | `front/cli` | MIT | very active | ~120 kB in the bundle (external dep) | offline JSON Schema validation of v3 documents in `validate`; already a dev dependency |
| `googleapis/release-please-action` | CI only | Apache-2.0 | active (Google) | none shipped | changelog and release PRs |
| `oven-sh/setup-bun` + bun | release CI only | MIT | very active | none shipped | cross-compiled binaries (ADR 0077) |
| `actions/attest-build-provenance` | release CI only | MIT | GitHub | none shipped | Sigstore attestations without key management |
| `rcodesign` (only when Apple secrets exist) | release CI only | MIT/Apache-2.0 | active | none shipped | sign and notarise macOS binaries from Linux |

Not added: `remark`/`micromark` (section 5.2), `diff` (the element diff is structural; the conflict file's
unified diff uses a 100-line Myers implementation in `front/cli/src/course/textdiff.ts`), changesets,
`fast-check`. Server: no new PHP dependency. Self-hosting impact: none; course-as-code works with AI off and
without internet access between the CLI and a self-hosted instance.

## 16. Risks

| Risk | Mitigation |
|---|---|
| Round trip loses formatting or reorders content | canonical `fmt`, byte-identical round-trip property test, raw block Markdown stored verbatim |
| PHP and TypeScript checks drift | shared fixture set run by both suites |
| Authors launder AI text as `origin: author` to skip citations | `ai → author` only through a human import, visible in the diff and audit log; `agent` tokens cannot approve |
| Living Course cannot track uncited author blocks | publish check states it; CAC-8 offers path citations |
| Adoption mis-splits RichText | split only at top-level headings; whole topic as one block when no headings; adopt then apply = zero writes is tested |
| Loops between Git and ulams | unchanged pushes create no version; sync PRs never touch `sources/`; loop test |
| Preview tenants pile up or cost money | 7-day TTL, reaper, deletion on PR close, per-repo cap (5 previews) |
| bun binary differs from Node behaviour | fetch-only command code, smoke matrix on four OSes, Node stays the reference |
| npm name taken before #77 is answered | owner input listed first in section 12; until then the CLI stays installable from the image and release binaries |
| Unsigned binaries scare users | curl/brew/npm/Docker paths avoid quarantine; signing slots ready behind secrets |

## 17. ADRs

- **0160** Course-as-code sync through Blueprint v3 import versions (Proposed): v3 naming, element `origin` and
  the author-citation rule, imports as proposed versions with per-token-kind approval, AI-free route group,
  adoption and drift absorption, element-level three-way merge with a hash base. Amends ADR 0079 (Proposed).
- **0161** CLI release engineering (Proposed): release-please changelog and `cli-v*` tags, npm trusted
  publishing with provenance after one bootstrap publish, Sigstore attestations for binaries, signing behind
  optional secrets (SignPath Foundation first for Windows), CLI image built by the release workflow. Amends ADR
  0077 (Proposed).
