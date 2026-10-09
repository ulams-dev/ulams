# 0079. Course-as-code: Markdown with directives + YAML, Blueprint v2 and a committed sync base

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/cli.md` (9); owner question #78. Implemented after Phase 3.

## Context and problem statement

Spec 7.1 asks for a file format (Markdown + YAML/JSON, JSON Schema) and `init`, `validate`, `preview`,
`push`, `pull`, `diff`, `publish` with two-way sync. The Course Blueprint v1 (ADR 0010) is the basis,
but it has only RichText lessons and requires citations on every block.

## Decision

- Folder: `course.yaml`, `modules/NN-slug/module.yaml`, lessons as Markdown with YAML front matter,
  blocks as container directives `:::kind{#ulid cite="frg_…" origin=ai|author}`, quizzes as YAML,
  `assets/`, `sources/`.
- Blueprint v2 adds a `contentType` enum and asset references for SCORM, H5P, LiaScript, video and file
  topics; v1 stays accepted.
- Blocks with `origin: author` may omit citations; AI-originated blocks must cite (default, pending #78).
- `.ulams/state.json` (committed) holds the base version and per-element hashes; push and pull merge
  three ways per element id; conflicts are written as diff files and fail with `SYNC_CONFLICT`, never
  overwritten silently.
- Push and pull go through new endpoints that import a blueprint as a builder version and export a
  course as a blueprint, so LMS entities change only through the builder's apply service.

## Consequences

- Good: Git review of course content with stable element ids; reuses the Phase 2/3 diff model.
- Bad: a v2 schema and two new endpoints; rename and move handling is designed when Phase 3 merges.
