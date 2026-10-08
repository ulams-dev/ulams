# 0014. S3-compatible object storage (MinIO locally) for uploaded files

- Status: Accepted (retroactive)
- Date: 2024-02-16

## Context and Problem Statement

Course files, H5P content, SCORM packages and images were stored on the local disk
(`storage/`). That breaks the stateless, horizontally scaled container model (ADR-0010) and
per-tenant separation (ADR-0013).

## Considered Options

Not recorded.

## Decision Outcome

Use the `s3` filesystem driver (`league/flysystem-aws-s3-v3 ^3.0`) against any S3-compatible
store. For local development and self-hosting, docker-compose runs MinIO, exposed through
Caddy (e.g. `storage.localhost`). In multi-domain mode each tenant gets its own bucket, created
by the init tooling with root MinIO credentials (`AWS_ROOT_ACCESS_KEY_ID`, `AWS_ENDPOINT`,
`AWS_URL_PREFIX`).

The compose image was later switched to `bitnamilegacy/minio` (`278c4a4b`, monorepo era).

### Consequences

- Good: containers hold no user files; any S3 provider can be used in production.
- Good: per-tenant buckets give simple isolation and cleanup.
- Bad: public asset URLs and CORS (notably for H5P) depend on bucket/proxy config.
- Bad: local setup needs an extra service and credentials.

## Evidence

- `19991ed7` 2024-02-16 "local s3" — `api/composer.json`, `api/config/filesystems.php`,
  `api/docker-compose.yml` (minio), `api/.env.example`, `api/docker/conf/Caddyfile`.
- `976aebb9` 2024-02-16 "Merge … into feature/minio_s3".
- `bc5c354c` 2024-09-26 "Saas multidomain v01" — `api/docker-compose.yml`.
- `api/docs/multidomain.md` — MinIO variables.
