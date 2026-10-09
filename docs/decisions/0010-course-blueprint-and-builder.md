# 0010. Course Builder: a versioned Course Blueprint applied through domain services

- Status: Accepted (2026-10-09)
- Date: 2026-10-08

## Context and problem statement

The AI Course Builder turns source documents and an interview into a course, and later lets the
author refine any element by chatting. The spec requires: every generated element cites source
fragments; AI proposes and the author approves every change as a diff; LMS entities are changed only
through domain services; generation is queued, resumable and streamed; every change is a version with
undo and restore. Phase 3 (Living Course) needs to find every element that depends on a changed source
fragment. The existing import/export format creates courses only, never updates them, and drops quiz
questions, so it cannot serve as the source of truth.

## Considered options

1. A **Course Blueprint**: one schema-validated JSON document per version, owned by a
   `course-builder` package, applied to the LMS by an applier that calls the existing repositories and
   services, with an entity map from blueprint element IDs to LMS IDs.
2. Generate straight into LMS entities and keep the history in an audit table.
3. Extend the courses-import-export format and re-import on every change.

## Decision

Option 1. `api/packages/course-builder` (`Ulams\CourseBuilder`):

- **Source Document**: uploaded MD, PDF or DOCX is normalised to Markdown and split into fragments
  with stable IDs (`frg_…` derived from the heading position, not the text; a content hash is stored
  separately for change detection).
- **Course Brief**: the interview answers, schema-validated and editable; each field records whether
  the author or a default decided it.
- **Course Blueprint v1** (`course-blueprint/v1.json`): course, modules, lessons with blocks, quizzes
  and a final test, objectives, page documents (A2UI-shaped, in the `@ulams/ui` catalogue). Element IDs
  are ULIDs assigned by our code, never by the model. Every block and question cites at least one
  fragment and serves at least one approved objective.
- **Versions**: each pipeline stage and each chat edit creates a version with origin (`ai`, `author`,
  `restore`), status (`proposed`, `approved`, `rejected`, `superseded`) and an element-aware diff from
  its parent. Undo, redo and restore move along or extend the chain.
- **Gates**: the outline with objectives is approved before content is generated; the apply is
  approved before anything is written to the LMS; publishing is a separate action.
- **Applier**: a queued job running as the author calls `CourseRepositoryContract`,
  `LessonRepositoryContract`, `TopicRepositoryContract::createFromRequest`,
  `GiftQuestionServiceContract` and `CourseServiceContract::sort` (and the pages service for page
  documents). GIFT is rendered from structured questions by our code. Changed elements are updated,
  removed ones deleted, new ones created, using the entity map. A test fails if the package writes LMS
  tables directly.
- **Element chat**: the model returns a replacement subtree for the selected element, validated
  against that element type's sub-schema with IDs preserved; the server computes the diff shown to the
  author.
- Builder data lives in the tenant database (ADR 0007).

## Consequences

- Good: one source of truth for diffs, citations, versions, the CLI and course-as-code (Phase 7.1) and
  impact analysis (Phase 3).
- Good: LMS behaviour (events, permissions, progress) stays intact because only domain services write.
- Good: the author approves every AI change; nothing reaches learners without an explicit apply and
  publish.
- Bad: edits made in the admin after an apply are not in the blueprint; until two-way sync (Phase 7.1)
  the applier detects drift by comparing the entity's `updated_at` with the last apply and asks before
  overwriting.
- Bad: the blueprint schema becomes a public contract that needs versioning and migrations.
