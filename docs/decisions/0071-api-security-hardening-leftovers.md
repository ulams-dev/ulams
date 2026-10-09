# 0071. API security hardening: auth on admin routes, allow-listed payment input, bounded group walks

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/leftovers-0-2.md` (L0-06, L0-07, L0-08); audit `docs/reports/phase-0-audit.md`

## Context and problem statement

The Phase 0 audit found medium-severity gaps in inherited packages: admin tag routes reachable by
guests, an unbounded and unthrottled batch image endpoint, client-controlled payment parameters
(currency, trial, amount), a `payProduct` that skips purchasability, a vouchers search whose OR
branches leak rows, and recursive group walks that can loop or stop after one level.

## Decision

- **Authentication first.** Admin routes sit behind `auth:api`; the permission is then checked in a
  policy (`tags_list` is new, granted to admin and tutor).
- **Batch image rendering is bounded and always throttled.** 20 paths per request, 4096 px per side,
  and `images.render` applies to `POST` whatever `rate_limiter_status` says.
- **Server values win.** Only an allow-list of client fields reaches the payment drivers, and the
  server's price, currency and trial values are merged last.
- **One bounded group walk.** `GroupTree::descendants()` is breadth-first with a visited set and a
  depth limit of 10.
- **No dev packages in the demo image**, so debug routes do not exist there.

## Consequences

- Good: each fix has a test that fails without it; no new dependencies, no migrations.
- Bad: legacy clients that send extra payment fields now have them ignored; installs that predate
  `tags_list` must re-run the permission seeder.
