# 0025. How a Course Blueprint maps to LMS entities

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

ADR 0010 fixes that the applier uses domain services and an entity map; it does not fix the shape.
The LMS model is course → lessons (optionally nested) → topics; quizzes are GIFT quiz topics with
question rows; pages live in the `pages` package.

## Decision

- Course → course (created `draft`, the author as author; title, subtitle, description, language,
  level, target group, duration).
- Module → lesson (ordered); blueprint lesson → a RichText topic with the lesson Markdown, followed
  by a GIFT quiz topic when the lesson has a quiz; question → GIFT question (score 1), GIFT rendered
  by our code with escaping.
- Final test → an extra "Final test" lesson with one GIFT quiz topic (pass score from the brief).
- Lesson Markdown: the blocks in order (callouts as quotes), raw HTML removed outside code, and a
  "Sources" list of the cited sections at the end. Learners see where content comes from; the
  blueprint keeps the exact block-level citations.
- Landing document → a page `course-<id>` in the `pages` package, inactive, content = the JSON
  document (until ADR 0008's page storage replaces it).
- The entity map stores a fingerprint of each element as applied; a re-apply deletes removed
  elements first, then creates or updates changed ones, then orders lessons and topics through
  `CourseServiceContract::sort`, all in one transaction as the author.

## Consequences

- Good: the course works in every existing front and report; nothing new is needed on the learner
  side in M2.1.
- Bad: topic deletion through the repository leaves the content row (RichText/GiftQuiz) behind; that
  is existing LMS behaviour.
- Bad: edits made in the admin after an apply are overwritten by the next re-apply of that element;
  drift detection (ADR 0010) is a follow-up.
