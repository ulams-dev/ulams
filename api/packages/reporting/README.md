# reporting: datasets for the report builder

The foundation of the report builder (ADR 0194, plan `docs/plans/authoring-metric-and-report-builder.md`). This
release (milestone RB-1) is the **dataset registry**; the query compiler, saved reports, the admin screen, schedules
for saved reports and the OData feed follow in RB-2 to RB-7.

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
- Permissions: `reporting_read`, `reporting_ai_usage` (admin). `ReportingPermissionSeeder` runs with `PermissionsSeeder`.

Column names the compiler will accept come from the registry only; there is no path from user input to SQL.
`Tests\Concerns\AssertsDatasetContract` is the check every dataset must pass (the base query selects the declared
columns, in order); packages that register a dataset use it in their own tests.

Admin documentation: `front/docs-site/src/content/docs/admin/report-builder.mdx`.
