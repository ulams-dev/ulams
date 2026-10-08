# 0011. Docker image on escolalms/php with PHP-FPM + Supervisor, Caddy as reverse proxy

- Status: Accepted (retroactive)
- Date: 2023-02-13

## Context and Problem Statement

The API is distributed as a Docker image (`escolalms/api`, built on release since
`79daf661`, 2022-04-21, for amd64/arm64). It must run PHP, queue workers and the scheduler,
and be reachable over HTTP(S) together with admin, front and storage.

## Considered Options

Evidenced sequence: `thecodingmachine/php:7.4-v4-apache` (2021) → `devilbox/php-fpm:8.0`
+ nginx (2021–2022) → own `escolalms/php` base + Caddy (2023) → Caddy outside the API
container (2024).

## Decision Outcome

- Base image is the project's own `escolalms/php` (`dbddff48`, 2022-04-21), now
  `escolalms/php:8.3-alpine` (`f5cc5f45`, 2025-01-10).
- Processes inside the container (php-fpm, Horizon/queue, scheduler) are managed by
  Supervisor (`e2d31bc0`, `821330f6`).
- nginx config was removed and Caddy adopted (`92c778ac`, `2e205efb`, 2023-02).
- 2024-10-04 Caddy was taken out of the API container; it runs as a separate `caddy` service
  in docker-compose that reverse-proxies API, admin, front and MinIO. The image exposes
  php-fpm on 9000 and got a `HEALTHCHECK` using `artisan health:check`
  (`spatie/laravel-health`).

### Consequences

- Good: one image, many roles; health checks usable by orchestrators.
- Good: Caddy gives automatic HTTPS and short config for many (sub)domains (ADR-0013).
- Bad: owning the base image (`escolalms/php`) means maintaining PHP extension builds
  (e.g. excimer).
- Bad: Supervisor-in-container hides worker crashes from the orchestrator.

## Evidence

- `05ceebba` 2021-03-03 — `FROM thecodingmachine/php:7.4-v4-apache`.
- `cbfb1571` 2021-09-22 — `FROM devilbox/php-fpm:8.0-prod`.
- `dbddff48` 2022-04-21 "docker build" — `FROM escolalms/php:8-prod`; `79daf661` — `docker-build.yml`.
- `92c778ac` 2023-02-06 "Caddyfile"; `2e205efb` 2023-02-13 "remove nginx" — deletes `api/docker/conf/nginx/*`.
- `b49b22bb` 2024-10-04 "Add healtcheck & clearup dockerfile, remove caddy from conatiner as
  there is only reverse proxy now" — `api/Dockerfile`, `api/config/health.php`.
- `f5cc5f45` 2025-01-10 "docker update alpine 8.3" — `api/Dockerfile`.
