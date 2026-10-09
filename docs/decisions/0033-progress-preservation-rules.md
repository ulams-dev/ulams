# 0033. Progress preservation rules for content updates

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

An accepted Living Course update changes lessons and quiz questions of a live course. The spec says:
reuse the existing mechanism, a minor edit keeps completion, a changed quiz answer marks the question
for re-attempt, and a past score never changes silently. The Phase 0 audit found that progress
survives only in-place edits: deleting a topic hard-deletes `course_progress`; deleting a GIFT
question cascades its answers; `QuizAttempt::max_score` is computed live from the current questions,
so adding or removing a question changes the percentage of past attempts; and adding a topic clears
`course_user.finished` on the next progress update.

## Decision

- Content updates never revoke completion, never change a stored score or attempt, never delete
  learner data and never regrade automatically.
- Updates are applied in place through the domain services (topic and question ids kept).
- Removal of a topic or lesson with learner progress deactivates it; removal of a GIFT question with
  answers archives it (`topic_gift_questions.archived_at`, excluded from new attempts). Without
  learner data they are deleted as before (`RemovalPolicy` contract in the applier).
- `topic_gift_quiz_attempts.max_score` is snapshotted when an attempt starts; the accessor uses the
  snapshot when present.
- A question whose correct answer changed (decided by code) creates a re-attempt notice for every
  learner who answered it; the learner may take one extra attempt beyond `max_attempts`
  (`QuizAttemptAllowanceContract`); the old attempt stays on record.
- Major lesson changes create an "Updated since you completed it" notice for learners who started or
  completed the topic; minor changes and citation updates are silent.
- Learners who finished the course before an update keep `finished` when lessons are added
  (`CourseCompletionGuardContract`, bound only for courses built with the Course Builder); they see
  "New since you finished".
- A reset of completion for changed lessons is not offered; compliance re-completion belongs to
  re-certification (Phase 6.1).

## Consequences

- Good: an accepted update can be proven not to change any learner's record (regression test with a
  table checksum).
- Good: the contracts default to today's behaviour, so existing courses and suites are unaffected.
- Bad: three small changes in vendored LMS packages (`topic-type-gift`, `courses`) that upstream
  never had.
- Bad: learners who finished before an update may not have seen new material; the notice tells them,
  and compliance customers will need Phase 6.1 re-certification for stricter rules.
