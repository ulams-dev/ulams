# webhooks: outgoing, signed event webhooks

Lets an academy (tenant) register https URLs that receive its domain events as signed, versioned JSON:
HMAC-SHA256 signature with a timestamp (replay resistant), retries with exponential backoff through the
queue, a delivery log with every attempt, test sends, replays and SSRF-safe target URLs. Phase 7.3 of
`docs/ROADMAP-PROMPT.md`.

- Guide for receivers and admins: `front/docs-site/src/content/docs/developers/outgoing-webhooks.mdx`
- Endpoints: `api/admin/webhooks/*` (permission `webhook_manage`, token scope `webhooks:read|write`),
  documented in OpenAPI under the tag "Admin Webhooks".
- Events wired today: `course.completed`, `enrolment.created`, `update_proposal.ready`,
  `update_proposal.applied`, `certificate.issued`. Planned types are listed by `GET /api/admin/webhooks/events`.

```
domain event ─▶ EventCatalog (class → type, payload) ─▶ WebhookDispatcher
   ─▶ one WebhookDelivery per enabled, subscribed endpoint (payload stored as sent)
   ─▶ DeliverWebhookJob ─▶ DeliveryRunner ─▶ Ulams\Core\Http\SafeHttp (https, public IPs, pinned address, no redirects)
   ─▶ 2xx: succeeded | else: attempt logged, retry with backoff, after max attempts failed, endpoint disabled after N failed deliveries in a row
```

## Install

Registered in `api/config/app.php`. Then:

```bash
php artisan migrate
php artisan db:seed --class="Ulams\Webhooks\Database\Seeders\WebhookPermissionSeeder"
php artisan vendor:publish --tag=ulams_webhooks.config   # optional
```

A queue worker must run for deliveries; the scheduler runs `webhooks:retry-due` every minute (retries
whose job was lost, and every retry under the synchronous queue) and `webhooks:prune` daily.

## Configuration

`src/config.php`: `api_version`, `max_endpoints`, `max_attempts`, `backoff_seconds`, `timeout`,
`disable_after_failures`, `retention_days`, rate limits. Environment: `WEBHOOKS_ALLOW_INSECURE` (local
development only: http and private addresses), `WEBHOOKS_ALLOWED_HOSTS`, `WEBHOOKS_QUEUE`,
`WEBHOOKS_QUEUE_CONNECTION`.

## Adding an event

Add it to `Support/EventCatalog.php` (class name as a string, description, payload builder, sample) and
to the table in the guide. `tests/Feature/DomainEventsTest.php` fails when an `available` type has no
listener.

## Tests

```bash
vendor/bin/phpunit --testsuite webhooks
```

The receiver is a Guzzle `MockHandler` bound as `webhooks.http_handler`; DNS is replaced through
`SafeHttp::useResolver`, so no test touches the network.
