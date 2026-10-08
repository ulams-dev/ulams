# 0009. Redis-backed queues run by Horizon (single domain) or a queue:work loop (multi-domain)

- Status: Accepted (retroactive); Horizon partially superseded by ADR-0013 in multi-domain mode
- Date: 2021-09-22

## Context and Problem Statement

Notifications, video processing, imports, reports and broadcasts must run outside the HTTP
request. The queue backend and the worker supervisor had to be chosen, and later had to work
with many tenants on one deployment (ADR-0013).

## Considered Options

Not recorded for the original choice. For multi-domain: the `gecche` Horizon provider vs.
plain `queue:work --domain=…` per domain (both appear in history).

## Decision Outcome

- 2021-09-22 PR #66 added Redis to docker-compose and `laravel/horizon` with
  `config/horizon.php` and a `HorizonServiceProvider`. Supervisor runs Horizon and, since
  `821330f6` (2022-04-21), the scheduler (`cron.sh` replaced by `scheduler.conf`).
- 2024-12-04 the multi-domain Horizon provider was removed "as it's not working for many
  domains" (`1c92ea67`). In multi-domain mode `init_multidomains.sh` disables `horizon.conf`
  and enables `multidomain_queue.conf`, which runs `queue.sh`: a loop over shuffled
  `MULTI_DOMAINS` calling `artisan queue:work --queue=default,broadcast,video --max-jobs=20
  --stop-when-empty --domain=$domain` (`eddd25ab`, `ba4e1170`).
- 2026-03 real-time broadcasting moved to a self-hosted Pusher-compatible Soketi server
  (`f51c206f`, `pusher/pusher-php-server`) with a dedicated broadcast worker (`64b80294`).

### Consequences

- Good: single-domain installs get Horizon's dashboard and balancing.
- Good: multi-domain works with any number of tenants without one worker per tenant.
- Bad: two different worker setups to maintain and debug.
- Bad: the round-robin loop adds latency proportional to the number of domains and gives no
  Horizon metrics for tenants.

## Evidence

- `cbfb1571` 2021-09-22 "Feature/docker redis update (#66)" — `api/config/horizon.php`,
  `api/app/Providers/HorizonServiceProvider.php`, `api/docker-compose.yml`.
- `821330f6` 2022-04-21 "scheduler to supervisor" — `api/docker/conf/supervisor/scheduler.conf`.
- `1c92ea67` 2024-12-04 "remove horizon multidomain provider as it's not working for many domains".
- `eddd25ab` 2024-10-10 "making queue worksaand fixing multidomain tool" — `api/queue.sh`,
  `multidomain_queue.conf`.
- `f51c206f` 2026-03-06 "Added soketi"; `64b80294` 2026-03-13 "Added broadcast queue".
