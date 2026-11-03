# Plan: authoring time and cost metric, report builder with saved reports, Notion integration token

Status: draft, waiting for owner approval. ADR 0194 is Proposed with this plan. Nothing here is implemented.
TODO items: the `(new)` items marked "plan `authoring-metric-and-report-builder.md`" in `docs/ROADMAP-TODO.md`
(Phase 2.6, Phase 3, Phase 6.5), plus notes on the existing Phase 5 "Admin UX … saved filters", Phase 5 "Metrics"
and Phase 6.5 "BI export/API" items.

Why (owner, 2026-10-22, from `docs/reports/market-research-2026-10.md`):

- Authoring cost is the core economic pain: Chapman Alliance measured 49 to 716 hours of work per finished hour of
  e-learning. We claim "source to grounded course in hours", but we only measure the machine half of that claim.
- Poor reporting is the most-cited reason to replace an LMS (Brandon Hall surveys). We have fixed datasets and
  exports, but no way for an admin to build, save, share or schedule their own report, and no BI connector.

Our own measured run (PR #408 case study, `ulams-dev/demo-docs`): 6 lessons and 18 quiz questions from a docs
repository; 35 of 35 blocks cite 12 passages; outline 24 s, lessons, quizzes and checks 6 min 57 s, apply 23 s
(about 7.5 minutes of generation); 27 model calls, USD 0.2841. What the run did **not** record is how long the
author spent reviewing and editing, and how many AI elements they changed. That is the half this plan adds.

Facts below come from `origin/main` at `1d3f9abeb` (2026-10-22). Paths are relative to the repo root.

---

## 1. Goal

1. **Authoring metric.** For every course, a recorded, explainable figure: generation time per stage, LLM cost
   (build and maintenance), author review/edit time (opt-in per tenant), and counts (lessons, questions, blocks,
   cited blocks, AI elements accepted unchanged, edited by the author, revised by AI on request, written by the
   author). Shown in the studio and in admin analytics, exportable, queryable over the API, CLI and MCP, and
   turned into an honest "hours to produce one learner hour" figure on the tenant's own data. No benchmark claims
   in the product.
2. **Report builder.** An admin picks a dataset, columns, filters, grouping and a date range, previews the result,
   saves it, shares it with tenant roles, schedules it by e-mail (existing schedules), exports CSV, JSON lines or
   XLSX, and connects a BI tool to it through a read-only feed.
3. **Notion integration token.** The studio's Notion form accepts a pasted internal integration token as an
   alternative to OAuth, handled as a secret end to end.

## 2. TODO items covered

| # | Item (`docs/ROADMAP-TODO.md`) | Coverage | Milestones |
|---|---|---|---|
| T1 | 2.6 "(new) Authoring time and cost metric per course" | Full | AM-1 to AM-6 |
| T2 | 2.6 "(new) Element provenance for direct author edits" | Full | AM-2 |
| T3 | 6.5 "(new) Report builder with saved reports" | Full | RB-1 to RB-5, RB-7 |
| T4 | 6.5 "(new) BI connector: read-only OData feed per saved report" | Full | RB-6 |
| T5 | 3 "(new) Notion source: paste an integration token in the studio" | Full | NT-1 |
| T6 | 6.5 "BI export/API, scheduled reports, optional business KPI link" (existing, partial) | BI connector half; the KPI link stays in `phase-6-remaining.md` P6R-6 | RB-5, RB-6 |
| T7 | 5 "Admin UX: … saved filters" (existing) | Not covered: list-screen saved views stay in `phase-5-remaining.md` P5R-10 (`admin_saved_views`); saved reports are a separate concept (6.7) | none |
| T8 | 5 "Metrics: time to first course, …" (existing) | Not covered: ADR 0152 / P5R-12; the authoring metric is per course and complements it | none |

Overlap with `docs/plans/phase-6-remaining.md`: P6R-3 plans a `DatasetRegistry` in `compliance`, a
`GET …/report/datasets` endpoint, XLSX and `updated_since`; P6R-4 plans an admin reports screen with a dataset
picker. None of it is built. This plan takes over the registry and the reports screen (RB-1, RB-4) and moves the
registry into a small `reporting` package (6.6, ADR 0194). P6R-3 keeps `learner-insights/overview`, XLSX and
`updated_since`; P6R-4 keeps the compliance dashboard, schedules and certificate screens. If the owner prefers the
registry to stay in `compliance`, only the namespace changes.

## 3. What exists

### 3.1 Generation time and cost

- `course_builder_runs` (`kind` ingest|interview|outline|generate|patch|apply|action|sync, `status`, `stage`,
  `started_at`, `finished_at`). `stage` is overwritten as the run advances (`Pipeline/GenerationService.php`), so
  there is no time per stage on the run.
- `course_builder_steps`: `key`, `stage`, `status`, `attempts`, `cost_micro_usd`, only `created_at`/`updated_at`.
- `course_builder_events` (AG-UI log, `Events/EventLog.php`): `STEP_STARTED`/`STEP_FINISHED` pairs per stage carry
  ms timestamps, but rows are pruned after `course_builder.events_retention_days` (30).
- `ai_calls` (`api/packages/ai`): model, tokens, `cost_micro_usd`, `latency_ms`, `status`, `subject_type`,
  `subject_id`, `user_id`. No course column. Subjects: `course_builder_session`, `course_builder_variant`,
  `living_course_proposal`, `learner_remediation`, `tutor_conversation`. `GET /api/admin/course-builder/sessions/{s}/usage`
  and `ulams builder usage` aggregate per session; `ai:usage` per day and task. Denormalised totals on
  `course_builder_sessions.cost_micro_usd`, `course_builder_steps.cost_micro_usd`, `living_course_proposals.cost_micro_usd`.
  Nothing aggregates cost per course across subjects.

### 3.2 Accepted vs edited

- `course_builder_versions`: `kind` (outline|content|patch|author|restore|import), `origin` (ai|author), `status`,
  `element_id` (element chat patches), `decided_by`, `decided_at`, `ai_call_ids`, `diff_from_parent`.
- Blueprint elements carry `origin` (absent means `ai`). `DriftAbsorber` (admin edits after apply) and
  `CourseImporter` set `author`; `PatchService` flips an element back to `ai` when AI patches it (ADR 0160).
  `OutlineEditor` direct edits create an `author` version but **do not tag the edited elements**.
- Citations live inline (`citations: [frg_…]`) on objectives, lessons, blocks, interactions and questions;
  `Blueprint::citations()` and `Sources/CitationIndex` read them.
- Living Course: `ProposalItem.status` (accepted|rejected|…) and the hash-chained `living_course_audit`.

### 3.3 Author activity

None. No heartbeat, presence or `last_seen` in `front/web`, `admin` or the API. `agent_audit_log` covers scoped
tokens only. ADR 0152 (Proposed, P5R-12) designs anonymous admin task timings without user ids; not built.

### 3.4 Reports, schedules, exports, tokens

- `compliance`: `ComplianceReportService` with datasets `assignments`, `certificates`, `audit` (fixed columns,
  filters `course_id`, `user_id`, `status`, `from`, `to`); `GET /api/admin/compliance/report`,
  `…/report/export?format=csv|jsonl` (`CsvExporter` neutralises `= + - @`), every export logged in
  `compliance_exports`; `DashboardService`; schedules in `compliance_report_schedules` (`dataset`, `filters`,
  `frequency`, `format`, `recipient_ids`, `next_run_at`), sent by `compliance:process` daily at 06:00 through
  `ScheduledReportMail`. Permission `compliance_report`.
- `reports` (legacy Wellms): `GET /api/admin/reports/*`, `GET /api/admin/stats/*`, Excel exports, admin
  `/analytics/reports` pie charts.
- `learner-insights`: `Analytics::summary/elements` per course; groups under `min_group_size`
  (`LEARNER_INSIGHTS_MIN_GROUP_SIZE`, default 10) come back as `smallGroup: true` with no figures.
- Scoped personal access tokens (ADR 0074, `auth/src/Support/TokenScopes.php`, route map
  `auth/resources/token-scopes.php`, fail-closed, `TokenScopeMapCoverageTest`); preset `read-only`. Note:
  `api/admin/compliance*` maps to `certificates`, not `reports`, although `phase-6-remaining.md` says `reports:read`.
- No saved reports, no report definitions, no BI connector, no analytics Postgres views (only `searchable_events`).
- Tenancy: one PostgreSQL database per tenant (`api/packages/tenancy`), no `tenant_id` columns; isolation comes
  from the connection. Route-complete tenant isolation suites are required for new endpoints (ADR 0190).

### 3.5 CLI and MCP

The MCP server is part of the CLI (`front/cli/src/mcp/*`); every CLI command is a tool named from its id
(`compliance.report-export` → `compliance_report_export`), filtered by `--read-only` and `--no-destructive`;
permissions come from the token on the server. Commands are generated from `spec/openapi.json`
(`front/cli/scripts/gen-commands.mjs`) or hand-written (`front/cli/src/commands/*.ts`).

### 3.6 Notion connector

`living-course/src/Connectors/Notion/NotionConnector.php` (`secretFields(): ['token']`) and
`SourceConnections::connect()` accept either an OAuth grant (`secrets.grant`) or a pasted integration token
(`secrets.token`). `living_course_connections.secrets` uses an `encrypted:array` cast and is never serialised.
The studio form (`front/web/src/studio/connect.ts`, `connect-model.ts` `ACCOUNT_SOURCES`) offers only OAuth for
Notion and shows "Signing in with Notion is not set up…" when OAuth has no client id. PR #408 could not build the
Notion case study for this reason.

## 4. Scope

In:

- New package `api/packages/authoring-metrics` (`ulams/authoring-metrics`, `Ulams\AuthoringMetrics`).
- Small additions to `course-builder` (stage timing events, element provenance on direct edits, content hashes).
- New package `api/packages/reporting` (`ulams/reporting`, `Ulams\Reporting`): dataset registry, report
  definition, query compiler, saved reports, OData feed. `compliance` registers its datasets there and keeps its
  schedules, which gain a `saved_report_id`.
- Admin screens (`admin/`): Analytics > Authoring, Analytics > Report builder.
- Studio (`front/web`): a "Production" panel per course, the review time indicator, the Notion token field.
- SDK clients, CLI commands, MCP tools, OpenAPI, docs pages.

Out:

- Any benchmark figure (Chapman or other) inside the product UI, e-mails or exports. Marketing pages are a separate
  owner decision and must cite the tenant's own export, not this feature.
- Per-person productivity views of authors. The data model makes them impossible (6.3).
- Free-form SQL, user-defined calculated columns, joins across datasets (v1 is one dataset per report).
- Direct database access for tenants (Postgres views with a read-only role: rejected, 6.9).
- Parquet output (deferred, 6.9). The business KPI link (P6R-6).

## 5. Milestones (one PR each, in this order unless noted)

| Id | PR | Demo |
|---|---|---|
| NT-1 | `feat(studio): connect a Notion page with a pasted integration token` | paste a token, see the page's sections; the token appears nowhere in logs, events or responses |
| AM-1 | `feat(course-builder): record time per pipeline stage` | after a build, `authoring_stage_timings` lists outline, lessons, quizzes, apply with seconds |
| AM-2 | `feat(course-builder): tag author edits per element and hash AI output` | edit one block in the outline editor; the applied blueprint marks it `author`-edited |
| AM-3 | `feat(authoring-metrics): per-course metric snapshot, API and tenant isolation` | `GET /api/admin/authoring-metrics/courses/{id}` returns time, cost and counts for the PR #408 course |
| AM-4 | `feat(studio,admin): show and export the authoring metric` | studio "Production" panel; admin Analytics > Authoring table with CSV and JSON lines export |
| AM-5 | `feat(authoring-metrics): opt-in anonymous review time` | enable the tenant setting, edit for 5 minutes, see 5 author-minutes on the course; no user id stored |
| AM-6 | `feat(cli): authoring metrics commands and MCP tools; hours per learner hour` | `ulams authoring metrics --course 12 --format json`; the same through an MCP client |
| RB-1 | `feat(reporting): dataset registry with column capabilities; compliance and authoring datasets` | `GET /api/admin/reporting/datasets` lists `assignments`, `certificates`, `audit`, `authoring`, `ai_usage` |
| RB-2 | `feat(reporting): report definitions, query compiler and preview with small-group suppression` | `POST /api/admin/reporting/query` groups assignments by course and month; a group of 4 learners is withheld |
| RB-3 | `feat(reporting): saved reports shared with tenant roles` | save a report, share it with the `tutor` role, a tutor runs it with their own scope |
| RB-4 | `feat(admin): report builder screen` | build, preview, save and export a report in the admin, keyboard only, axe clean |
| RB-5 | `feat(compliance): schedule a saved report by e-mail` | a weekly schedule sends the saved report as CSV to two admins |
| RB-6 | `feat(reporting): read-only OData feed per saved report` | Excel "From OData feed" and Power BI load a saved report with a read-only token |
| RB-7 | `feat(cli): reporting commands and MCP tools` | `ulams reporting run <id> --format jsonl`; an agent lists datasets and runs a saved report |

NT-1 is independent and smallest; it can go first. AM-1 and AM-2 should land soon, because every build that runs
before them loses its stage timings once the AG-UI events are pruned (30 days). RB-1 replaces the registry part of
P6R-3; coordinate so only one of them builds it.

## 6. Design

### 6.1 Generation time (AM-1)

- `EventLog::stepStarted/stepFinished` (and `runStarted/runFinished/runError`) also dispatch Laravel events
  `BuilderStageStarted` / `BuilderStageFinished` (`session_id`, `run_id`, `run_kind`, `stage`, `at`). The
  course-builder package stays unaware of metrics.
- `authoring-metrics` listens and writes `authoring_stage_timings` (6.4). A stage without a finish (crash) is
  closed by the run's `finished_at` and flagged `incomplete`.
- Generation time = sum of stage durations of runs of kinds `outline`, `generate`, `apply`, `sync` (and `patch`
  for element edits, reported separately as "AI edit time"). `ingest` and `interview` are reported separately:
  interview time is the author answering questions, so it belongs to author time, not machine time.
- Backfill command `authoring-metrics:backfill` rebuilds timings from `course_builder_events` that still exist
  (at most 30 days) and from run `started_at`/`finished_at` for older runs (whole-run time only, flagged
  `coarse`).
- Wall time, not summed call latency: parallel lessons overlap, and the author waits for wall time. Summed
  `latency_ms` is kept as a second figure ("model time").

### 6.2 Cost (AM-3)

Authoring cost of a course = sum of `ai_calls.cost_micro_usd` whose subject resolves to the course:

| Subject | Resolves through | Bucket |
|---|---|---|
| `course_builder_session` | `course_builder_sessions.course_id` | build |
| `course_builder_variant` | variant → session → course | build (variants) |
| `living_course_proposal` | proposal → session → course (both `source` and `insights` kinds) | maintenance |
| `learner_remediation`, `tutor_conversation` | course | **excluded**: learner runtime, shown in AI usage, not authoring |

A `SubjectCourseResolver` contract lets each package resolve its own subject types, so `ai` does not learn about
courses. Failed calls (`status` ≠ `ok`) count, because they were paid for; the count of failed calls is shown.
Cost is reported in USD with the price table version that `ai_calls` already used; no re-pricing.

### 6.3 Author review and edit time (AM-5): privacy model

Off by default. A tenant admin turns on `authoring_metrics.review_time` in Settings, after reading a notice that
names exactly what is stored. Model:

- **What is measured:** active time in the studio for a course and in the admin course editor, counted in whole
  minutes. A minute counts when the tab is visible and there was keyboard, pointer or scroll input in the last
  120 s. Time with the tab hidden, the window blurred or no input is not counted.
- **What is stored:** `authoring_activity_minutes` rows `(course_id, minute_start, tab_nonce, surface)`, unique on
  all four. `tab_nonce` is a random value generated per page load and kept only in memory, so two rows from the
  same person on different days cannot be linked. **No user id, no IP, no user agent, no element id.** Several
  tabs of one browser elect one sender with `BroadcastChannel`, so an author with three tabs open counts once.
- **What is shown:** author-minutes per course, per day and in total. Never per person. The API offers no
  breakdown that could single out an author: a course with one author obviously shows that author's time, which
  the notice says plainly.
- **Retention:** raw minute rows are rolled up nightly into `authoring_course_daily` (`course_id`, `day`,
  `author_minutes`, `surface`) and deleted after 7 days; daily totals live as long as the course.
- **Transparency:** while tracking is on, the studio header shows "Review time is recorded for this course
  (anonymous)" with a link to the notice; the notice and `operators/authoring-metrics.mdx` list every field.
- **Switching off** stops collection at once and offers "delete recorded review time" (keeps generation time,
  cost and counts, which are not personal).
- **Legal note in the operator docs (not legal advice):** in the EU, measuring employee activity can need a legal
  basis and, in some countries, works council involvement (e.g. Germany). That is why it is opt-in per tenant,
  anonymous and minute-grained.
- **Endpoint:** `POST /api/admin/authoring-metrics/activity` `{course_id, minutes: [iso minute…], surface,
  tab_nonce}`, batched every 60 s with `navigator.sendBeacon`, max 10 minutes per call, minutes in the future or
  older than 15 minutes rejected, 404 when the setting is off. Accepted only from interactive sessions (scoped PATs
  get 403), so an agent or script cannot inflate review time.

Author time = interview time (6.1, recorded anyway) + review/edit minutes. Without the setting, the metric shows
"author time: not recorded" and no hours figure.

### 6.4 Data model (tenant database)

| Table | Columns | Package |
|---|---|---|
| `authoring_stage_timings` | `id`, `session_id`, `run_id`, `run_kind`, `stage`, `started_at`, `finished_at`, `seconds`, `quality` (exact|coarse|incomplete) | authoring-metrics |
| `authoring_activity_minutes` | `course_id`, `minute_start`, `tab_nonce` (char 22), `surface` (studio|admin) | authoring-metrics |
| `authoring_course_daily` | `course_id`, `day`, `surface`, `author_minutes` | authoring-metrics |
| `authoring_course_metrics` | `course_id` (pk), `session_id`, `generation_seconds`, `ai_edit_seconds`, `interview_seconds`, `model_seconds`, `author_minutes` (null when not recorded), `cost_build_micro_usd`, `cost_maintenance_micro_usd`, `llm_calls`, `llm_calls_failed`, `lessons`, `questions`, `blocks`, `cited_blocks`, `ai_elements`, `ai_accepted_unchanged`, `ai_edited_by_author`, `ai_revised_on_request`, `author_elements`, `learner_minutes_estimate`, `method_version`, `computed_at` | authoring-metrics |
| `saved_reports` | `id` (ulid), `name`, `description`, `owner_user_id`, `definition` (json), `definition_version`, `visibility` (private|roles), `shared_roles` (json), `feed_enabled`, `last_run_at`, timestamps, `deleted_at` | reporting |
| `compliance_report_schedules` | + `saved_report_id` nullable (existing `dataset`/`filters` rows keep working) | compliance |
| `compliance_exports` | + `saved_report_id` nullable, + `channel` (download|schedule|feed|cli) | compliance |

`authoring_course_metrics` is a snapshot, recomputed on `ApplyCompleted`, `CoursePublished`, `ProposalApplied`,
nightly for courses with activity, and by `authoring-metrics:recompute {--course=}`. `method_version` changes when
a definition changes, so exports say which definitions produced a number.

Course-builder additions (AM-2): blueprint elements gain optional `ai_hash` (sha256 of the normalised element
content when AI produced or last revised it) and `edited_by_author` (bool). `OutlineEditor` and `DriftAbsorber`
set `edited_by_author`; `PatchService` sets `ai_hash` and records `revised_on_request` when the patch came from an
author's chat instruction. Blueprint v3 schema gets both as optional fields (old documents valid unchanged).

Classification of an AI element in the applied version: **accepted unchanged** (`ai_hash` matches, never edited),
**revised by AI on request** (element chat patch, no direct edit), **edited by the author** (`edited_by_author`
or hash mismatch), **author-written** (`origin: author` from the start). Elements created before AM-2 are counted
as "unknown" rather than guessed.

### 6.5 "Hours to produce one learner hour" (AM-6)

`(generation_seconds + interview_seconds + author_minutes × 60) / (learner_minutes_estimate × 60)`, shown as
"X h of work per learner hour" only when author time was recorded. `learner_minutes_estimate` reuses the per-lesson
estimate behind "how much is left" (#295) if it is per lesson; otherwise words at 200 per minute plus 1 minute per
question plus the declared duration of interactives and video (the formula is shown in a tooltip and in the docs).

Honesty rules, tested in AM-6:

- The UI and exports show the tenant's own figure, its inputs and the formula. No industry ratio appears anywhere.
- A tenant may enter **its own** previous figure ("our hand-built courses took N h per learner hour") in
  Settings; if set, it is shown next to the measured one, labelled as the tenant's own estimate.
- The tenant-wide figure is a median over courses with recorded author time, with the count of courses
  (`n = 7`); fewer than 3 courses shows "not enough courses yet".
- Maintenance is shown separately (cost and time of accepted Living Course proposals per course), because the
  research names maintenance as the hidden multiplier.

### 6.6 Report builder: datasets (RB-1)

`Ulams\Reporting\Contracts\ReportDataset`:

```php
interface ReportDataset {
    public function key(): string;                    // 'assignments'
    public function label(): string;
    /** @return list<Column> name, type (string|int|decimal|bool|date|datetime|enum), label,
     *  filterOps, groupable, aggregatable, personal (bool), enumValues */
    public function columns(): array;
    public function dateColumns(): array;             // usable for the date range
    public function permission(): string;             // e.g. 'compliance_report'
    public function subjectColumn(): ?string;         // learner id column used for small-group suppression
    public function baseQuery(ReportContext $ctx): \Illuminate\Database\Query\Builder; // scope applied here
}
```

`ReportContext` carries the viewer, their permissions and the scoped-admin `ScopeResolver` result (P6R-7, ADR
0142) when it exists. Datasets in v1: `assignments`, `certificates`, `audit` (moved unchanged from
`ComplianceReportService`, which becomes a thin wrapper so existing routes and CLI commands do not change),
`authoring` (from `authoring_course_metrics`), `ai_usage` (`ai_calls` per day, task, model, subject type; no prompt
text). Built in RB-1 with three additions to the interface: `description()` (for the picker), `aggregateOnly()` and `available()` (false when
an optional package's tables are missing). The datasets are registered by the package that owns the data (`compliance`,
`authoring-metrics`); `ai_usage` ships in `reporting` and needs the new permission `reporting_ai_usage` (admin), because no
existing permission covers AI spend. The authoring dataset exposes cost as `cost_*_usd` decimals, not micro USD.
Planned by P6R-3: `course_health` (aggregates only). Later candidates (open question Q9): `enrolments`
(course access and progress), `quiz_results` (GIFT attempts).

### 6.7 Report definitions, query compiler, privacy (RB-2)

Definition v1 (JSON Schema in `reporting/resources/schemas/report-definition/v1.json`):

```json
{
  "dataset": "assignments",
  "columns": ["course_title", "status"],
  "filters": [{"column": "status", "op": "in", "value": ["overdue", "open"]}],
  "dateRange": {"column": "assigned_at", "preset": "last_90_days"},
  "groupBy": ["course_title", {"column": "assigned_at", "bucket": "month"}],
  "aggregates": [{"fn": "count_distinct", "column": "user_id", "as": "learners"}],
  "sort": [{"column": "learners", "dir": "desc"}],
  "limit": 1000
}
```

- The compiler validates the definition against the schema **and** the dataset's column capabilities, then builds
  the query from `baseQuery()` with bound parameters. Column names come only from the registry; there is no path
  from user input to raw SQL. Operators: `eq, neq, in, not_in, lt, lte, gt, gte, between, contains, is_null`.
  Aggregates: `count, count_distinct, sum, avg, min, max`. Date buckets: day, week, month, quarter, year.
- Preview: `POST /api/admin/reporting/query` returns at most 500 rows plus the total; run and export stream all rows
  (`cursor()`), capped at 1 000 000 rows per export.
- **Small-group suppression:** for a grouped report on a dataset with a `subjectColumn`, every group with fewer
  distinct subjects than `reporting.min_group_size` (default: `learner_insights.min_group_size`, 10) is replaced by
  one "withheld (fewer than 10 learners)" row; totals exclude withheld groups' values so they cannot be recovered by
  subtraction (complementary suppression: if exactly one group is withheld, the next smallest is withheld too).
  Datasets marked aggregate-only (`course_health`) refuse ungrouped reports.
- **Personal columns:** row-level output of a column marked `personal` (names, e-mails) needs the dataset's
  permission (`compliance_report` today) and is logged in `compliance_exports`; aggregates never need personal
  columns.
- Definitions are versioned (`definition_version`); when a dataset drops or renames a column, saved reports using
  it show "needs repair" instead of failing silently.

### 6.8 Saved reports, sharing, schedules (RB-3, RB-5)

- Separate from the list-screen saved views of P5R-10 (`admin_saved_views` store a table's filter query string for
  one screen). A saved report stores a definition. The admin UI reuses P5R-10's `useTableQueryState` for its own
  list.
- Visibility: `private` (owner) or `roles` (Spatie role names of the tenant). Only the owner or an admin with
  `reporting_manage` edits or deletes.
- **A shared report always runs with the viewer's own permissions and scope.** Sharing shares the definition, not
  the data: a scoped partner admin who opens a report shared by a full admin sees only their groups' rows, and a
  viewer without the dataset permission sees "you cannot run this report".
- Schedules: `compliance_report_schedules.saved_report_id`. At send time the report runs **for each recipient**
  with that recipient's permissions (rows can differ per recipient); a recipient who lost access is skipped and the
  schedule owner is told in the next mail. Formats csv, jsonl and xlsx (XLSX from P6R-3). The command stays
  `compliance:process`; sending moves to one queued job per recipient so a large report does not block the run.
- Permissions: new `reporting_read` (run reports shared with you), `reporting_create` (build and save),
  `reporting_manage` (edit others' reports, enable feeds). Seeded for admin; `reporting_read` for tutor.

### 6.9 BI connector: options and recommendation (RB-6)

| Option | For | Against |
|---|---|---|
| A. Postgres views + a read-only role per tenant | Native in every BI tool; fast | Exposes each tenant database on the network (one database per tenant, many self-host targets cannot open the port); small-group suppression and scoped admins cannot be enforced once a BI tool can group freely; couples customers to internal tables; bypasses token audit and revocation |
| B. OData v4 read-only feed per saved report | Native "OData feed" connector in Excel, Power BI and Tableau (the tools L&D teams use); paging and metadata standard; served by the same compiler, so permissions, scope, suppression and audit all apply; no new dependency for the subset we need | We implement and test a subset of a large spec; Looker Studio, Metabase and Superset have no OData connector |
| C. Parquet over HTTP | Compact, typed; DuckDB, Spark, pandas read it from a URL | New PHP dependency for writing Parquet; no native connector in Excel; one more format to test |
| D. Existing CSV / JSON lines exports with stable URLs and `updated_since` | Already built; works in Looker Studio (CSV URL), Metabase/Superset via a cron script or DuckDB `read_json` | No metadata, no paging contract; each BI tool needs a recipe |

**Recommendation: B, with D kept and documented.** One OData v4 entity set per saved report with `feed_enabled`:

- `GET /api/reporting/odata/` (service document), `…/$metadata` (CSDL XML generated from the dataset columns),
  `…/Report_{ulid}` with `$select`, `$top`, `$skip`, `$count` and server-driven paging (`@odata.nextLink`, 5 000
  rows a page). No `$filter`, `$apply` or `$expand` in v1: the saved report defines filters and grouping; a client
  asking for an unsupported option gets `501` with an OData error body. JSON only (`odata.metadata=minimal`).
- Auth: a scoped personal access token with `reports:read` (ADR 0074), sent as Bearer or as the password of HTTP
  Basic (user name ignored), which is how Excel and Power BI send credentials to an OData feed (Azure DevOps
  Analytics uses the same pattern). Feed requests are recorded in `agent_audit_log` (reads of the `reports` area are
  already recorded) and `compliance_exports` (`channel = feed`). The token can be limited to one saved report
  (`api_token_meta.resource_ids`, new nullable column) so a BI dashboard credential cannot read anything else.
- Rate limit per token (60 requests a minute) and the per-export row cap.
- D stays: the saved report's CSV and JSON lines URLs work with the same token, and the docs carry recipes for
  Looker Studio, Metabase and Superset. C is deferred until a customer asks; A is documented as "not offered".

### 6.10 API, CLI and MCP

Authoring metrics (`authoring-metrics`, token area `builder`):

| Method and path | Purpose | Permission |
|---|---|---|
| `GET /api/admin/authoring-metrics/courses?from=&to=&course_id[]=` | list of course snapshots | `authoring_metrics_read` (admin) |
| `GET /api/admin/authoring-metrics/courses/{course}` | one course with stage timings and element classification | `authoring_metrics_read`, or the course's author |
| `GET /api/admin/authoring-metrics/courses/export?format=csv\|jsonl` | export | `authoring_metrics_read` |
| `GET /api/admin/authoring-metrics/summary?from=&to=` | tenant medians, hours figure, n | `authoring_metrics_read` |
| `POST /api/admin/authoring-metrics/activity` | review minutes (6.3) | any studio author; interactive sessions only |

Reporting (`reporting`, token area `reports`; the route map gets `api/admin/reporting*` and `api/reporting/odata*`):
`GET /api/admin/reporting/datasets`, `POST /api/admin/reporting/query`, `GET|POST|PUT|DELETE
/api/admin/reporting/saved[/{id}]`, `GET /api/admin/reporting/saved/{id}/run?format=json|csv|jsonl|xlsx`, OData in 6.9.
The prefix `reporting` avoids the legacy `api/admin/reports*` routes of the Wellms `reports` package.

CLI (generated from OpenAPI where possible; MCP tool names follow `mcpName`):

| Command | MCP tool | Toolset |
|---|---|---|
| `ulams authoring metrics [--course] [--from --to] [--format table\|json\|csv]` | `authoring_metrics` | `analytics` |
| `ulams authoring summary` | `authoring_summary` | `analytics` |
| `ulams reporting datasets` | `reporting_datasets` | `analytics` |
| `ulams reporting query --file def.json` | `reporting_query` | `analytics` |
| `ulams reporting list` / `show <id>` / `run <id> --format` | `reporting_list`, `reporting_show`, `reporting_run` | `analytics` |
| `ulams reporting save --file def.json --name` / `delete <id>` | `reporting_save`, `reporting_delete` (destructive, confirm token) | `analytics` |

All read tools are available with `--read-only`. The MCP tool descriptions say that numbers are the tenant's own
measurements and that withheld groups are withheld on purpose (so an agent does not "fix" them).

### 6.11 UI

- **Studio** (`front/web`): a "Production" panel on the course page and in the publish summary: generation time per
  stage, cost (build, maintenance), counts with the accepted/edited/revised/author split as a bar with a text table,
  author time or "not recorded" with a link to the setting, the hours figure when available, "Export" (CSV, JSON).
  The review time indicator in the studio header (6.3). New strings in en, pl, zh.
- **Admin** (`admin/`, antd): Analytics > Authoring (`/analytics/authoring`): course table, tenant medians with
  `n`, export; Settings > Authoring metrics: review time toggle with the notice, own baseline field.
  Analytics > Report builder (`/analytics/report-builder`): dataset picker, column list with checkboxes, filter rows,
  group-by and aggregate pickers, date range presets, live preview table (500 rows), "withheld" rows explained
  inline, Save (name, description, visibility, roles), Export, Schedule, "Connect a BI tool" (feed URL, token
  creation limited to this report, copyable Excel/Power BI steps). Saved reports list with owner, shared roles, last
  run.
- Accessibility: every control keyboard-operable, charts have a data table, withheld rows announced as text, axe
  in the admin Playwright CI job (mocked API, as in P6R-1) and the `web-a11y` job for the studio panel. WCAG 2.2 AA.

### 6.12 Notion integration token (NT-1)

- The Notion choice in the studio account form gets two methods: "Sign in with Notion" (shown only when OAuth is
  configured) and "Paste an integration token" (always shown). With no OAuth client id, the token method is the
  default instead of the dead end message.
- Input: `type="password"`, `autocomplete="off"`, `spellcheck="false"`, a show/hide toggle; soft prefix check
  (`ntn_` or the legacy `secret_`) with a hint, not a hard block. Help text: create an internal integration in
  Notion, share the page with it, paste the token.
- Sent once, in the body of `POST …/sessions/{s}/sources/connect` as `secrets.token`, over the existing CSRF/BFF
  path. The server verifies it with one call to the Notion API (`GET /v1/users/me`, then the page) before saving,
  and stores it in `living_course_connections.secrets` (`encrypted:array`, tenant `APP_KEY`, already in place).
- After saving, the UI shows "token ending …abcd" only; "Replace token" opens an empty field; deleting the
  connection deletes the secret. A Notion `401` marks the connection `needs_auth` and the studio asks for a new
  token.
- Never logged: the request field names `token` and `secrets.*` are added to the exception and request-log
  redaction list (`dontFlash`, the error reporter's scrubber, the CLI's verbose HTTP log) and are excluded from
  AG-UI event payloads, `living_course_audit` entries and validation error messages. A test posts a known token and
  greps the log channel, the events table, the audit table and the JSON response for it.

### 6.13 Tenant isolation

Each new endpoint gets a route-complete isolation test (ADR 0190): two tenants, every route listed with a valid
request, tenant B's ids answer 404 on tenant A's host, list endpoints never include B's rows. Specific cases: a
saved report id from tenant B, an OData feed URL with tenant A's token on tenant B's host (401), an activity post
for a course id of tenant B (404), a schedule recipient id from tenant B (422). The `TokenScopeMapCoverageTest`
fails until every new route is mapped.

## 7. Tests

- AM-1: stage timings from scripted runs on the fake driver (outline, parallel lessons, apply, crash mid-stage);
  backfill from events and the coarse path; wall time vs summed latency.
- AM-2: blueprint v3 schema accepts both new fields and old documents unchanged; outline edit, drift absorb, chat
  patch and import set the right flags; classification table test with one element per class.
- AM-3: cost resolver per subject type, tutor and remediation excluded, failed calls counted; snapshot recompute on
  each trigger; the PR #408 numbers reproduced from a recorded fixture (27 calls, USD 0.2841, 6 lessons, 18
  questions, 35/35 cited blocks).
- AM-5: setting off → 404 and nothing stored; minute counting rules (hidden tab, idle, future, stale, batch cap);
  PAT rejected; no column that could hold a user id (schema test); rollup and 7-day deletion; client leader-tab
  election (Vitest); "delete recorded review time".
- AM-6: hours formula, `n` threshold, own-baseline labelling, and a test that no exported or rendered string
  contains a benchmark figure list (`49`, `716`, `79 h`, `184 h`, `490 h`, "Chapman").
- RB-2: schema and capability validation (unknown column, wrong operator, SQL in a value), bound parameters,
  bucket grouping, row cap, suppression including the complementary case and totals, aggregate-only datasets.
- RB-3: sharing by role, viewer scope applied, owner/manager edit rules, "needs repair" after a column change.
- RB-5: per-recipient run with different scopes, skipped recipient reported, legacy schedules unchanged.
- RB-6: OData service document and `$metadata` validated against the OData CSDL schema; paging; unsupported options
  → 501; Basic and Bearer auth; a token limited to one report cannot read another; recorded in both logs. A manual
  check with Excel and Power BI Desktop is listed in the PR (no CI for those tools).
- NT-1: form model tests (methods shown with and without OAuth), token never in log, events, audit or response;
  `401` → `needs_auth`; studio Playwright spec on the mocked API with axe.
- Everywhere: tenant isolation (6.13), scope map coverage, the existing suites and linters, H5P and SCORM unchanged.
- LLM: nothing here calls a model; the fake driver is enough.

## 8. New dependencies

None. OData is a hand-written read-only subset (JSON responses plus generated CSDL XML). XLSX comes from
`maatwebsite/excel`, already an API dependency (P6R-3). Parquet, which would need one, is deferred.

## 9. Migrations

New tables in 6.4; nullable columns on `compliance_report_schedules`, `compliance_exports` and `api_token_meta`;
blueprint v3 optional fields (no data migration). Permissions seeded by the packages' seeders and an upgrade step
(ADR 0081) for existing tenants. All migrations run per tenant database.

## 10. Risks

- **Review time is read as surveillance.** Mitigated by opt-in, no user id, minute grain, the visible indicator, the
  delete action and docs; residual risk is that small teams can still infer a person. The notice says so.
- **Review time is undercounted** (reading a printout, thinking away from the keyboard) or overcounted (a tab left
  open with occasional scrolling). The 120 s idle rule bounds the second; the docs say the figure is active screen
  time, a lower bound of real effort.
- **A flattering number used as a benchmark.** The product never compares with industry figures; exports carry the
  formula and `method_version`. Marketing use needs a separate owner decision.
- **Free grouping leaks small groups.** Suppression with complementary suppression and totals that exclude
  withheld groups; aggregate-only datasets; tests on the edge cases.
- **OData subset disappoints** users who expect `$filter`. The saved report is the filter; we document that and
  can add `$filter` on eq/range later.
- **Two registries.** If P6R-3 builds the registry in `compliance` first, RB-1 becomes a move. Coordinate before
  either starts.
- **Stage timings lost** for builds before AM-1 older than 30 days. Coarse timings from the run row remain.

## 11. Open questions (recommended defaults; work proceeds on the defaults until answered)

| # | Question | Recommended default |
|---|---|---|
| Q1 | Review time tracking default? | **Off**, opt-in per tenant by an admin, with the notice |
| Q2 | Store any author identity with review minutes (for per-author coaching)? | **No**; random per-tab nonce only (same stance as ADR 0152) |
| Q3 | Does interview time count as author time? | **Yes**, shown as its own line |
| Q4 | Do tutor and remediation costs count as authoring cost? | **No**; they appear in the `ai_usage` dataset |
| Q5 | Allow a tenant-entered "previous hours per learner hour" baseline? | **Yes**, optional, labelled as the tenant's own estimate |
| Q6 | Where does the dataset registry live? | **New `reporting` package**; `compliance` registers its datasets (instead of P6R-3's registry inside `compliance`) |
| Q7 | Small-group threshold for reports? | **Reuse `learner_insights.min_group_size` (10)** through `reporting.min_group_size` |
| Q8 | BI connector? | **OData v4 read-only subset per saved report**, CSV/JSON lines URLs kept; Parquet deferred; Postgres views not offered |
| Q9 | Extra datasets in v1 beyond compliance, authoring and AI usage? | **None in v1**; `enrolments` and `quiz_results` as follow-up items |
| Q10 | Token area for compliance routes (`certificates` today, plan text says `reports`)? | **Map `api/admin/compliance/report*` and `…/schedules*` to `reports`**, keep the rest on `certificates`; one fix commit in RB-1 |
| Q11 | Run shared reports with the viewer's or the owner's permissions? | **Viewer's** (no privilege escalation through sharing); schedules run per recipient |
| Q12 | Learner minutes estimate source? | **Reuse the #295 estimate if per lesson, else words/200 wpm + 1 min per question + declared media duration** |
| Q13 | Show the hours figure on the public landing with our own data? | **Not in this plan**; separate owner decision after a real customer-like run with recorded review time |
