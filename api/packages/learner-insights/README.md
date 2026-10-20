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
  SCORM completion, H5P results and logins.
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

Milestone M4.5, personal remediations (courses built with the Course Builder only):

- `LearnerStruggling` creates or reuses the cached remediation of the struggle pattern
  (`sha256(element, applied version, rule, kind, language)`: explanation and practice after failed
  answers, explanation and worked example after slow or abandoned elements), assigns it to the learner
  with the reasons, and queues `GenerateRemediationJob` on a miss. A second learner with the same
  pattern costs nothing.
- `RemediationGenerator` sends only the element, its cited fragments, the lesson objectives and a generic
  difficulty (no learner data; `ai_calls.user_id` is null). The output must follow the schema, cite only
  the provided fragments, pass the markup checks and the Course Builder `grounding` task; an unsupported
  claim triggers one regeneration, then the remediation is `failed` and nothing is shown. Tenant and
  course monthly budgets and a daily cap of new generations per learner apply.
- `GET /api/learner-insights/courses/{course}/topics/{topic}/support`, `POST .../assignments/{id}/feedback`,
  `/hide` and `/reflection` (Feynman feedback on the learner's own text, limited per day, text not stored).
- Remediations retire when the element's applied version changes (`learner-insights:retire-remediations`).
- The learner UI components are not part of this package; the endpoints return a declarative `document`.

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
