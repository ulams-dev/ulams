# 0077. CLI distribution: npm, bun-compiled binaries and a Docker image; no telemetry

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/cli.md` (11); owner questions #76, #77

## Context and problem statement

Agents and CI need `npx ulams`, a binary without Node, and an image for other CIs. Options for binaries:
Node single executable applications (still "active development", no cross-compilation, ESM entry only on
Node 26) or `bun build --compile` (cross-compiles all targets from one runner, MIT).

## Decision

- npm package `ulams` (pending #77), tsup bundle with `@ulams/sdk` inlined, Node ≥ 22.12, provenance.
- Binaries for linux x64/arm64, darwin arm64/x64 and windows x64 with `bun build --compile`, SHA256 sums
  and Sigstore signatures; macOS/Windows code signing only once an identity exists (pending #76).
- `ghcr.io/ulams-dev/ulams-cli`, non-root, signed like the other images.
- Tags `cli-v<semver>`, versioned independently of the API.
- No telemetry: no analytics, crash reports or update pings.

## Consequences

- Good: one release workflow covers all channels; air-gapped users can use the binary or image.
- Bad: bun is a second runtime; command code is fetch-only and binaries get smoke tests to catch
  differences.
