# 0028. Generation stages, grounding check and quiz support check

- Status: Accepted (2026-10-09)
- Date: 2026-10-09

## Context and problem statement

The plan lists lessons, quizzes, a grounding check, metadata and the apply as stages. Their order
matters for cost and quality: quizzes should be written from grounded lesson text, and the grounding
regeneration must not invalidate quizzes.

## Decision

- Stages run in this order, each as resumable steps keyed by element id:
  lessons → grounding → quizzes (+ final test) → metadata → assemble (content version, apply
  proposal). A stage opens only when every step of the previous one is done; a failed step sets
  the run to "needs attention" and can be retried alone.
- Lessons run in a concurrency window (`COURSE_BUILDER_LESSON_CONCURRENCY`, 4): the first steps are
  queued, each finished step queues the next. No extra infrastructure is needed for the limit.
- Grounding (light profile) sees only the fragments a lesson cites. Unsupported claims trigger one
  regeneration of the lesson with the claims as feedback and one more check; what remains becomes a
  flag shown in the workspace, the apply summary and the success screen.
- Every quiz question must share at least two content words (numbers included) between its stem
  plus correct answers and its cited fragments; otherwise the validator asks for a repair. This is a
  cheap guard, not a proof of correctness.

## Consequences

- Good: quizzes are written against the final lesson text; flags make doubtful content visible
  before publishing.
- Bad: lessons are not streamed into quizzes in parallel, so a run takes a little longer.
