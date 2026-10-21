# 0190. Tenant isolation for every endpoint through one route-walking test and a public-route allow-list

- Status: Proposed
- Date: 2026-10-21
- Plan: `docs/plans/tech-debt-and-quality-bar.md` (TD-3); spec `docs/ROADMAP-PROMPT.md` (quality bar)

## Context and problem statement

The quality bar asks for tenant isolation tests for every endpoint. Each tenant has its own database and
its own Passport key pair (ADR 0007), so a token issued by one tenant must be rejected by every other
tenant. About a dozen packages test this with a hand-written endpoint list (for example
`api/packages/compliance/tests/Api/TenantIsolationTest.php`); the API has about 640 route definitions and
most packages have no such test. A hand-written list goes stale as soon as someone adds a route, and
nothing tells them to.

## Considered options

1. Keep writing one isolation test per package and review them.
2. One test that walks `Route::getRoutes()`, calls every route behind `auth:api` with a token signed by
   another tenant's key pair and expects 401; every route without `auth:api` must be listed with a reason.
3. Only the opt-in two-tenant HTTP test (`api/packages/tenancy/tests/Integration`), run nightly.

## Decision

Option 2, with option 3 run nightly as well.

- `api/tests/Integrations/TenantIsolationCoverageTest.php` uses the technique of the existing tests (swap the
  Passport key pair, forget the guards), moved into a shared trait. Route parameters are filled with `1`, the
  body is empty; authentication rejects before binding or validation.
- `api/tests/fixtures/public-routes.php` lists every route that is not behind `auth:api`, each with a reason
  (own signature such as webhooks and LTI, read from the tenant database chosen by host, health). A new public
  route without an entry fails the test.
- Routes with their own token types (LRS, H5P internal, LTI tool) name the test that covers them.
- Per-package isolation tests stay: they also check data separation and author isolation, which a 401 probe
  does not.
- The two-tenant HTTP test runs in the nightly workflow, against real databases and buckets.

## Consequences

- A new endpoint is covered the moment it is registered, with no extra test to write.
- Public routes become a reviewed list instead of an accident.
- The probe proves authentication isolation only; data isolation inside a tenant (authors, learners) stays the
  job of package tests and policies.
- Routes that do not reject a foreign token before running code are bugs, fixed rather than allow-listed.
