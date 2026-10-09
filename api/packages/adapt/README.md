# Adapt (Path B)

Adapt Learning course sources as versioned JSON, built into SCORM by an isolated GPL-3.0 worker
(ADR 0013). **Off by default**: `ADAPT_SOURCE_ENABLED=true` turns the API on; the worker runs in the
optional compose profile `adapt` (not built yet, see the ADR).

Path A, uploading an Adapt export made with `adapt-contrib-spoor`, needs none of this: it is a SCORM
upload, labelled `source_format = adapt` (`packages/scorm`).

## API (permission `adapt_manage`; 404 when the flag is off)

| Method | Path | |
|---|---|---|
| GET / POST | `/api/admin/adapt` | list; create from `{"source": {course, config, contentObjects, articles, blocks, components}}` |
| GET / DELETE | `/api/admin/adapt/{id}` | show (status draft, building, built or failed; `scorm_id` of the build) / delete |
| GET | `/api/admin/adapt/{id}/source?version=` | the JSON of a version |
| POST | `/api/admin/adapt/{id}/versions` | add a version (`source`, `change_note`) |
| POST | `/api/admin/adapt/{id}/build` | queue a build of the current version (202) |

Validation (no GPL code): the six parts, unique `_id`s, `_parentId` links down the hierarchy,
articles under pages, components from the core-plugin allow-list (`config.php`). Errors come back
with paths, e.g. `/components/3/_component`.

Build: `POST <ADAPT_BUILDER_URL>/build` with `X-Internal-Token: <ADAPT_BUILDER_TOKEN>` and
`{"id", "source"}`; the worker answers with a SCORM zip, which is imported through the SCORM upload
(upload guard, safe extraction, content origin).

## Tests

```bash
vendor/bin/phpunit --testsuite adapt
```

The worker is faked (`Http::fake`) with a generated spoor-style SCORM zip.
