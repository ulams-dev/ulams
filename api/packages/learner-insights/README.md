# learner-insights: learner signals, transparent risk rules and personal help

Turns learning activity into one append-only signal stream mapped to the blueprint elements of
courses built with the Course Builder (and to `topic:<id>` / `question:<id>` keys for other
courses). Later milestones score the stream with transparent rules, send rate-limited nudges, offer
grounded personal remediations and show authors where learners struggle. **Off by default per
tenant.** Records: ADR 0057 to 0062 (proposed); plan `docs/plans/phase-4.md`.

Shipped so far (milestone M4.1, the signal stream):

- `learner_signals` table (tenant database, append-only; a PostgreSQL trigger rejects `UPDATE`),
  unique on `(source, source_ref, type)` so every ingestion path is idempotent.
- `SignalRecorder`: whitelisted `data` keys per type (`Signals/SignalType.php`), no free text,
  element resolution through `Ulams\CourseBuilder\Services\ElementLookup` (read only), queued
  after commit (`RecordSignalJob`, queue `LEARNER_INSIGHTS_QUEUE`).
- Listeners for progress (`TopicProgressUpdated`, `TopicFinished`, `CourseStarted`,
  `CourseFinished`), GIFT quizzes (attempt started and finished, one signal per graded answer),
  SCORM completion and logins.
- `POST /api/learner-insights/signals` for player signals (`media.replayed`, `media.seeked_back`).
- Commands: `learner-insights:backfill` (from existing progress, enrolments and quiz data, idempotent),
  `learner-insights:timeline --user= --course=`, `learner-insights:prune` (daily, retention 180 days).

Milestone M4.2, transparent scoring:

- `RiskScorer` contract (`Scoring/`), bound to `RuleBasedScorer` (version `1`): five rules, each with a
  documented default in `config/learner_insights.php`: `inactivity`, `failed_attempts`,
  `slow_element` (needs a cohort of 10), `abandoned_element`, `repetition` (low weight, never
  changes the course status alone). Pure: it reads a `LearnerCourseContext` and returns an
  `Assessment` whose `Reason`s are message keys plus numbers, rendered in the learner's language
  (`resources/lang`).
- Thresholds per tenant (administrable settings `learner_insights.rules.<rule>.<param>`) and per
  course (`GET/PUT /api/admin/learner-insights/courses/{course}/settings`, permission
  `learner_insights_manage`).
- `StatusService` keeps `learner_statuses` (course row has `element_key` = ''), an append-only
  `learner_status_transitions`, and fires `LearnerStruggling`, `LearnerAtRisk` and `LearnerRecovered`
  once per transition, with ids only. `EvaluateLearnerJob` (unique per learner and course,
  debounced 60 s) runs after new signals; `learner-insights:evaluate` (03:30) covers time-based
  rules; `learner-insights:cohort-stats` (03:00) computes median times per element.
- A status is information only: nothing reads it for grades, access, certificates or enrolment.

Privacy: nothing is recorded while `learner_insights.enabled` is false, for a learner who set the
user setting `insights.enabled` to false, or, with `learner_insights.require_consent`, before the
user setting `insights.consent` exists. Signals never reach an LLM.

- Settings, env variables, commands: `front/docs-site/src/content/docs/admin/learner-insights.mdx`

```bash
vendor/bin/phpunit --testsuite learner-insights
php artisan learner-insights:backfill --course=1 --dry-run
```

The package reads LMS data and changes it only through domain services;
`tests/Unit/GuardsTest.php` enforces this, no model names in `src/`, and ids-only events.
