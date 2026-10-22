# 0194. Authoring metrics come from recorded runs and opt-in anonymous review minutes; reports are saved definitions over one dataset registry, exposed to BI tools as a read-only OData feed

- Status: Proposed
- Date: 2026-10-22
- Plan: `docs/plans/authoring-metric-and-report-builder.md`; TODO Phase 2.6 "(new) Authoring time and cost metric
  per course", Phase 6.5 "(new) Report builder with saved reports" and "(new) BI connector"

## Context and problem statement

The market research (`docs/reports/market-research-2026-10.md`) names authoring cost (Chapman: 49 to 716 hours per
finished hour) and poor reporting (top switch reason, Brandon Hall) as the two main pains. We record LLM cost per
call and builder runs, but not time per stage, not how much of the AI output the author changed, and not how long
the author worked. Reports are three fixed compliance datasets with CSV/JSON lines export and schedules; there are
no saved or shared report definitions and no BI connector. Each tenant has its own PostgreSQL database.

## Decision drivers

- An honest per-course "hours to produce" figure from the tenant's own data, never a benchmark claim.
- Author activity must not become per-person surveillance; EU employee-monitoring rules apply to many tenants.
- Reports must keep the existing protections: small-group suppression (`min_group_size`), scoped admins, token
  scopes and audit, tenant isolation by database.
- Works with the BI tools L&D teams use (Excel, Power BI) without opening tenant databases.
- No new dependencies unless needed.

## Considered options

Authoring time:

- A1. Derive author time from `course_builder_versions` timestamps (gaps between edits).
- A2. Opt-in, minute-grained activity from the studio and admin editor, stored without user identity.
- A3. Per-user activity logging with a per-author breakdown.

Reporting:

- R1. Fixed datasets only, more export formats (today plus P6R-3).
- R2. A dataset registry with column capabilities, JSON report definitions compiled into bound queries, saved and
  shared by role; run with the viewer's permissions.
- R3. Free SQL against read-only views.

BI connector:

- B1. Postgres views and a read-only database role per tenant.
- B2. OData v4 read-only feed per saved report.
- B3. Parquet over HTTP.
- B4. CSV/JSON lines URLs with recipes only.

## Decision

**A2, R2, B2 (with B4 kept).**

- A new package `authoring-metrics` records time per builder stage from domain events (`BuilderStageStarted/Finished`
  dispatched by `EventLog`), resolves `ai_calls` cost to courses through a per-package `SubjectCourseResolver`
  (build and maintenance; tutor and remediation excluded), and keeps a per-course snapshot with counts and the
  accepted / edited by author / revised on request / author-written split, based on new optional blueprint
  fields `ai_hash` and `edited_by_author`.
- Review time is off by default. When a tenant admin enables it, the studio and admin editor send active minutes
  (visible tab, input in the last 120 s) keyed by course, minute and a random per-page-load nonce. No user id, IP,
  user agent or element id is stored; raw minutes are rolled up to daily course totals and deleted after 7 days;
  only course totals are shown; scoped tokens cannot post activity.
- The "hours per learner hour" figure is computed only from recorded data, shown with its inputs, formula,
  `method_version` and course count, and the product contains no industry ratio.
- A new package `reporting` holds the dataset registry (`ReportDataset` with typed columns, capabilities,
  `personal` flags, a subject column and a scope-aware base query), the report definition schema v1, the compiler
  (registry columns only, bound parameters), small-group suppression with complementary suppression, saved
  reports shared by tenant role, and the OData feed. A shared report always runs with the viewer's permissions;
  scheduled reports run per recipient. `compliance` registers its datasets and keeps its routes and schedules.
- BI tools read a saved report through an OData v4 subset (service document, generated `$metadata`, `$select`,
  `$top`, `$skip`, `$count`, server paging; other options answer 501), authenticated with a `reports:read` scoped
  token as Bearer or Basic password, optionally limited to one report, rate limited and logged.

## Consequences

- Good: the authoring claim becomes measurable per tenant, including the author's half, without identifying
  authors; maintenance cost is visible next to build cost.
- Good: one compiler enforces permissions, scope, suppression and audit for the UI, schedules, exports, CLI, MCP
  and BI, so no path bypasses them.
- Good: Excel and Power BI connect natively; nothing exposes a tenant database; no new dependency.
- Bad: review time is a lower bound of real effort (offline work is invisible) and small teams can still infer a
  person; the notice must say both.
- Bad: we maintain a subset of OData and must answer "why no `$filter`"; Looker Studio, Metabase and Superset still
  need the CSV/JSON lines recipes.
- Bad: the registry moves out of `compliance` compared with `phase-6-remaining.md` P6R-3, which must be coordinated.
- Rejected: A1 measures gaps, not work, and is wrong in both directions; A3 creates per-person productivity data
  the product does not need. R3 and B1 cannot enforce suppression or scope once a tool can group freely, and B1
  needs network access to every tenant database. B3 needs a new PHP dependency for little gain over B2 and B4 and
  is deferred.
