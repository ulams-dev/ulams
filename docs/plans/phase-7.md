# Phase 7: knowledge export, complete OpenAPI plus PHP SDK, create-ulams

Status: draft, waiting for owner approval. Nothing here is implemented.

Scope is three items from the Phase 7 TODO, one branch and one PR each, all based on `main`, tests in
the same commit, in this order: 7.6 knowledge export, 7.3 complete OpenAPI plus PHP SDK, 7.3
`create-ulams`.

## 1. Knowledge export with cited chunks (7.6)

### Goal
Export a course, or a whole tenant, as a knowledge pack for RAG in company agents: chunked content
with citations and metadata, re-exported when the course changes.

### What exists
- `api/packages/course-builder/src/Ingestion/Fragmenter.php` and `FragmentId.php` define source
  fragments and stable ids; the Living Course uses the same ids for citations.
- `CourseContentUpdated` (living-course events) fires when course content changes.
- `api/packages/webhooks` delivers signed, versioned events.
- Per-academy `/llms.txt` and Markdown course pages exist (#274), so a Markdown renderer for course
  content is already there.

### Design
- Format: JSONL, one chunk per line: `id`, `text`, `course_id`, `section`, `topic_id`, `topic_type`,
  `url` (deep link), `citations[]` (source fragment ids, source name, revision), `content_hash`,
  `updated_at`. A `manifest.json` carries pack version, tenant, counts and the pack hash.
- Chunking: by topic and heading, bounded token size, reusing `Fragmenter` rules. Chunks without a
  source fragment get `citations: []` and `uncited: true`. Nothing is invented.
- Only published, learner-visible content is exported; the scope check uses the existing course
  policies.
- Domain service `KnowledgeExporter` builds the pack; a queued job writes it to the files disk. A
  listener on `CourseContentUpdated` re-exports that course only when the pack hash changes, then
  emits a `knowledge.exported` webhook event.
- API: `POST /api/admin/knowledge/exports` (course or tenant), `GET .../exports/{id}`, `GET
  .../courses/{course}/knowledge/latest`. Scoped-token area `knowledge:read|write`. CLI: `ulams
  knowledge export|latest`.
- Docs: guide page plus the public JSON Schema for the chunk and the manifest.

### Tests
Unit: chunking, citation mapping, hash stability. Feature: export, re-export only on change,
unpublished content excluded, tenant isolation on every endpoint, scope enforcement. Webhook
delivery test.

### Risks
Large tenants: stream the pack to disk, no in-memory build. Untrusted content: chunk text is
escaped as data and never executed or treated as instructions.

## 2. Complete published OpenAPI plus PHP SDK (7.3)

### Goal
Every operation has a typed 2xx response and a stable `operationId`, the spec is published, and a PHP
SDK exists next to the TypeScript one.

### What exists
After #299 (S5), 466 of 582 operations have typed 2xx responses; the CLI coverage matrix reports
0 missing. The TypeScript SDK in `front/sdk` is generated from the spec.

### Design
- Add response schemas and operation ids for the remaining operations, grouped by package, in
  `Swagger/ResponseSchemas.php` style.
- CI gate: fail when an operation lacks a typed 2xx response or an `operationId`, with an explicit
  allowlist for streaming and binary endpoints.
- Publish the spec as a versioned file in the docs site build, with a changelog of added and
  removed operations between releases.
- PHP SDK in `sdk-php/`, generated with openapi-generator (PHP, Guzzle), with a thin hand-written
  wrapper for authentication, idempotency keys and pagination. composer metadata, README, and tests
  against a mock server built from the spec.

### Dependencies
openapi-generator, Java in CI only (dev dependency, Apache-2.0, not shipped). Starts after #299
merges, otherwise generated files conflict.

### Tests
Spec gate test; SDK contract tests for 10 representative operations; generation is reproducible
(CI regenerates and diffs).

## 3. create-ulams (7.3)

### Goal
`npx create-ulams my-academy` scaffolds a working local stack with seed data.

### Design
- npm package `create-ulams`: prompts for name and ports (with `--yes` for defaults), writes a
  compose project and an `.env` with generated private keys, starts it through the launcher from
  Phase 8 (ADR 0102, Proposed), then seeds demo data and prints the URLs.
- Reuses the `ulams` CLI launch commands; no second launcher.
- Idempotent: running in a non-empty directory refuses without `--force`.

### Tests
Unit tests for scaffolding and key generation; an e2e in CI against the compose stack; `npm pack
--dry-run` in CI. Publishing to npm stays blocked on #77.

### Risks
ADR 0102 may change before acceptance; the package follows it. Keys are written with mode 600 and
never printed.

## Open questions (defaults used until the owner answers)

1. Knowledge pack storage: the existing files disk, R2 in production. (Default used.)
2. PHP SDK generator: openapi-generator. (Default used.)
3. create-ulams builds on ADR 0102 as Proposed. (Default used.)
4. Should uncited chunks be exported with `uncited: true`, or excluded from packs? (Default:
   exported and flagged.)
5. Tenant-wide export: one pack per tenant, or one per course plus an index? (Default: per course
   plus an index.)

## Order and PRs

1. `phase-7/knowledge-export`
2. `phase-7/openapi-php-sdk` (after #299 merges)
3. `phase-7/create-ulams`
