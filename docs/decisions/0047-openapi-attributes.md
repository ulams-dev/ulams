# 0047. OpenAPI as PHP attributes; doctrine/annotations removed; spec snapshot test

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/leftovers-0-2.md` (L0-11)

## Context and problem statement

Our OpenAPI docs live in docblocks: 242 files with `@OA\` docblocks, read through
`doctrine/annotations`, which is abandoned. swagger-php 6 reads attributes by default, has
deprecated docblocks and drops them in 8.0. A custom `DocBlockConfigFactory` keeps docblocks working.
Five packages are missing from the scan paths, so the SDK's generated types do not cover the Course
Builder.

## Considered options

1. Convert to `#[OA\…]` attributes with a converter script, and guard the result with a snapshot
   of the generated spec.
2. Keep docblocks until swagger-php 8.
3. Hand-write a static `openapi.yaml`.

## Decision

Option 1:

- **Baseline.** A normalised baseline spec is committed as a test fixture.
- **Conversion.** A dev-only converter rewrites one batch of packages per commit, and the generated
  spec must equal the baseline after each batch.
- **Scan paths.** The missing packages are added in a separate, reviewed baseline update.
- **Clean-up.** The factory and `doctrine/annotations` are removed.
- **SDK types.** CI regenerates the SDK types and fails on drift.

## Consequences

- Good: no abandoned dependency, ready for swagger-php 8, and builder endpoints are typed in the SDK.
- Bad: a large mechanical diff; batching and the snapshot keep it reviewable.
