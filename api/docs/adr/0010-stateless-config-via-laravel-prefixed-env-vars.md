# 0010. Stateless containers configured by LARAVEL_-prefixed environment variables

- Status: Accepted (retroactive)
- Date: 2022-08-02

## Context and Problem Statement

The API is shipped as a Docker image (ADR-0011) and deployed on Kubernetes and docker-compose.
Laravel normally reads a hand-edited `.env` file, which does not fit immutable images,
horizontal scaling or per-tenant configuration.

## Considered Options

Not recorded.

## Decision Outcome

The container is configured only by environment variables. At start-up `docker/envs/envs.php`
(introduced in `20479084`) writes every variable starting with `LARAVEL_` into `.env` with the
prefix stripped (`LARAVEL_APP_NAME` → `APP_NAME`). The `.env` is ephemeral and regenerated on
every restart; `init.sh` (made Kubernetes-friendly in `51d60960`) then runs migrations, seeds,
Passport keys and storage links. In multi-domain mode the same mechanism builds
`.env.<domain>` files from `<DOMAIN>_<VAR>` variables (`envs_multidomains.php`, ADR-0013).
Runtime-editable settings live in the DB instead (ADR-0005); files live in object storage
(ADR-0014).

Documented in `api/docs/enviromental-variables.md`: "Application is designed to be stateless".

### Consequences

- Good: the same image runs everywhere; config comes from compose/k8s manifests and secrets.
- Good: replicas can be added freely because no state lives in the container.
- Bad: editing `.env` inside a container has no lasting effect, which surprises Laravel devs.
- Bad: the prefix convention must be applied to every new variable.

## Evidence

- `20479084` 2022-08-02 "docker update to use env files" — `api/docker/envs/envs.php`
  (`strpos($env, "LARAVEL_") === 0`), `api/Dockerfile`.
- `51d60960` 2022-09-28 "init.sh for k8s" — `api/init.sh`.
- `028a4d38` 2022-10-03 "Update init.sh".
- `ba4e1170` 2024-10-11 "documentation and multidomain queue" — `api/docs/enviromental-variables.md`,
  `api/docs/init-script.md`.
