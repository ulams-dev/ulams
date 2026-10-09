# 0034. The quiz time limit default is read from `ulams_gift_quiz.max_quiz_time`

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

The administrable setting `ulams_gift_quiz.max_quiz_time` is registered by the GIFT topic type, but
`QuizAttemptService` read `ulams_gift_quizmax_quiz_time` (the dot was missing), so the setting was never
applied and every quiz without its own `max_execution_time` closed after the hard-coded 120 minutes. The
existing test set the same misspelt key, so it passed.

## Decision

Read the registered key. Precedence stays: the quiz's own `max_execution_time`, then the setting, then 120
minutes. The test sets the real key.

## Consequences

- Good: the admin setting works; the default of 120 minutes is unchanged when nobody set it.
- Bad: installations that already stored a different value now see attempts close at that value.
