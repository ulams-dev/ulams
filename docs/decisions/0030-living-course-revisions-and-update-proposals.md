# 0030. Living Course: source revisions and update proposals on top of the Course Blueprint

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

Phase 3 keeps a course in sync with its sources: when a source changes, the affected course elements
get AI patches that the author reviews as one proposal, and accepted changes reach the LMS without
losing learner progress. Phase 2 (ADR 0010) gives us sources with stable fragment IDs, citations per
element, versions with element-aware diffs, and an applier that writes through domain services. But a
source has no history: `SourceIngestor::ingest()` replaces the fragments of a source in place and
`course_builder_fragments.id` is the primary key, so the text a course was written from is lost on
re-ingest.

## Considered options

1. A new `living-course` package that stores **source revisions** with their own fragment copies,
   keeps the live fragment table on the **synced** revision, and turns accepted proposal items into
   a blueprint version of kind `update`.
2. Make `course_builder_fragments` revision-keyed in place and build Living Course inside
   `course-builder`.
3. Re-run the Phase 2 generation pipeline on the new source and diff the resulting blueprints.

## Decision

Option 1.

- `api/packages/living-course` (`Ulams\LivingCourse`) owns connections, revisions
  (`living_course_revisions`, `living_course_revision_fragments`), fragment changes, proposals and
  items, progress rules, learner notices, staleness and the audit trail. It depends on
  `course-builder` and uses its services (ingestion split into convert / build rows / write live,
  `Blueprint`, `PatchSchemas`, `Checks`, `VersionService`, `BlueprintApplier`, `EventLog`).
- Fragment IDs are computed as in Phase 2 with the same source row id, so unchanged positions keep
  their IDs across revisions. Revision 1 is backfilled from the live fragments.
- Each connection keeps `synced_revision_id` and `latest_revision_id`. The live fragment table always
  holds the synced revision; Phase 2 prompts, chat edits and citation popovers are unchanged.
  Fragments that disappear stay readable through a `FragmentArchive` contract.
- A proposal belongs to one source (one open proposal per source) and holds items per impacted
  element: `update`, `citation_remap`, `remove`, `no_change`, `manual` (AI disabled), `uncovered`.
  Items are decided one by one, all at once, or rejected together.
- Apply builds a new version of kind `update` from the session's **current** version plus the
  accepted items (conflicts if the element changed since the analysis), stores
  `source_revisions` on the version row (the blueprint schema does not change), applies it through
  `BlueprintApplier`, then promotes the new revision to the live table.
- Rejecting a whole proposal acknowledges the revision (the synced pointer advances and elements are
  marked `dismissed`), so the same changes are not proposed again.
- The model writes patches only, one call per lesson group, under per-proposal and per-source budgets
  with an estimate shown before analysis. Detection and impact analysis are deterministic
  (ADR 0031) and work with AI disabled.

## Consequences

- Good: Phase 2 code paths keep their behaviour; the course always cites the text it was written
  from; history of every source is kept for the audit trail and evals.
- Good: proposals reuse the Phase 2 diff, validation and apply model, so undo/restore work for
  updates too.
- Bad: fragments are stored once per revision (text duplicated); acceptable at documentation sizes,
  prunable later for revisions older than the oldest referenced version.
- Bad: two fragment stores (live and per revision) must be kept consistent; only
  `RevisionService::promote()` writes the live table for synced sources.
- Rejected option 2 rewrites every Phase 2 fragment query; option 3 regenerates unchanged content,
  costs far more and destroys author edits.
