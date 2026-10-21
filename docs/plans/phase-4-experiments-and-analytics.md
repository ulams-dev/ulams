# Phase 4 plan: experiments, surveys and the insights studio

Status: draft. The owner approved the 2026-10-21 batch of plans in advance ("All approved"), so every open
question in section 14 has a recommended default that implementation will use unless the owner says
otherwise. ADRs 0180 and 0181 are Proposed with this plan.

This plan covers what is still open in the **Learner Insights lane** after `docs/plans/phase-4.md` (M4.1 to
M4.8, merged in #277, #281, #283, #287, #288, #289 and the learner UI PRs #314 to #325), plus the Phase 2.7
learning-science items that `docs/plans/leftovers-0-2.md` (L2-23) assigned to an `experiments` package that
was never built. It does **not** cover Course Builder lane work (critics, simulations, AI-composed layouts,
evals of the builder): another lane owns those. Where an item here needs builder output (a Layout topic as an
experiment arm), it only consumes what the builder already approved and applied.

Facts below come from `main` at `4bde7dd1f` (2026-10-21). Paths are relative to the repo root.

---

## 1. Goal

> Authors and admins see, per course, where learners struggle, and can turn a high-struggle element into a
> cited Living Course proposal they review as a diff. Tenants that opt in can run A/B experiments per course
> whose primary metric is delayed retention 3 to 7 days later, with standard surveys (SUS, UEQ-S, NASA-TLX)
> and behavioural metrics as secondary measures, results shown to authors only when the sample is large
> enough to mean something. Learners are told, can opt out, and in consent mode are asked first. No learner
> signal ever reaches an LLM; experiments never touch grades, access or certificates.

## 2. TODO items covered (quoted from `docs/ROADMAP-TODO.md`)

| # | TODO item (verbatim) | State today | Coverage here | Milestone |
|---|---|---|---|---|
| T1 | "Author analytics; high-struggle elements → update proposals (partial: author and admin analytics API merged, #283; the Living Course proposal from insights, `ProposalService::createFromInsights`, is open)" | API merged; no proposal entry point | Insights proposals in `living-course` (ADR 0181), studio button and evidence card | LX-2 |
| T2 | "(new) Per-course analytics dashboard for authors and admins: progress, completion, quiz performance, at-risk learners and the sections that confuse people, per course (Phase 4, M4.6 author analytics; API merged, #283, studio Insights page not built; …)" | API merged; no UI | Studio course Insights page; admin course tab | LX-1 |
| T3 | "Privacy: per-tenant toggle, retention, explanations, minimal data to LLM (partial: … minimal data to the LLM waits for remediations and the tutor)" | Remediations and tutor merged | Minimisation guard tests for every learner-related task, redaction of e-mail and phone numbers in learner text, evidence-only `insights_improve` | LX-1, LX-10 |
| T4 | "Rule unit tests, synthetic learner journeys, tenant isolation (partial: rule unit tests and the journey scenario test merged, #277; a dedicated tenant isolation suite is not confirmed)" | Only `SettingsApiTest` checks one route across tenants | Route-complete tenant isolation suite for `learner-insights`, `tutor`, `experiments`, plus journeys with experiments | LX-1, LX-4 |
| T5 | "(new) Remediation holdout (20 % get no remediation) and the adaptive interface presentation profile, backend (… the experiments package and the A/B and delayed-retention measure are not built)" | Holdout is `crc32('holdout:'.userId) % 100` in `RemediationService::inHoldout()` | Holdout and adaptive interface become experiment kinds of the new package | LX-4 |
| T6 | "(new) Partition `learner_signals` by month when a tenant exceeds 50M rows" | Not built | Threshold check plus an opt-in partitioning command | LX-11 |
| T7 | "(new) Remediations for courses not built with the Course Builder" | Not built (builder courses only) | Grounding on the tutor's course chunks | LX-9 |
| T8 | "(new) `TopicProgressUpdated` event in `courses` for non-completion progress (ids only)" | **Already built**: `api/packages/courses/src/Events/TopicProgressUpdated.php`, fired in `CourseProgressRepository` (line 93), tested in `courses/tests/Repositories/CourseProgressEventTest.php`, consumed by `RecordSignals::topicProgressed` (#277) | Ticked in the TODO with this plan's commit (evidence above); no work | — |
| T9 | "Reasons for every recommendation · AI on/off and provider per tenant (partial: learner-side reasons …; per-tenant provider choice not built)" | Reasons on learner surfaces; provider only through operator env overrides (ADR 0063) | Reasons coverage test; tenant AI status and per-feature switches in the admin settings screen; provider stays operator-level (Q7) | LX-3 |
| T10 | "AI content labelling · documented data flows, no training on customer content (partial: AI labels …; the data-flow documentation is open)" | Learner Insights data-flow page exists (`operators/dpia-learner-insights.mdx`), no page for the other tasks | Platform-wide AI data-flow page generated from the task registry, with a drift test | LX-10 |
| T11 | Phase 2.7: "Surveys with SUS, UEQ, NASA-TLX + behavioural metrics" | Not built | Built-in instruments in `experiments` | LX-7 |
| T12 | Phase 2.7: "Built-in A/B experiments per course; delayed retention (3–7 days) as primary metric" | Not built | `experiments` package, retention checks | LX-4, LX-5, LX-8 |
| T13 | Phase 2.7: "Results visible to authors; opt-in per tenant, consent where required" | Consent model confirmed (#58 closed: opt-in per tenant, notice plus opt-out, consent only in consent mode) | Results page with guard rails; tenant opt-in; consent mode | LX-4, LX-6 |
| T14 | "the experiments package" referenced by T5 and by `docs/plans/phase-4.md` §13.4 and L2-23 | Does not exist | `api/packages/experiments` (`Ulams\Experiments`) | LX-4 |

Also part of the brief and planned here: the admin "Learner Insights" settings screen (open since M4.2, see
`phase-4.md` §19) in LX-3.

### Non-goals

Builder critics, simulations and AI-composed layouts (Course Builder lane); sequential or Bayesian testing;
cross-course experiments; multi-armed bandits; an ML risk scorer; experiments on assessments; any analytics
SaaS; learner-facing experiment results.

---

## 3. Current state (what this plan builds on)

- **Learner Insights** (`api/packages/learner-insights`): signals, rules, statuses, nudges, remediations,
  outcomes, presentation profiles, privacy (`Services/Privacy.php`: export, erase, prune), analytics
  (`Services/Analytics.php`: `summary`, `elements`, `learners`, `timeline`, minimum group size enforced on the
  server). Routes in `src/routes.php`: learner group `api/learner-insights/*` (13 routes) and admin group
  `api/admin/learner-insights/*` (8 routes). There is **no improve endpoint** and no studio or admin UI for the
  analytics.
- **Holdout**: `RemediationService::inHoldout()` uses `crc32('holdout:' . $userId) % 100 < holdout_percent`.
  No salt, so the same learners are the control group in every course and every tenant with the same ids;
  no exposure record; `OutcomeTracker::helpedVsHoldout()` compares recovery counts without intervals.
- **Living Course** (`api/packages/living-course`): proposals are bound to a source revision.
  `living_course_proposals.source_id`, `from_revision_id`, `to_revision_id` are NOT NULL; `ProposalService`,
  `Analysis/UpdateRequest`, `ApplyService::applyAccepted()` and `build()` read the revisions and their
  `FragmentChange` rows, and apply promotes `to_revision`. One open proposal per source. The review UI is
  `front/web/src/pages/studio/s/[id]/updates/{index,[proposal]}.astro`; the BFF allow-list is
  `LIVING_COURSE_RULES` in `front/web/src/lib/studio-rules.ts`.
- **Course Builder**: `ElementLookup` (read-only element and fragment lookups), `PatchService` (element chat
  edits), `VersionService`.
- **Tutor** (`api/packages/tutor`): `tutor_chunks` index (RichText and LiaScript Markdown for any course, blueprint
  blocks for builder courses; never quiz content).
- **Questionnaire** (`api/packages/questionnaire`): integer `rate` answers per user and question, question types
  `rate|text|review`; answers can be made visible to authors. No bipolar anchors, reverse scoring or instrument
  versions (why it is not reused: Q5).
- **GIFT** (`api/packages/topic-type-gift`): `QuestionStrategyContract::checkAnswer(array): CheckAnswerDto` per
  question type; `GiftQuestionServiceContract`.
- **Courses**: `TopicResource::extend(name, callable)` adds fields to program topics (used for `ai_assisted`).
- **Front**: studio pages per builder session under `front/web/src/pages/studio/s/[id]/`, nav in
  `front/web/src/layouts/Studio.astro`; learner privacy page `front/web/src/pages/account/personalisation.astro`;
  admin course form tabs in `admin/src/pages/Courses/form.tsx` (`TabNames`), settings pages in
  `admin/src/pages/Settings/`.
- **CI**: PHPUnit shards in `.github/workflows/ci.yml` (`PHPUNIT_SHARDS`); every testsuite in `api/phpunit.xml`
  must appear exactly once. A new `experiments` suite goes into the `living` shard.
- **Consent**: #58 closed with the ADR 0055 model. ADR 0055 (Proposed) stays the base record; ADR 0180 adds the
  assignment, exposure and statistics rules it left open.

---

## 4. Studio Insights page and admin tab (T2, LX-1)

### 4.1 Where

- **Studio**: `front/web/src/pages/studio/courses/[courseId]/insights.astro`, keyed by **course id**, not by
  builder session, so non-builder courses have a dashboard too. The session nav (`Studio.astro`) gains an
  "Insights" item when the session has a `courseId`, linking to this page. The page reuses the studio layout
  without the session-only items when the course has no session.
- **Admin**: `admin/src/pages/Courses/form.tsx` gets `TabNames.INSIGHTS = 'insights'` with a compact summary
  (status distribution, completion, the top 5 confusing sections) and "Open full insights" linking to the studio
  page. One full implementation, not two (Q3).

### 4.2 Content (from the existing API, `period` 7/30/90)

1. Header: course title, period switch, "Groups smaller than 10 learners are hidden" notice.
2. **Progress and completion**: enrolled, started, completed, median progress (from `summary`).
3. **Quiz performance**: per quiz first-attempt pass rate and mean score (from `summary`).
4. **Learners at risk / struggling**: counts always; the list (`learners`) only for holders of
  `learner_insights_view_individuals` or authors when `instructors_see_individuals` is on; otherwise a sentence
  explains why it is hidden.
5. **Sections that confuse people**: `elements` table sorted by struggle rate: label, struggle rate, remediation
  rate, recovery rate, top reasons (rendered from reason keys), learners in the group. Each row with
  `min_group_size` met and a builder element id has "Propose an improvement" (LX-2).
6. **Personal help and nudges**: counts and recovery rates; the holdout comparison moves to the experiment
  results page in LX-6.
7. **Experiments** (from LX-4): list of the course's experiments with status and a link to results.

Rates are numbers in text with a decorative bar (`RateBar`, no colour-only meaning). States: loading skeleton,
module off ("Learner Insights is off for this academy" with the admin link), small group, empty period, API error
with retry.

### 4.3 Files

| File | Change |
|---|---|
| `front/web/src/pages/studio/courses/[courseId]/insights.astro` | new page |
| `front/web/src/studio/insights.ts` | view model: period, rows, labels, reason text |
| `front/web/src/lib/studio-rules.ts` | `INSIGHTS_PREFIX = "/learner-insights"` with `INSIGHTS_RULES` (GET `courses/{n}/summary|elements|learners`, POST `courses/{n}/elements/{key}/improve`); same for `/experiments` in LX-4 |
| `front/web/src/pages/studio/api/[...path].ts` | route the two new prefixes |
| `front/sdk/src/insights.ts` | admin analytics client (`summary`, `elements`, `learners`, `improve`) |
| `front/ui/src/studio/{InsightsTable,RateBar,ImproveProposalPanel}.ts(x)` | studio components with vitest and axe |
| `front/web/src/layouts/Studio.astro` | "Insights" nav item |
| `admin/src/pages/Courses/components/InsightsTab.tsx`, `admin/src/services/learnerInsights.ts` | admin tab |

Element keys in the URL (`topic:12`, a ULID, `question:7`) are validated by a regex in the BFF rule
(`(?:[0-9A-Z]{26}|topic:\d+|question:\d+)`, URL-encoded colon accepted).

---

## 5. Insights → Living Course improvement proposal (T1, LX-2, ADR 0181)

### 5.1 Why a separate entry point

Living Course proposals are built around two source revisions (columns NOT NULL, analysis and apply read
`FragmentChange`, apply promotes the target revision). An insights proposal has no source change: the source
is the same, the evidence is learner difficulty. Faking a revision pair would make the audit trail say "source
update r3 → r3". ADR 0181 records the choice: **one proposals table, a `kind` column, nullable revision
columns, a dedicated service and request builder, one review screen.** The alternative (a builder chat-edit
version through `PatchService`) was rejected because it bypasses the Living Course progress rules (ADR 0033:
changed quiz answer → re-attempt, never change past scores), the audit trail and the review screen authors
already know.

### 5.2 Flow

1. Studio Insights row → "Propose an improvement" → side panel with the evidence that will be sent and the
   **cost estimate** (`POST …/improve` without `confirmEstimate` returns `{estimateUsd, evidence}`).
2. Confirm → `POST /api/admin/learner-insights/courses/{course}/elements/{elementKey}/improve {confirmEstimate: true}`.
   Permission: course author of the builder session or `living_course_review`; 403 otherwise. Requires: the
   element is a builder element of the session's current approved version; the element's group meets
   `min_group_size`; AI enabled (503 otherwise); no open insights proposal for the same element (returns the
   existing one, 200).
3. `learner-insights` builds `Ulams\LearnerInsights\Improve\InsightsEvidence` (aggregates only):
   `{periodDays, learners, struggleRate, remediationRate, recoveryRate, helpfulShare, topReasons:[{rule,count}],
   wrongOptions:[{optionId, label, share}] (questions only, from `quiz.answer` `chosen`), tutorOutOfScope:int}`.
   No user ids, no per-learner rows, no free text. Shares below 5 learners are merged into "other".
4. It calls `Ulams\LivingCourse\Services\InsightsProposalService::create(Session $session, string $elementId,
   array $evidence, int $userId): Proposal` (new class; `ProposalService` is unchanged). The proposal:
   `kind = insights`, `trigger = insights`, `source_id/from_revision_id/to_revision_id = null`,
   `base_version_id` = current version, `evidence` = the JSON above, one item (`kind = manual`,
   `element_id`, `reason` = a rendered sentence such as "38 % of 42 learners chose option B on this question in
   the last 30 days; 2 failed attempts is the most common reason").
5. Analysis: `AnalysisService` picks `Analysis/ImproveRequest` (new) for `kind = insights`: blocks are
   (a) system prompt `living-course/resources/prompts/update-improve/v1.md`; (b) `<source_fragments untrusted="true">`
   with the element's cited fragments and its lesson's fragments, read through `ElementLookup::citedFragments()`
   (live fragments, no revision); (c) the element and lesson objectives; (d) `<learner_evidence aggregated="true">`
   with the numbers. Task `insights_improve` (profile from `ai.tasks.insights_improve`, default `default`),
   output schema `resources/schemas/outputs/update.json` (unchanged: `decision update|no_change`, patch, reason,
   citations). Validation as for updates: citations ⊆ provided fragments, markup checks, quiz support check,
   grounding task; one regeneration, then the item stays `manual` with "The AI could not propose a grounded
   change; edit it yourself".
6. Review: the existing screen `studio/s/[id]/updates/[proposal].astro` shows an **evidence card** instead of
   the source diff header when `kind = insights` ("Why this was proposed"), the usual before/after diff and the
   cited fragments. The list page shows both kinds, with a "From learner insights" badge and the heading
   "Updates and improvements".
7. Apply: `ApplyService::applyAccepted()` branches on `kind`: no revision lookup, no promote, no remap; the
   version reason is "Improvement from learner insights: {label}"; fragment checks use the session's live
   fragments. `ProposalApplied` fires as today, so progress rules and learner notices run, and
   `learner-insights:retire-remediations` retires the element's cached remediations (new applied version).
8. Audit: `proposal.created` and `proposal.applied` entries carry `kind = insights`, the evidence summary
   (counts only) and `ai_call_ids`.
9. Budget: counted against the session's Living Course monthly budget (`living_course.cost.*`); limit 3 insights
   proposals per session per day (`living_course.cost.insights_proposals_per_session_per_day`).

### 5.3 Signal prerequisite

`quiz.answer` signals do not record the chosen options yet (`phase-4.md` §19). LX-2 adds `chosen` as **plain
option ids** (course content ids, not personal data; aggregated only, Q4) in
`RecordSignals::quizFinished` and in the backfill, and the remediation pattern key starts to use them (the
M4.5 pattern detail that was empty).

### 5.4 Files

| File | Change |
|---|---|
| `api/packages/living-course/database/migrations/2026_10_25_100000_add_kind_and_evidence_to_living_course_proposals.php` | `kind` string(16) default `source`, `evidence` JSON nullable; `source_id`, `from_revision_id`, `to_revision_id` nullable; index `(session_id, kind, status)` |
| `living-course/src/Services/InsightsProposalService.php` | new |
| `living-course/src/Analysis/ImproveRequest.php` | new request builder |
| `living-course/src/Services/{AnalysisService,ApplyService}.php` | branch on `kind` |
| `living-course/src/Http/Controllers/ProposalsController.php`, `src/Support/Presenter.php` | expose `kind`, `evidence` |
| `living-course/resources/prompts/update-improve/v1.md` | new prompt |
| `learner-insights/src/Improve/{InsightsEvidence,EvidenceBuilder}.php`, `src/Http/Controllers/ImproveController.php` (+ Swagger) | new |
| `learner-insights/src/Listeners/RecordSignals.php`, `src/Console/BackfillCommand.php`, `src/Signals/SignalType.php` | `chosen` option ids |
| `api/packages/ai/config/ai.php` | task `insights_improve` |
| `front/web/src/pages/studio/s/[id]/updates/*.astro`, `front/web/src/studio/updates.ts` | evidence card, badge |

`living-course` belongs to the Phase 3 code; the change is additive and its owner reviews the PR (risk R3).

---

## 6. Experiments package (T5, T12, T13, T14; LX-4 to LX-8; ADR 0055 + ADR 0180)

### 6.1 Package

New `api/packages/experiments` (`Ulams\Experiments`), following the `example-plugin` layout: provider,
config `config/experiments.php`, migrations, permissions enum and seeder (`experiments_manage` for admins and
course authors on their courses, `experiments_view_results`), routes, Swagger attributes, `README.md`, testsuite
`experiments` in `api/phpunit.xml` and in the `living` CI shard. Depends on `courses`, `topic-type-gift`
(contracts only), `notifications`, `templates-email`, `settings`, `learner-insights` (signals read service and
privacy hooks). **Makes no LLM calls.**

### 6.2 Data model (tenant DB)

| Table | Columns |
|---|---|
| `experiments` | ULID `id`, `course_id`, `key` (slug), `title`, `hypothesis` (text, author-written), `kind` (`remediation_holdout`, `adaptive_interface`, `topic_variant`), `subject` JSON (e.g. `{topicId, variantTopicId}` for `topic_variant`), `arms` JSON (`[{key:'control',weight:50},{key:'treatment',weight:50}]`, 2 to 3 arms, weights sum 100), `salt` (random 32 hex), `status` (`draft|running|stopped|archived`), `primary_metric` (`retention_score`), `secondary_metrics` JSON (`completion`, `time_on_target`, `first_attempt_pass`, `sus`, `ueq_s`, `nasa_tlx`), `retention_delay_days` (3 to 7, default 5), `retention_window_days` (default 3), `target_per_arm` (computed, editable upward only), `max_days` (default 90), `rollout_arm` (arm everybody gets after stop, default `control`), `instruments` JSON, `started_at`, `stopped_at`, `stop_reason` (`target_reached|max_days|guardrail|manual`), `created_by`, timestamps. One running experiment per `(course_id, kind, subject)` |
| `experiment_assignments` | `experiment_id`, `user_id`, `arm`, `assigned_at`, `excluded` (`opt_out|no_consent|null`); unique `(experiment_id, user_id)` |
| `experiment_exposures` | `experiment_id`, `user_id`, `arm`, `first_exposed_at`, `exposures` int; unique `(experiment_id, user_id)` |
| `experiment_retention_checks` | ULID, `experiment_id`, `user_id`, `arm`, `target_completed_at`, `due_at`, `expires_at`, `items` JSON (question ids), `notified_at`, `opened_at`, `completed_at`, `score` decimal(5,4) nullable, `status` (`scheduled|due|completed|expired|cancelled`) |
| `experiment_survey_responses` | `experiment_id`, `user_id`, `arm`, `instrument` (`sus|ueq_s|nasa_tlx`), `instrument_version`, `answers` JSON (item → value), `scores` JSON (computed), `language`, `created_at`; unique `(experiment_id, user_id, instrument)` |
| `experiment_results_snapshots` | `experiment_id`, `computed_at`, `payload` JSON (aggregates only), `conclusive` bool |

Retention: assignments, exposures, checks and responses are deleted 365 days after `stopped_at`
(`experiments:prune`, daily); snapshots (aggregates, groups ≥ 20) are kept. Learner export and erase:
`Privacy` in `learner-insights` gets an `ExportContributor` / `EraseContributor` tag that `experiments`
registers, so `GET/DELETE /api/learner-insights/me…` and the `AccountDeleted` listener include experiment data
(an erased learner's assignment row is deleted; snapshots already computed are anonymous).

### 6.3 Assignment (stable hash)

```
bucket = hexdec(substr(hash('sha256', tenant_key ‖ ':' ‖ experiment.salt ‖ ':' ‖ user_id), 0, 8)) % 10000
arm    = first arm whose cumulative weight × 100 > bucket
```

`tenant_key` is the tenant's `APP_KEY`-derived HMAC key (`hash_hmac('sha256', 'experiments', config('app.key'))`),
so the same user id in two tenants never maps to the same arm, and the per-experiment salt makes arms
independent across experiments (the current crc32 holdout fails both). Deterministic, so recomputable, but the
first decision is **persisted** at the eligibility moment and never recomputed: weight edits are blocked while
running.

Eligibility, checked in order by `Ulams\Experiments\Services\Gate::decide(string $kind, int $courseId, int $userId, array $subject): ?Decision`:
1. tenant setting `experiments.enabled` (default **false**); 2. experiment `running` for that course, kind and
subject; 3. learner enrolled; 4. learner opt-out (`user_settings` `experiments.opt_out`) → assignment row with
`excluded = opt_out`, **control** behaviour, excluded from analysis; 5. consent
mode (`experiments.require_consent`) without `experiments.consent` → `excluded = no_consent`, control behaviour;
6. otherwise hash → arm. `null` means "no experiment": callers keep their default behaviour.

Contract for other packages: `Ulams\Experiments\Contracts\ExperimentGate` (bound in the container; a null
implementation when the package is absent, so `learner-insights` keeps working without it).

### 6.4 Exposure logging

An assignment is not an exposure. Exposure is recorded by `Gate::expose($decision)` at the moment the varied
surface is actually used:

| Kind | Exposure point |
|---|---|
| `remediation_holdout` | `RemediationService` when a struggle would create or reuse a remediation (control: when it would have) |
| `adaptive_interface` | `PresentationController::show` when a derived profile differs from the default (control: when it would have) |
| `topic_variant` | the program/topic API serving the target topic to the learner (6.8) |

Analysis is **intention-to-treat over exposed learners**: everybody exposed counts in their arm, whatever they
did afterwards. Exposures are idempotent (`insertOrIgnore` + `exposures` counter, one write per learner per day).

### 6.5 Metrics

| Metric | Definition | Source |
|---|---|---|
| `retention_score` (primary) | score 0 to 1 of the delayed retention check (6.6), learners who completed it within the window | `experiment_retention_checks` |
| `retention_response` (guard) | share of exposed learners who reached the target and completed the check | same |
| `completion` | target topic completed within 14 days of exposure; course completion for `adaptive_interface` | `TopicFinished` / `CourseFinished` via `learner_signals`, else `course_progress` read through `ProgressService` |
| `time_on_target` | median seconds on the target topic | `topic.progressed` signals (learner-insights on) |
| `first_attempt_pass` | first attempt of the lesson's quiz passed | `quiz.attempt_finished` signals |
| `sus`, `ueq_s`, `nasa_tlx` | instrument scores (7) | `experiment_survey_responses` |
| `recovery` | holdout only: `OutcomeTracker` outcomes per arm | `learner_interventions` |

Behavioural metrics that need signals are shown as "needs Learner Insights" when the module is off. Signals are
read in-process through a new read service `Ulams\LearnerInsights\Services\SignalQueries` (aggregates per user
set); they never leave the tenant and are never sent to a model.

### 6.6 Delayed retention measurement (LX-5)

1. **Target**: the topic (or, for `adaptive_interface`, each module the learner finishes; the check is about the
   last finished module) whose learning is measured.
2. **Item bank**: GIFT questions of the quiz topics in the same lesson as the target, and for builder courses
   the blueprint questions of the target lesson (mapped to `gift_question` through the entity map). Questions
   the learner already answered in that lesson's quiz are used last. 3 to 5 items (`experiments.retention.items`,
   default 4); types limited to those `checkAnswer` grades deterministically (multiple choice, multiple answers,
   true/false, short answer, numerical, matching). Creating a `topic_variant` or holdout experiment on a
   target with fewer than 3 gradable questions is refused with "Add at least 3 auto-graded questions to this
   lesson to measure retention". No AI generates retention items (keeps the experiment model-free; Q9).
3. **Scheduling**: on `TopicFinished` for an exposed learner, a check is created with
   `due_at = completed_at + retention_delay_days` (default 5, allowed 3–7) and
   `expires_at = due_at + retention_window_days` (default 3). The scheduler command
   `experiments:retention-due` (hourly) marks due checks and fires `RetentionCheckDue(User $user, Course $course,
   string $url)`: it **carries `User` on purpose**, so the notifications package stores the in-app notice and sends
   the e-mail template "A 2-minute check on {lesson}" (registered with `Template::register`, seeded EN/PL/ZH).
   One reminder after 48 h if not opened. Expired checks are `expired` and counted in `retention_response`.
4. **Taking it**: learner page `front/web/src/pages/learn/[courseId]/check/[checkId].astro`, component
   `RetentionCheck` in `front/ui` (catalogue component: props schema, text fallback, axe): one question per
   screen, no feedback per question, a summary with the correct answers at the end. API:
   `GET /api/experiments/checks/{check}` (owner only; items without answers),
   `POST /api/experiments/checks/{check}` `{answers}` → graded with
   `GiftQuestionStrategyFactory` / `checkAnswer` through `topic-type-gift` contracts, score stored, **no quiz
   attempt is created and nothing touches grades or course progress**.
5. Copy says what it is: "This short check helps the course team see what people remember a few days later.
   It does not count towards your grade." Skippable ("Not now", "Don't ask again for this course").

### 6.7 Statistics and guard rails (LX-6)

Fixed-horizon design, computed nightly (`experiments:results`, 04:00) and on demand, stored as snapshots:

1. **Planned sample** at creation: `target_per_arm = ceil(2 × (z₁₋α/₂ + z₁₋β)² × σ² / MDE²)` with α = 0.05,
   power 0.8, σ = 0.25 (default spread of a 0–1 quiz score), MDE = 0.10 (10 points of retention score) →
   **99 per arm** (shown as 100). Authors can raise it, not lower it below 100, and see the estimate in weeks from
   the course's enrolment rate.
2. **No peeking**: before `target_per_arm` exposed-and-measured learners per arm or `max_days`, the results page
   shows only progress (n per arm towards the target, response rates, days left) and **no** means, differences,
   intervals or verdict. Opening the page does not change this; there is no "show anyway". (Q1)
3. **Small groups**: no figure is shown for an arm with fewer than **20** learners (ADR 0055), also after the
   end; a stopped experiment below 20 per arm reports "Not enough learners to draw a conclusion".
4. **Estimate**: difference of means (treatment − control) with a **95 % bootstrap percentile interval**
   (2 000 resamples, seed = experiment id, so the page is reproducible), plus per-arm means with intervals.
   Three arms: each treatment against control, with a Bonferroni-adjusted interval (97.5 %).
5. **Verdict** wording, only when conclusive: interval above 0 → "Learners in {arm} remembered more
   (+x points, 95 % interval a to b)"; below 0 → "remembered less"; spanning 0 → "No clear difference; the
   effect is between a and b points". Never "significant", never a p-value alone.
6. **Secondary metrics** are shown with intervals but labelled "exploratory: do not decide on these alone".
7. **Sample ratio mismatch**: χ² test of exposed counts against weights; p < 0.001 → banner "Learners were not
   split as planned; results may be biased" and the verdict is withheld.
8. **Differential attrition**: retention response rates differing by more than 10 points between arms →
   banner "Fewer learners in one group took the check; results may be biased" (verdict still shown, flagged).
9. **Guardrail (safety stop)**: if completion in the treatment arm is lower than control with the 95 % interval
   entirely below −10 points at any nightly run after 50 per arm, the experiment stops automatically
   (`stop_reason = guardrail`), everybody gets `rollout_arm = control`, and the author is notified. This is the
   only early look allowed and it only checks for harm.
10. **Stop**: at target (`target_reached`), at `max_days`, by guardrail, or manually (a manual stop before target
    marks the results "stopped early: not conclusive").

Implementation: `Ulams\Experiments\Stats\{Bootstrap,SampleSize,SampleRatio,Summary}` (plain PHP, seeded
`mt_srand`, unit-tested against fixtures computed in R/NumPy and stored as JSON in
`tests/fixtures/stats/*.json`). No statistics library (section 9).

### 6.8 Experiment kinds

| Kind | Arms | What changes | Integration |
|---|---|---|---|
| `remediation_holdout` | `control` (no personal help), `treatment` (help) | personal remediations | `RemediationService::inHoldout()` replaced by `ExperimentGate`; the config `holdout_percent` becomes the default weight of a newly created holdout experiment and is deprecated (kept read for one release); `Intervention.detail = holdout` stays for continuity |
| `adaptive_interface` | `control` (profile off), `treatment` (derived profile) | presentation profile | `PresentationProfiles::for()` asks the gate when `learner_insights.adaptive_interface` is on; learner overrides still win and are recorded as a secondary metric ("overrode the profile") |
| `topic_variant` (LX-8) | `control` = the topic, `treatment` = an author-approved sibling topic (e.g. a Layout or Interactive topic applied by the builder, or a hand-made topic) | content shown for one lesson item | see below |

`topic_variant` without touching course completion: the variant is created as an **inactive** topic in the
same lesson (`active = false`, so `ProgressService` never counts it and the program hides it). A
`TopicResource::extend('experiments.variant', …)` callback adds `experiment_variant: {experimentId, arm,
topicable}` to the target topic for learners in `treatment`, built from the variant's client resource. The
learner lesson page renders the variant's content in place of the target's, and **progress is recorded on the
target topic**. Only non-assessed topic types can be varied (RichText, Layout, Interactive, Video, Audio,
LiaScript, PDF; not GIFT quizzes, SCORM, H5P with score, cmi5, LTI): experiments never change what is graded
(Q8). The author sees both versions side by side when creating the experiment and confirms both are approved.

### 6.9 Consent and privacy (confirmed model #58, ADR 0055)

- Tenant opt-in: `experiments.enabled` (administrable, default false) on the admin settings screen (LX-3).
- **Notice**: every course with a running experiment shows a short notice on the course page and in
  `/account/personalisation` ("This course compares two ways of teaching {lesson} to see which one people
  remember better. Your grades are not affected. [Opt out]").
- **Opt-out**: `PUT /api/experiments/me` `{optOut: true}` (or per course `{courseId}`) → control behaviour,
  excluded from analysis, existing assignment marked `excluded = opt_out`, pending checks cancelled.
- **Consent mode**: `experiments.require_consent` → the notice becomes a question with "Take part" / "No thanks";
  nothing is assigned before "Take part" (`experiments.consent` timestamp in `user_settings`).
- Never affects grades, access, certificates, enrolment or deadlines (stated in the docs and enforced by the
  allowed kinds and topic types).
- Authors and admins see aggregates only; there is no per-learner experiment view.
- DPIA: `front/docs-site/src/content/docs/operators/dpia-learner-insights.mdx` gains an "Experiments" section
  (purpose, data, retention 365 days after stop, legal basis per tenant).

### 6.10 Author-facing results (LX-6)

Studio page `front/web/src/pages/studio/courses/[courseId]/experiments/[experimentId].astro` and list
`…/experiments/index.astro`; create form `…/experiments/new.astro` (kind, target, arms and weights, hypothesis,
delay, instruments, sample estimate). Results layout: hypothesis; status and progress to target; after the
horizon, the primary metric card (means, difference, interval as a horizontal interval plot with the numbers
in text), the verdict sentence, banners from 6.7, secondary metrics table (exploratory), survey scores, and
"Stop and roll out {arm}". Accessible: the interval plot is decorative next to a text table; keyboard and screen
reader tested.

API (prefix `api/admin/experiments`, `auth:api`): `GET/POST /courses/{course}/experiments`,
`GET/PUT /experiments/{e}` (draft only), `POST /experiments/{e}/start|stop`, `GET /experiments/{e}/results`.
Learner API (prefix `api/experiments`): `GET/PUT /me`, `GET /courses/{course}/notice`,
`GET/POST /checks/{check}`, `GET/POST /surveys/{experiment}/{instrument}`.

---

## 7. Surveys: SUS, UEQ-S, NASA-TLX (T11, LX-7)

### 7.1 Instruments

| Instrument | Items | Scale | Score | Licence (implementer verifies and records the URL and date in `api/packages/experiments/resources/instruments/SOURCES.md`) |
|---|---|---|---|---|
| **SUS** (Brooke 1986/1996) | 10 statements | 1–5 agreement | odd items `x−1`, even items `5−x`, sum × 2.5 → 0–100 | Free to use with attribution to the source; no fee (Brooke, "SUS: a quick and dirty usability scale", 1996) |
| **UEQ-S** (Schrepp, Hinderks, Thomaschewski 2017) | 8 bipolar pairs | 7-point semantic differential, −3…+3 | pragmatic quality (items 1–4), hedonic quality (5–8), overall | Free of charge, published by the authors at ueq-online.org with official translations (including Polish and Chinese) |
| **NASA-TLX raw** (Hart & Staveland 1988; RTLX without pairwise weighting) | 6 subscales | 0–100 in steps of 5 | mean of the six (performance reversed as published) | Developed by NASA Ames, US government work, free to use |

Instruments are versioned JSON definitions in `api/packages/experiments/resources/instruments/{sus,ueq_s,nasa_tlx}.v1.json`
(items, anchors, reverse flags, scoring), with the wording exactly as published, never paraphrased. Languages:
EN always; PL and ZH only from published translations (UEQ official; SUS and NASA-TLX from published validated
translations whose licence allows reuse); a language without one falls back to EN and the results group
responses by language. Native review is an owner action (section 15).

### 7.2 When they are shown

Configured per experiment (`instruments`): NASA-TLX right after the learner completes the target topic (it
measures the workload of that task); UEQ-S after the retention check; SUS at course completion (only for
`adaptive_interface`, which is about the interface). At most one survey per learner per day and each instrument
once per experiment; always skippable; shown as a card (`SurveyCard` catalogue component with `LikertScale`,
`SemanticDifferential`, `RatingSlider` that are keyboard-operable radio groups / a native range with visible
value, WCAG 2.2 AA, 360 px).

### 7.3 Why not the `questionnaire` package

It stores integer rates by user with author-visible answers, has no bipolar anchors, reverse scoring or
instrument versions, and answers are not tied to an experiment arm. Building the instruments on it would mean
changing its schema and visibility model. The experiments package keeps responses arm-scoped, aggregated only,
retained with the experiment (Q5).

---

## 8. Other open Learner Insights items

### 8.1 Tenant isolation suite (T4, LX-1)

- `api/packages/learner-insights/tests/Feature/TenantIsolationTest.php`: a data provider listing **every**
  route of `src/routes.php` with a valid request for tenant A; for each, tenant B (own Passport keys, technique
  of `example-plugin/tests/Api/TenantIsolationTest.php`) gets 401 with tenant A's token, and tenant A's token works
  in A. A companion test reads `Route::getRoutes()` for `api/learner-insights/*` and `api/admin/learner-insights/*`
  and fails if a route is missing from the provider, so a new endpoint cannot skip the suite.
- Learner isolation: another learner of the same tenant gets 404 on assignments, presentation overrides, export
  contents; an author of course X gets 403 on course Y analytics.
- Same pattern for `tutor` (`tutor/tests/Feature/TenantIsolationTest.php`) and `experiments`.
- The opt-in real-tenant probe `api/packages/tenancy/tests/Integration/TenantIsolationTest.php` gains
  `GET /api/learner-insights/me` and one admin analytics call across the two provisioned tenants.

### 8.2 Minimal data to the LLM (T3, LX-1 and LX-10)

- `api/packages/learner-insights/tests/Unit/LlmMinimisationTest.php` and the tutor equivalent: for every task
  that touches learner context (`remediation`, `reflection_feedback`, `tutor`, `insights_improve`), run through the
  fake driver and assert the recorded request blocks contain no user id, name, e-mail, other course ids, signal
  types or status reasons with learner data, and that `ai_calls.user_id` is null.
- **Redaction** of e-mail addresses and phone numbers in learner-written text (tutor questions, Feynman text)
  before it is sent: `Ulams\Ai\Support\PersonalDataRedactor` (regex, replaces with `[email]`, `[phone]`), on by
  default (`ai.redact_learner_text`), unit-tested with fixtures.
- A guard test in `learner-insights` and `experiments`: no class in `src/` of `experiments` resolves `LlmClient`.

### 8.3 Reasons for every recommendation; AI on/off and provider per tenant (T9, LX-3)

- `api/packages/learner-insights/tests/Feature/ReasonsCoverageTest.php`: every learner-facing recommendation
  payload (support assignments, presentation profile, nudge notification and e-mail variables, the experiment
  notice) has a non-empty `reasons`/`why` field rendered from message keys.
- Admin screen `admin/src/pages/Settings/LearnerInsights.tsx` (route `/settings/learner-insights`, design
  `front/docs/design/stitch/personalisation/05-admin-insights-settings`): module switch, consent mode, retention,
  rules with defaults and per-course link, nudges, remediation, adaptive interface, tutor, experiments opt-in and
  consent mode, minimum group size. Values through the existing administrable config (`POST /api/admin/config`)
  and `GET /api/admin/learner-insights/settings`.
- **AI section** of the same screen: per-feature switches (`course_builder.enabled`, `living_course.analysis_enabled`,
  `learner_insights.remediation.enabled`, `learner_insights.reflection.enabled` (new), `tutor.enabled`) and a
  read-only "Provider and models" panel from a new `GET /api/admin/ai/status` (`ai` package: driver, profile
  labels, whether a key is configured as yes/no, monthly spend vs limit; never a key). Changing the provider stays
  an operator action (`ulams:tenant:set-env`, ADR 0063), documented on the panel (Q7).

### 8.4 Data flows, labelling, no training (T10, LX-10)

- `front/docs-site/src/content/docs/operators/ai-data-flows.mdx`: one table row per AI task in `config('ai.tasks')`
  (builder tasks, Living Course `update`, `new_lesson`, `insights_improve`, `remediation`, `reflection_feedback`,
  `tutor`, `grounding`, critics): what is sent, what is never sent, retention at the provider, who sees the output.
  It states that ulams trains no models and sends content only to the configured provider, links the provider's
  commercial terms (the implementer records the URL and access date) and the local-model option.
- `api/packages/ai/tests/Unit/DataFlowDocTest.php`: fails when a task in config has no row in the page.
- Labelling remainder: the improvement proposal patch inherits the existing "Created with AI assistance and
  reviewed by the author" topic label after apply (no new work); the retention check and surveys are not AI.

### 8.5 Remediations for courses not built with the Course Builder (T7, LX-9)

- Grounding corpus: the tutor's `tutor_chunks` for the course (RichText and LiaScript Markdown, transcripts),
  exposed read-only through `Ulams\Tutor\Contracts\CourseChunks::forTopic(int $topicId): array` (ids `chk_…`,
  label, text). The tutor index is built for these courses even when the tutor is switched off
  (`tutor:index` already works per course).
- `ElementContext` gains a non-builder variant: for `topic:<id>` the topic's chunks; for `question:<id>` the GIFT
  question (stem, options, correct answer, feedback) through `GiftQuestionServiceContract` plus the chunks of
  topics in the same lesson. Citations are chunk ids; validation: citations ⊆ provided chunk ids, the builder
  `grounding` task with chunks as fragments.
- Pattern key uses `content_hash` of the topic (or question) instead of `applied_version_id`; a changed hash at read
  time retires the remediation.
- Not available for topics with no indexable text (SCORM, H5P, cmi5, LTI, video without transcript): no card,
  as today. Citations show "Lesson › section" with a link to the topic.

### 8.6 Partitioning `learner_signals` (T6, LX-11)

- Nightly `learner-insights:signals-health` logs the row count estimate (`pg_class.reltuples`) and, above
  `learner_insights.partition_threshold_rows` (50 000 000), shows a warning on the admin settings screen.
- Opt-in `learner-insights:partition-signals {--dry-run} {--chunk=50000}` (PostgreSQL only): creates
  `learner_signals_p` `PARTITION BY RANGE (occurred_at)` with monthly partitions covering the data plus 2 months
  ahead and a default partition; primary key `(id, occurred_at)`; unique `(source, source_ref, type, occurred_at)`
  (the partition key must be in unique constraints; `source_ref` rows have a fixed `occurred_at`, so idempotency
  holds; the recorder's `insertOrIgnore` is unchanged); copies in id chunks while new writes go to both tables
  (a temporary trigger); swaps names in one transaction; re-creates the `UPDATE`-rejecting trigger on the parent.
- After partitioning, `learner-insights:prune` detaches and drops whole months older than retention instead of
  chunked deletes, and the nightly job creates the next months' partitions.
- No `pg_partman` (an extension self-hosters would need to install; three statements a month do the job).
- Tested in the PostgreSQL integration suite with a small threshold; SQLite skips.

---

## 9. New dependencies

**None.** Hashing (`hash('sha256')`), bootstrap and sample size are a few hundred lines of PHP with fixture
tests; charts are text tables plus decorative CSS bars; surveys use our catalogue components. Rejected:
a statistics library such as `markrogoyski/math-php` (MIT, maintained, but we need four functions and the
seeded bootstrap must be reproducible on our side), GrowthBook/Unleash/PostHog experiments (extra services
for self-hosters and learner data leaving the LMS), `pg_partman` (extension).

---

## 10. Migrations

| Package | Migration | Down |
|---|---|---|
| living-course | `2026_10_25_100000_add_kind_and_evidence_to_living_course_proposals` (kind, evidence, nullable revision columns, index) | drops the columns and index; refuses if insights proposals exist |
| experiments | `2026_10_26_100000_create_experiments_tables` (6.2, six tables) | drops them |
| learner-insights | none for LX-1 to LX-9 (the `chosen` key is JSON data); LX-11 is a command, not a migration | — |
| ai | none (status endpoint and redactor only) | — |

All tables in the tenant DB; no change to LMS tables (`topics.active = false` for variant topics is set through
the courses topic service when the author creates the variant, like any inactive topic).

---

## 11. Tests

### 11.1 API (PHPUnit, fake LLM driver)

- **Insights proposals**: evidence has no per-learner data; min group size refused; duplicate open proposal
  returns the existing one; `ImproveRequest` blocks (fake driver recorded request) contain the evidence and
  fragments only; citations ⊆ provided fragments; `no_change` decision; apply creates a version without revision
  promote, runs progress rules (changed quiz answer → re-attempt), retires remediations, writes audit entries;
  the existing Living Course tests stay green (`kind = source` default).
- **Experiments**: assignment stability (same input → same arm; distribution within ±2 % of weights over 20 000
  synthetic ids; different salts and tenants → independent); persistence (weights cannot change while running);
  opt-out and consent mode exclusions; exposures idempotent; holdout parity (the remediation tests pass with the
  gate); retention scheduling (delay, window, expiry, reminder once), grading through `checkAnswer` for each
  allowed type, no quiz attempt or progress written; survey scoring against the published worked examples;
  statistics against fixtures (bootstrap interval reproducible with the seed, sample size 99, χ² SRM, attrition
  flag, guardrail stop); results withheld before target and below 20 per arm (server side).
- **Tenant isolation**: route-complete suites for `learner-insights`, `tutor`, `experiments` (8.1).
- **Synthetic journeys**: `learner-insights/tests/Support/Journey.php` gains `->inExperiment('key', 'treatment')`,
  `->exposed()`, `->day(n)->retentionCheck(score)`, `->survey('nasa_tlx', [...])`, `->optOutOfExperiments()`;
  scenarios: holdout control learner gets no help but is measured; treatment learner recovers; opted-out learner
  never assigned and absent from results; consent-mode learner assigned only after consent; adaptive interface
  treatment learner overrides the profile.
- **Minimisation and reasons** (8.2, 8.3), **data-flow drift** (8.4).
- **Non-builder remediations**: chunk grounding, citations ⊆ chunks, retirement on content hash change, no card
  for SCORM.
- **Partitioning** (PostgreSQL integration suite): copy, swap, idempotent inserts, prune by partition.
- Guard tests: no LLM in `experiments`; no direct writes to LMS tables; no model names in `src/`; ids-only
  internal events except `RetentionCheckDue` (documented exception, like `LearnerNudged`).

### 11.2 Front

- `@ulams/ui`: `InsightsTable`, `RateBar`, `ImproveProposalPanel`, `RetentionCheck`, `SurveyCard`, `LikertScale`,
  `SemanticDifferential`, `RatingSlider`, `IntervalPlot`: schema, text fallback, interaction round-trip, axe.
- `@ulams/sdk`: insights admin, living-course `kind`/`evidence`, experiments clients.
- `@ulams/web` Playwright (fake driver): studio Insights page with the small-group notice → "Propose an
  improvement" → estimate → proposal with evidence card → accept → apply; experiment create → learner notice →
  opt-out; retention check reached through the notification link and completed; survey card skip and submit;
  results page shows progress only before target and the verdict after (seeded fixture); axe and keyboard on every
  new page, desktop and 360 px.
- Admin: Jest for `InsightsTab` and `Settings/LearnerInsights`.

### 11.3 Evals

`living-course:eval` gains `insights_improve` fixtures (git-basics question with a skewed wrong-option
distribution): decision, citations, grounding pass rate, cost. Fake driver in CI; `--live` needs
`ANTHROPIC_API_KEY` (owner input). Experiments have no eval (no model).

---

## 12. Risks

| # | Risk | Mitigation |
|---|---|---|
| R1 | Cohorts too small: most courses never reach 100 per arm | Sample estimate in weeks shown before start; "not enough learners" is an honest outcome; 2 arms by default; tenants can pool by running the same kind on several courses (later) |
| R2 | Authors read early numbers as results | No numbers before the horizon (6.7 rule 2); verdict wording with intervals; secondary metrics labelled exploratory |
| R3 | `living-course` change collides with Phase 3 work | Additive migration, `kind = source` default, separate service and request class, owner of the package reviews; existing Living Course suite must stay green |
| R4 | Retention checks annoy learners or bias response | One reminder, skippable, "Don't ask again"; response rate per arm with an attrition banner |
| R5 | Variant topics break progress | Variants are inactive topics; progress only on the target; graded types excluded; e2e covers completion |
| R6 | Survey translations not validated | EN fallback, responses grouped by language, native review as owner action |
| R7 | Partitioning a live table | Opt-in command, dry run, dual-write trigger, tested on PostgreSQL; only above 50M rows |
| R8 | Holdout migration changes who is in control | Running holdouts are created fresh (new salt) at LX-4; the old config share stays in place until an experiment exists, documented in the release notes |
| R9 | Learner text sent to the tutor still contains personal data | Redaction of e-mail and phone, UI warning already present, data-flow page |

---

## 13. Milestones (small, demoable)

| # | Milestone | TODO items | Demo |
|---|---|---|---|
| **LX-1** (recommended first) | Studio Insights page, admin course tab, route-complete tenant isolation suites, minimisation tests | T2, T4 (insights, tutor), T3 (tests) | On the coffee demo tenant with backfilled signals: open the course in the studio → "Insights" shows progress, quiz performance, at-risk counts and "Sections that confuse people"; a small course shows the small-group notice; CI shows the isolation suites |
| LX-2 | Insights → Living Course improvement proposal (ADR 0181) | T1 | Click "Propose an improvement" on "2.3 question 3" → estimate → proposal with the evidence card → accept the patch → apply; the remediation for that element retires; audit shows `kind = insights` |
| LX-3 | Admin Learner Insights and AI settings screen, AI status endpoint, reasons coverage | T9, admin screen | Admin changes the inactivity threshold to 5 days and switches the tutor off; the AI panel shows the provider label and "key configured: yes" |
| LX-4 | Experiments package core: assignment, exposures, consent and opt-out, holdout and adaptive interface as kinds (ADR 0180) | T5, T12 (part), T13 (consent), T14, T4 (experiments) | Tenant opts in; author starts a remediation holdout on git-basics; learners see the notice; one opts out; assignments split 50/50 in the studio list |
| LX-5 | Delayed retention checks | T12 | A learner completes lesson 2.3; five days later (time travel in the demo) gets the in-app and e-mail notice, takes a 4-question check; the score appears in the experiment's progress |
| LX-6 | Results with guard rails | T13 | Seeded experiment: before target only progress is visible; after target the verdict with the bootstrap interval; an SRM fixture shows the warning banner |
| LX-7 | Surveys SUS, UEQ-S, NASA-TLX and behavioural metrics | T11 | After the target topic the learner gets the NASA-TLX card, after the check UEQ-S; results show scores per arm and completion/time/pass metrics |
| LX-8 | Topic variant experiments | T12 | Author pairs a RichText lesson with an approved Layout version; treatment learners see the Layout, progress counts on the original |
| LX-9 | Remediations for non-builder courses | T7 | A hand-made RichText course: a learner failing a question twice gets extra help citing "Lesson 2 › Rebasing" |
| LX-10 | AI data-flow page, redaction, labelling remainder | T10, T3 | Docs page lists every AI task; drift test fails when a task is added without a row; a tutor question with an e-mail address is sent redacted |
| LX-11 | Partitioning tool and health check | T6 | On PostgreSQL with a 1 000-row threshold: warning on the settings screen, `--dry-run`, partition, prune drops a month |

Order: LX-1 needs nothing new in the API and is the most visible gap ("Coming" on the landing). LX-2 depends on
LX-1's page. LX-3 is independent. LX-4 → LX-5 → LX-6 → LX-7 are sequential; LX-8 after LX-6. LX-9, LX-10, LX-11
are independent and can run in parallel with the experiments chain.

### Commit order (one concern per commit, tests in the same commit)

LX-1: `test(learner-insights,tutor): route-complete tenant isolation suites` · `test(learner-insights,tutor): no learner data in model requests` ·
`feat(sdk,ui): insights admin client and studio insights components` · `feat(web): studio course insights page` · `feat(admin): course insights tab`.
LX-2: `feat(learner-insights): record chosen options in quiz answer signals` · `feat(living-course): insights proposals with evidence (ADR 0181)` ·
`feat(learner-insights): improve endpoint with aggregated evidence` · `feat(web): propose an improvement from insights and the evidence card`.
LX-3: `feat(ai): tenant AI status endpoint` · `feat(admin): Learner Insights and AI settings screen` · `test(learner-insights): reasons on every recommendation`.
LX-4: `feat(experiments): package, assignment, exposures and consent (ADR 0180)` · `refactor(learner-insights): holdout and adaptive interface through the experiment gate` ·
`feat(web): experiment notice and opt-out` · `feat(web): studio experiment list and create form`.
LX-5: `feat(experiments): delayed retention checks and notices` · `feat(ui,web): retention check page`.
LX-6: `feat(experiments): results with sample size, bootstrap interval and guard rails` · `feat(web): experiment results page`.
LX-7: `feat(experiments): SUS, UEQ-S and NASA-TLX instruments and behavioural metrics` · `feat(ui,web): survey cards`.
LX-8: `feat(experiments,courses): topic variant arms through the topic resource extension` · `feat(web): render the assigned variant`.
LX-9: `feat(tutor): course chunks read contract` · `feat(learner-insights): remediations grounded on course chunks`.
LX-10: `feat(ai): redact e-mail and phone numbers in learner text` · `docs: AI data flows for every task with a drift test`.
LX-11: `feat(learner-insights): signal table health check and monthly partitioning command`.

---

## 14. Open questions (each with the default implementation will use)

| # | Question | Default (used unless the owner objects) |
|---|---|---|
| Q1 | May authors look at interim results before the planned sample? | **No.** Progress only until target or `max_days`; the only early look is the automatic harm guardrail (6.7) |
| Q2 | Minimum planned sample and smallest reportable group | Planned 100 per arm (MDE 10 points, power 0.8, α 0.05); nothing shown below 20 per arm |
| Q3 | Where does the per-course dashboard live? | Studio page by course id (all courses), admin course tab with a summary and a link; one full implementation |
| Q4 | Record chosen quiz options in plain ids or hashed? | Plain option ids (course content, not personal data), shown only aggregated with min group size |
| Q5 | Reuse the `questionnaire` package for surveys? | No: built-in versioned instruments in `experiments`, arm-scoped, aggregated only |
| Q6 | Retention check delay and window | Delay 5 days (allowed 3–7), window 3 days, one reminder after 48 h, 4 items |
| Q7 | Can tenant admins choose the AI provider themselves? | No: provider and keys stay operator-level (ADR 0063 overrides); tenant admins get per-feature switches and a read-only provider panel |
| Q8 | Which content may an experiment vary? | Only non-assessed topic types and two features (remediation, adaptive interface); never quizzes, SCORM, scored H5P, cmi5, LTI, grades, deadlines or certificates |
| Q9 | May AI write retention check items when a lesson has too few questions? | No: experiments stay model-free; the author adds questions (creation is refused below 3) |
| Q10 | Statistics method | Fixed horizon, difference of means with a seeded 95 % bootstrap interval, Bonferroni for 3 arms, SRM and attrition checks |
| Q11 | Default holdout share once a tenant opts in | 20 % control (`remediation_holdout` weights 20/80), as in `phase-4.md` |
| Q12 | Data retention for experiment data | Per-learner rows 365 days after the experiment stops; anonymous snapshots kept |
| Q13 | Insights proposal model | Same proposals table with `kind = insights`, nullable revision columns, own service (ADR 0181) |
| Q14 | Who may create an improvement proposal | Session author or `living_course_review`; group ≥ `min_group_size`; 3 per session per day |
| Q15 | Remediations for non-builder courses | Grounded on tutor course chunks; none for topics without indexable text |
| Q16 | Partitioning | Opt-in command above 50M rows, monthly range partitions, no `pg_partman` |
| Q17 | Redact personal data in learner text before the model | Yes, e-mail and phone patterns, on by default (`ai.redact_learner_text`) |
| Q18 | Survey languages | EN always; PL/ZH only from published translations, otherwise EN fallback |

## 15. Owner-only inputs (cannot be defaulted by the agent)

1. Accept ADRs 0180 and 0181 (and ADR 0055, still Proposed) — covered by the advance approval unless revoked; the
   status flip to Accepted is the owner's.
2. Native review of the Polish and Chinese survey and retention-check strings (as for ADR 0096).
3. `ANTHROPIC_API_KEY` and the eval cap for the `insights_improve` live eval (`living-course:eval --live`).
4. Review of the `living-course` change by the owner of that package (LX-2).
5. Confirmation that the provider-terms link on the data-flow page reflects the account the hosted demos use
   (only the account holder can see the contract terms).

## 16. TODO changes with this plan

- Phase 4 header: link to this plan and the LX milestones.
- T8 "(new) `TopicProgressUpdated` event in `courses`…" ticked with the evidence in section 2 (built and tested
  in #277).
- Phase 2.7 survey and impact items: note "(plan: `docs/plans/phase-4-experiments-and-analytics.md`, LX-4 to LX-8)".
