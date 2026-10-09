# 0064. The studio reports "applied" only from authoritative state

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

Approving a chat edit approves the version, makes it current and queues a re-apply run
(`RunService::reapply`). The patch card switched to "Approved and applied" as soon as the patch was
approved, and the `applied` custom event and the session `status` were read on their own. Both can be
true while `applied_version_id` still points at the previous version, so the studio (and the e2e test
that reads the session right after) could report "applied" before the queue had caught up.

## Decision

The authoritative signal is the session itself: the apply is settled when `status = applied` and
`appliedVersionId = currentVersionId` (`isApplied()` in `@ulams/sdk`). The studio:

- shows "Approved; applying to the course…" on an approved patch, and withholds the "Course overview" and
  "See your course" links, until the stream's state (STATE_DELTA) satisfies `isApplied()`;
- after the `applied` event, reads the stream state and, when it lags, polls the session endpoint
  (`sessions.waitForApplied()`, 500 ms, 20 s) instead of trusting the event;
- makes the course overview page wait up to 8 s for a lagging apply before rendering.

## Consequences

- Good: no UI says "applied" while the academy still holds the older version; the SDK helper is reusable.
- Bad: an extra session read per applied event when the stream is slower than the event; a stuck queue
  keeps the card on "applying" instead of lying.
