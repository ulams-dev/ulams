# 0150. PWA offline mode: saved lessons and idempotent, monotonic progress sync

- Status: Proposed
- Date: 2026-10-21
- Plan: `docs/plans/phase-5-remaining.md` 4.4 (milestones P5R-4, P5R-5; open questions Q1 to Q4)
- TODO: Phase 5 "PWA offline mode with tested sync/conflict rules"

## Context and problem statement

The spec asks for a PWA that downloads lessons, records progress offline and syncs it back with defined and tested
conflict rules. The reference frontend (`front/web`, Astro SSR with a learner BFF and an httpOnly session) has no
service worker. The progress API sets absolute statuses (`PATCH api/courses/progress/{course}`, which can downgrade a
completed topic) and counts time by server clock (`PUT .../ping`), with no client timestamps and no idempotency, so
replaying offline calls would lose time and could undo work done on another device. Content-origin frames (H5P, SCORM,
LiaScript, Interactive) live on another origin, and quizzes are scored and timed on the server (ADR 0082).

## Considered options

1. Replay the existing calls from a queue when back online.
2. A new sync endpoint taking client events with ids and timestamps, merged by fixed rules.
3. A full offline database with CRDT-style merge of all learner state.

## Decision

Option 2.

- **Worker.** A hand-written service worker at `/` on tenant hosts only; it skips the platform host, the studio,
  `/cli`, `/lti` and every BFF write while online. Hashed assets are precached; pages are network-first with a cached
  fallback and an `/offline` page; lessons are cached only when the learner saves a course for offline.
- **Offline scope.** Rich text, Markdown, image and PDF lessons, the outline and the last known progress. Quizzes,
  the tutor, search, H5P, SCORM, LiaScript, Interactive and video need a connection and say so.
- **Sync.** `POST /api/courses/progress/sync {sentAt, events[]}`, implemented through `ProgressService`. Rules:
  1. every event has a client ULID and is applied once (ids kept 30 days);
  2. statuses merge monotonically (complete > in progress > incomplete), never downgraded; restarting a course is
     online-only;
  3. offline seconds are capped (60 min per event, three times the estimated duration per day);
  4. client clocks are corrected by `now - sentAt`; events older than 14 days or in the future are rejected;
  5. deadlines are checked at the corrected event time;
  6. removed or inactive topics are ignored; topics changed by a Living Course update keep their progress;
  7. revoked access rejects the events and removes the saved course;
  8. domain events carry the event time, so learner signals are timed correctly.
- **Privacy.** Logout sends `Clear-Site-Data`; caches are keyed by tenant and user.

## Consequences

- Good: no dependency (no Workbox, no IndexedDB library); rules are pure functions with one test each.
- Good: a second device or a late sync can never undo progress.
- Bad: interactive and quiz content is not offline; learners see a clear notice instead.
- Bad: a new table (`progress_sync_events`) and a second write path for progress, kept thin over `ProgressService`.
