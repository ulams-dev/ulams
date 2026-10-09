# Interactive

The **Interactive** topic type ([ADR 0086](../../../docs/decisions/0086-interactive-topic-type.md)): an
author-uploaded web app (a zip with `index.html`, assets and `ulams-interactive.json`) played in an
opaque sandbox from the tenant content origin, talking to the lesson page through the `ulams-ix` bridge
([ADR 0087](../../../docs/decisions/0087-interactive-bridge-protocol.md), `front/interactive-bridge`).

- Packages and **immutable versions** (`interactive_packages`, `interactive_package_versions`); files on the
  package disk under `interactive/<storage_key>/v<n>/` (`storage_key` is a random UUID).
- A topic (`topic_interactives`) points to a package, **pins a version** (or `follow_latest`), plays a
  step range and completes by `on_open`, `on_range_end`, `on_complete` or `on_score`.
- Uploads go through the upload guard (kind `interactive`, 50 MB by default, zip safety), an extension
  allow-list, and the manifest JSON Schema (`resources/schemas/ulams-interactive/v1.json`) plus a text
  alternative for every step and locale.
- The **CSP** of every file comes from the API per version (`InteractiveCsp`, wired through
  `ulams_uploads.content_headers`): no `unsafe-eval`, `connect-src 'self'` (plus the manifest `network`
  list only with `ulams_interactive.allow_network`), `frame-ancestors` limited to the tenant front and admin.
- **No token in the frame.** Progress comes from the learner's own session through the front BFF.

## API

| Method | Path | Notes |
|---|---|---|
| GET / POST | `/api/admin/interactive` | `interactive_manage`: list (`search`), upload a `.zip` (`file`, `title`, `change_note`) |
| GET / PUT / DELETE | `/api/admin/interactive/{id}` | show with the current manifest, rename, delete (409 while topics use it) |
| GET / POST | `/api/admin/interactive/{id}/versions` | list, upload a new version (the manifest `id` must match) |
| GET | `/api/admin/interactive/{id}/preview?version=` | `{url, nonce, version, manifest}` for an admin preview, nothing tracked |
| POST | `/api/interactive/launches/{topic}` | learner (`attend` gate): `{url, version, manifest, topic}`; 404 when disabled, 503 without a content origin |
| GET | `/api/interactive/showcase` | public, throttled (60/min), nothing tracked: the first Interactive topic of the first published public course that has one, in the shape of a launch; 404 when there is none or the type is off, 503 without a content origin. For landing pages |
| POST | `/api/interactive/topics/{topic}/events` | learner, throttled: a batch of at most 40 bridge events, validated against `resources/schemas/ulams-ix/v1` |

## Settings

`ulams_interactive.enabled` (default on) and `ulams_interactive.allow_network` (default off) are
administrable config keys; `INTERACTIVE_DISK`, `UPLOADS_INTERACTIVE_MAX_MB` and `INTERACTIVE_ENABLED` are
environment variables.

## Tests

`./vendor/bin/phpunit --testsuite interactive`. Fixture packages are folders in `tests/Fixtures/packages/`
zipped at test time; no binary zips are committed.
