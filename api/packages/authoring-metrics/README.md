# authoring-metrics: how long a course takes to produce

Measures the authoring side of the Course Builder from what the builder already does: time per pipeline
stage (this release, milestone AM-1), later cost, counts and opt-in anonymous review time. Records: ADR 0194;
plan `docs/plans/authoring-metric-and-report-builder.md`.

- `EventLog` in `course-builder` dispatches `BuilderStageStarted` and `BuilderStageFinished` next to every AG-UI
  step and run event; the builder does not know this package.
- `RecordStageTimings` turns them into `authoring_stage_timings` (wall time per stage, to the millisecond). A failure
  in the listener is logged and never reaches the build.
- Rows are kept as long as the session, unlike the AG-UI events (30 days).
- `php artisan authoring-metrics:backfill [--session=ID] [--dry-run]` rebuilds timings of earlier runs: exact from
  events that still exist, one `coarse` whole-run row from the run row for older runs. Idempotent; `ulams:upgrade`
  runs it once per tenant.

Admin and developer documentation: `front/docs-site/src/content/docs/operators/authoring-metrics.mdx`.
