# 0057. learner-insights package: an append-only signal stream keyed by blueprint element IDs

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/phase-4.md` (section 5)

## Context and problem statement

Personalisation needs learner activity from the sources mapped in the Phase 0 audit: progress,
quizzes, SCORM, xAPI/cmi5, H5P and logins. These live in different tables, mostly as latest state
without history. Some fire events; the LRS fires none. The spec asks for a single append-only stream
mapped to the same element IDs used for citations and updates, processed by queues, with a backfill.
`escolalms/recommender` must not be used (ADR 0006).

## Considered options

1. A new `learner-insights` package with one `learner_signals` table. It is fed by listeners and
   model observers after commit, with whitelisted payloads and element keys from the builder entity
   map.
2. Store full xAPI statements for everything in the LRS.
3. Read the source tables directly at scoring time.

## Decision

Option 1:

- **Fields.** Each signal has user, course, topic, `element_key` (blueprint ULID, or `topic:<id>` /
  `question:<id>`), type, value, small whitelisted `data`, source, `source_ref` (unique, for
  idempotency) and `occurred_at`.
- **Append-only.** Enforced by a PostgreSQL trigger. Deletes happen only through retention and
  erasure.
- **Reverse lookup.** `course-builder` adds a reverse index and an `ElementLookup` read service.
- **Client signals.** Media replays come from a rate-limited client endpoint.
- **Backfill.** Idempotent.
- **When nothing is recorded.** No signals when the module is off or the learner opted out.

## Consequences

- Good: one queryable history that can be explained, tested and pruned, aligned with citations and
  Living Course elements.
- Bad: duplicated data (sources plus signals), and volume that needs pruning and possibly
  partitioning later.
