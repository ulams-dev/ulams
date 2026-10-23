# reporting: datasets for the report builder

The foundation of the report builder (ADR 0194, plan `docs/plans/authoring-metric-and-report-builder.md`). This
release has the **dataset registry** (RB-1), the **report definition, query compiler and preview with small-group
suppression** (RB-2) and **saved reports shared with roles** (RB-3), the **admin screen** (RB-4), **schedules and XLSX** (RB-5) and the **OData feed** (RB-6); the curated CLI
and MCP set follows in RB-7.

- `Contracts\ReportDataset`: a set of rows a report can be built on: `key()`, `label()`, `columns()` (typed
  `Support\Column` with filter operators, `groupable`, `aggregatable`, `personal`), `dateColumns()`, `permission()`,
  `subjectColumn()` (the learner column for small-group suppression), `aggregateOnly()`, `available()` and
  `baseQuery(ReportContext)`, which selects exactly the declared columns and applies the viewer's scope.
- `Services\DatasetRegistry`: the one place that knows which datasets exist. The package that owns the data registers it:
  `compliance` (`assignments`, `certificates`, `audit`), `authoring-metrics` (`authoring`); this package ships `ai_usage`
  (`ai_calls`, without prompt text, errors or users) because `ai_calls` belongs to the platform. A dataset whose tables are
  missing is not offered.
- `GET /api/admin/reporting/datasets` (permission `reporting_read`: admin and tutor) lists the datasets the caller holds
  the dataset permission for, with every column. Token area `reports`.
- `Export\XlsxWriter` writes one-sheet XLSX files as a stream with `ext-zip` and `XMLWriter` (no new dependency;
  PhpSpreadsheet keeps every cell in memory), and `ReportExporter` offers it as `xlsx`.
- `compliance` schedules a saved report (`compliance_report_schedules.saved_report_id`): `SendSavedReportToRecipient` runs
  the report once per recipient with their own permissions; skipped recipients are reported to the schedule's owner.
- `OData\Csdl`, `OData\QueryOptions` and `Http\Controllers\ODataController`: the read-only OData v4 feed of a saved report whose
  `feed_enabled` is on (`api/reporting/odata`, `$metadata`, `Report_{id}`). Only `$select`, `$top`, `$skip`, `$count` and
  `$format=json`; the rest answers 501. 5 000 rows a page, 60 requests a minute per token, every request logged as an export of
  channel `feed`. Auth is a `reports:read` token as Bearer or as the password of HTTP Basic (`StripTokenPrefix` in `auth`); a token
  limited to a report (`api_token_meta.resource_ids`, enforced by `EnforceTokenScopes` through the `resource` option of
  `token-scopes.php`) reads only that report.
- Permissions: `reporting_read`, `reporting_ai_usage` (admin). `ReportingPermissionSeeder` runs with `PermissionsSeeder`.

- `resources/schemas/report-definition/v1.json` is the shape of a report definition; `Definition\DefinitionValidator`
  checks it, then the capabilities of the dataset's columns (operators, groupable, aggregatable, types, personal), and
  returns a `ReportDefinition` or throws `InvalidReportDefinition` with one error per JSON pointer path.
- `Query\QueryCompiler` wraps the dataset's `baseQuery()` as a derived table and builds the grouped or row query with
  bound values; identifiers come from the registry, aggregate functions and date buckets from closed lists.
  `Query\Suppression` withholds groups with fewer distinct learners than `reporting.min_group_size` (default: the
  Learner Insights threshold, 10) plus the complementary group, counted on learners in SQL.
- `Services\ReportRunner::preview()` is the one path that reads data for a report: permission of the dataset, scope in
  `baseQuery()`, suppression, typed values, and the record of row-level personal columns in `compliance_exports`.
- `POST /api/admin/reporting/query` previews a definition (500 rows and the total). A POST that only reads: token
  `reports:read` is enough.

- `saved_reports` (ulid, name, owner, definition, `visibility` private|roles, `shared_roles`, `last_run_at`, soft deletes) and
  `Services\SavedReports`, the one place that decides who sees and changes a report: the owner and `reporting_manage`
  see a private report, role holders see a shared one. A saved report always runs with the permissions of the runner.
  `GET|POST /api/admin/reporting/saved`, `GET|PUT|DELETE .../saved/{id}`, `GET .../saved/{id}/run?format=json|csv|jsonl`.
  A report whose dataset or column is gone shows `needs_repair` and answers 409 when run.
- `Export\ReportExporter` streams CSV (BOM, formula-neutralised) or JSON lines up to `reporting.export_rows` and records
  the export in `compliance_exports` with `saved_report_id` and `channel` (columns added by a `compliance` migration).
- Permissions: `reporting_read` (admin, tutor), `reporting_create` and `reporting_manage` (admin), `reporting_ai_usage` (admin).

Column names the compiler will accept come from the registry only; there is no path from user input to SQL.
`Tests\Concerns\AssertsDatasetContract` is the check every dataset must pass (the base query selects the declared
columns, in order); packages that register a dataset use it in their own tests.

Admin documentation: `front/docs-site/src/content/docs/admin/report-builder.mdx`.
