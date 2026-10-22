# 0192. Course releases are recorded applies, and course variants are branches of one builder session applied as separate LMS courses

- Status: Accepted (2026-10-22)
- Date: 2026-10-22 (accepted by the owner 2026-10-22, with the plan's recommended defaults for Q1 to Q11)
- Plan: `docs/plans/course-versions-and-variants.md`; TODO Phase 3.1 and Phase 4.4 (new)

## Context and problem statement

The owner asked for two linked features (2026-10-22):

1. **Course versions with a visual diff.** A course changes through author edits, chat edits, accepted Living
   Course updates, course-as-code pushes and insights proposals. Each change is reviewed on its own, but nobody
   can see which versions learners actually got or compare any two of them.
2. **Course variants by level and role.** Every learner gets the same course. The builder should ask which
   levels and roles to cover and draft several variants at once, with shared sources and citations, each reviewed
   as a diff against the base, and learners routed to the matching variant.

What exists: `course_builder_versions` is an append-only history of blueprint documents per builder session
(ADR 0010) with `parent_id`, `kind`, `status`, `source_revisions` and `diff_from_parent`; a session points to
one LMS course (`course_id`) and records `current_version_id` and `applied_version_id`. `BlueprintDiff` compares
two documents by stable element id, and `GET versions/{id}/diff?against=` already compares any two versions of a
session. `ElementMerge` (CAC-4, ADR 0160) does a three-way merge by element id. Progress rules (ADR 0033) apply
updates in place; entitlements are per course (ADR 0130).

Two questions need a decision: what a "course version" is for learners and authors, and what a variant is in
the data model and in the LMS.

## Considered options

Versions:

- V1. A new `course_builder_releases` table: one row per successful apply of an approved version to the LMS
  course (per variant), with a sequential number, trigger, approver and source revisions. Compare works on any
  two versions; releases are the default list.
- V2. A `released_at` flag on `course_builder_versions`.
- V3. Snapshot the LMS course tables on every apply.

Variants:

- A. One builder session holds the sources and the base blueprint; each variant is a branch (a `variant_id` on
  versions) with its own current and applied version, applied as **its own LMS course** through the existing
  `BlueprintApplier`. Variants are grouped as one course family for the catalogue, entitlements and routing.
- B. Variants are topic sets inside one LMS course, with per-learner visibility.
- C. Variants are separate builder sessions that copy the sources.
- D. Variants are learner-scoped overlays, like personal remediations (Phase 4.1).

## Decision

**V1 and A.**

- A release is created by the apply step, never by hand: it marks what learners saw from when to when. The
  version history keeps every proposal, rejection and undo; releases are the readable timeline. V2 was rejected
  because one version can be applied to several variants and re-applied after an undo, and a flag cannot hold
  that; V3 because it duplicates data the blueprint already holds and bypasses the element ids that diffs and
  citations use.
- A variant is a branch of one session. It shares the session's sources, fragments and citations, keeps the base
  element ids for every element it inherits or changes, and gets new ids only for new elements, so
  `BlueprintDiff` compares a variant with the base by id with no mapping table. Rebasing a variant on a newer
  base is `ElementMerge::merge(oldBase, variant, newBase)`: disjoint changes merge, overlaps are shown to the
  author as conflicts.
- Each variant is applied to its own LMS course. Progress, completion, certificates, H5P, SCORM and quiz attempts
  keep working unchanged because each learner is in one ordinary course. The family (`course_variant_groups`)
  hides non-default variants from the catalogue, and one entitlement source per family grants access to all of
  its variant courses through `EntitlementService` (the LMS still owns access; ADR 0130 is unchanged).
- Option B was rejected because Wellms completion counts every topic of a course, so it would need changes deep
  in `courses`, quiz and certificate logic and risks H5P and SCORM behaviour. Option C was rejected because
  copied sources drift: one source change would need N analyses and N reviews. Option D was rejected because
  overlays are per learner and unreviewed by design, while variants must be approved by the author.

## Consequences

- Good: no change to the LMS progress model; variant courses are normal courses for every existing feature.
- Good: one Living Course analysis per source change; a patch to an element that a variant inherits unchanged
  is reused for that variant without another model call.
- Good: the compare screen, the CLI and the MCP tool share one diff payload for versions, releases and variants.
- Bad: one builder session now maps to several LMS courses; `course_builder_entity_map` needs `variant_id` in
  its unique key, and every place that reads `session.course_id` must say which branch it means.
- Bad: switching variant means enrolling in another course; progress carries over only for elements whose
  content is identical (same element id and fingerprint), and that copy goes through the courses domain service.
- Bad: generating N variants costs about N times the lesson generation; mitigated by a per-course cap, a cost
  estimate before the run and the session budget.
- Open (plan section 18): whether certificates and mandatory training count any variant or a pinned one, the cap
  on variants, and whether learners may switch freely.
