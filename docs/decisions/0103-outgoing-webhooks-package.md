# 0103. Outgoing webhooks as a package with Stripe-style signatures and the shared SSRF-safe client

- Status: Proposed
- Date: 2026-10-20
- Plan: `docs/plans/cli.md` (7.9); spec `docs/ROADMAP-PROMPT.md` 7.3

## Context and problem statement

Tenants and integrations need to react to what happens in an academy (a learner finishes a course, is
enrolled, receives a certificate, an update proposal needs review) without polling the API. Roadmap 7.3
asks for Stripe-style webhooks: signed, retried, replayable, with a delivery log, test sends and versioned
events. The URLs are chosen by tenant admins, so the sender must not become a way into the internal network
(SSRF, DNS rebinding). Only inbound webhooks existed.

## Considered options

1. A new package `api/packages/webhooks` that listens to the domain events that already exist, with its own
   tables, queue jobs and admin API, reusing `Ulams\Core\Http\SafeHttp` (ADR 0032) for every request.
2. Extend `notifications` (it already fans events out to channels).
3. Run an open-source webhook service (Svix, Hook0) next to the API.

## Decision

Option 1.

- **Own package.** `notifications` is about messages to users with templates and per-user channels; webhooks
  are per-tenant integrations with their own secrets, retry state and log. A separate service adds a
  deployment unit and a second tenancy model for a feature that fits the queue the API already runs.
- **Events.** Only events that exist as domain events are wired (`CourseFinished`, `CourseAssigned`,
  `UpdateProposalReady`, `ProposalApplied`, `PdfCreated` for course certificates). The classes are named as
  strings, so the package loads without them, and a listener never throws into the code that fired the event.
  Types not yet backed by an event (order paid, refunds, course published) are listed as `planned` and cannot
  be subscribed to. An event fired once per reviewer is delivered once per endpoint (`dedupe_key`).
- **Payload and versions.** `{id: evt_…, object: "event", type, api_version, created, data.object}`. An endpoint is
  pinned to the `api_version` at creation; a version only gains fields; a JSON Schema per version is published
  (ADR 0104). The event `id` is the receiver's idempotency key and is the same for retries and replays.
- **Signature.** `Ulams-Signature: t=<unix>,v1=<hex>` with `HMAC-SHA256(secret, "<t>.<raw body>")`, fresh `t` on
  every attempt, five minutes of tolerance recommended to receivers. The header allows several `v1` values for a
  later rotation window. The secret (`whsec_…`) is stored encrypted with the application key (it is needed to
  sign), returned once on creation and rotation, and shown only as a four-character hint afterwards.
- **Delivery.** One `webhook_deliveries` row per event and endpoint holds the exact body that is signed; one
  `webhook_attempts` row per HTTP attempt holds the status, a truncated answer and the error. 2xx succeeds;
  otherwise retry after 1 min, 5 min, 30 min, 2 h, 6 h, 12 h, 24 h (eight attempts), as delayed queue jobs. A
  scheduler sweep re-queues overdue retries (lost jobs, synchronous queue). An attempt is claimed with a
  compare-and-set on the attempt counter, so duplicate jobs cannot double-send. 25 failed deliveries in a row disable
  the endpoint with a reason.
- **SSRF.** Every request goes through `SafeHttp`: https only, no credentials in the URL, every resolved address
  public, the checked address pinned for the connection (DNS rebinding), no redirects, response size cap. The URL is
  also checked when the endpoint is saved for an early error. Operators may allow http and private addresses for
  development (`WEBHOOKS_ALLOW_INSECURE`) or restrict a tenant to listed hosts.
- **Access.** Permission `webhook_manage` (admin role), token scope area `webhooks`, throttles per user (60 per
  minute; 10 per minute for test sends and replays, which call third parties), at most 20 endpoints per tenant.
  Data lives in the tenant database, so another tenant's ids never resolve.
- **Retention.** 30 days of deliveries and attempts (`webhooks:prune`).

## Consequences

- Good: no new infrastructure; the delivery log is the audit trail and the replay source.
- Good: a payload schema, a reference verifier and the SSRF guard are shared and tested.
- Bad: the signing secret is recoverable from the database with the application key; rotating the application key
  invalidates stored secrets (endpoints must be re-created or rotated). A KMS-backed secret store is out of scope.
- Bad: delivery is at least once and unordered; receivers must deduplicate on the event id.
- Open: an admin screen, `ulams webhooks listen` (forwarding to localhost), plugin-registered event types (roadmap 7.4).
