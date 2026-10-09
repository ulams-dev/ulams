# H5P (Laravel side)

Thin Laravel integration for the isolated H5P service in `api/h5p` (Lumi, GPL). The API never
imports H5P code: it only talks to the service over HTTP, so the GPL boundary stays intact
(see `LICENSING.md` and ADR 0015).

## What it does

- `H5PServiceClient` calls the service with the shared `X-Internal-Token` (server-to-server calls
  act as the system user).
- `H5PContentService` lists H5P contents and deletes the unused ones.
- Admin endpoints (Swagger-documented): `GET /api/admin/h5p/contents`, `DELETE /api/admin/h5p/unused`.
  Creating, editing, playing, uploading and downloading are served by the service itself at `/h5p/*`.

## Configuration

| Variable | Default | Purpose |
|---|---|---|
| `H5P_SERVICE_URL` | `http://h5p:8080` | Base URL of the service, without the `/h5p` prefix |
| `H5P_INTERNAL_TOKEN` | none | Shared secret, must match the service |
| `H5P_SERVICE_TIMEOUT` | `300` | Request timeout in seconds |

## Tests

`./vendor/bin/phpunit --testsuite h5p` (HTTP calls to the service are faked).

## Further reading

Operator documentation: `front/docs-site/src/content/docs/operators/h5p.mdx`.
