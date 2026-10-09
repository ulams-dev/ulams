# living-course: courses that stay in sync with their sources

Watches the sources of a course built with the Course Builder (an upload, a Git repository, web
pages), detects what changed at fragment level without a model, finds the lessons and quiz questions
that cite the changed passages, and proposes cited updates the author reviews as one diff. Learner
progress is preserved by explicit rules and every decision lands in a tamper-evident audit trail.
Records: ADR 0030 (revisions and proposals), 0031 (change detection), 0032 (connectors), 0033
(progress rules), 0034 (audit trail); plan `docs/plans/phase-3.md`.

- Author guide: `front/docs-site/src/content/docs/creators/course-builder.mdx` (sections on sync and updates)
- Settings, env variables, commands: `front/docs-site/src/content/docs/admin/living-course.mdx`
- Internals, endpoints, events: `front/docs-site/src/content/docs/developers/living-course.mdx`
- Writing a connector plugin: [`docs/living-course/connector-plugins.md`](../../../docs/living-course/connector-plugins.md)

```
Connector (upload | git | url | plugin) ─▶ Revision (numbered per source, fragments with file_path)
   ─▶ FragmentDiff (deterministic: unchanged | changed | moved | added | removed; no model)
   ─▶ ImpactAnalyzer (citations ─▶ elements) ─▶ StalenessService (element_status)
   ─▶ AnalysisService (one grounded call per group, run kind `sync`, cost caps) ─▶ UpdateProposal + items
   ─▶ DecisionService (accept / reject / regenerate) ─▶ ApplyService (blueprint version kind `update`)
   ─▶ ProgressRules + LearnerNotice, AuditLog (hash chain, append-only trigger), Notifier
```

```bash
vendor/bin/phpunit --testsuite living-course                         # fake driver, recorded live answers replayed
php artisan living-course:eval --fixtures=all --author=1             # free, fake driver
php artisan living-course:poll                                       # due sources (scheduled every 15 minutes)
php artisan living-course:backfill                                   # first revision for existing sessions
```

Source text is untrusted: it only reaches the model inside a delimited block of the user turn, and
the model's output is validated against a schema and must cite fragments of the new revision.
Outbound requests use `Ulams\Core\Http\SafeHttp`. LMS entities change only through the Course
Builder applier and the domain services; `tests/Unit/GuardsTest.php` enforces this.
