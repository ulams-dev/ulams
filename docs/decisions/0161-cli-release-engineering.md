# 0161. CLI release engineering: release-please, npm trusted publishing, attested binaries

- Status: Proposed
- Date: 2026-10-21
- Plan: `docs/plans/phase-7-course-as-code.md` (section 10); amends ADR 0077 (Proposed); owner questions #76, #77

## Context and problem statement

ADR 0077 chose npm, bun-compiled binaries and a Docker image, with an `NPM_TOKEN` secret and Sigstore
signatures. Long-lived npm tokens are a supply-chain risk, the repository has no changelog tooling, and code
signing for macOS and Windows costs money or an application process the owner has not chosen yet.

## Decision

- Versions and changelogs come from Conventional Commits through release-please, components `front/cli` and
  `front/create-ulams`, tags `cli-v<semver>` and `create-ulams-v<semver>`.
- npm publishes use **trusted publishing (OIDC)** with provenance from `release-cli.yml`. One bootstrap publish
  (by hand or with a short-lived granular token) is needed because a trusted publisher can only be added to an
  existing package; no long-lived `NPM_TOKEN` remains.
- Binaries are smoke-tested on Linux, macOS and Windows runners and published with `SHA256SUMS` and GitHub
  artifact attestations (Sigstore).
- macOS and Windows signing steps exist in the workflow behind optional secrets; default unsigned (ad-hoc on
  macOS), with a SignPath Foundation application recommended for Windows and the Apple Developer Program only
  on demand.
- `ghcr.io/ulams-dev/ulams-cli` is built by the release workflow with provenance and SBOM.

## Consequences

- Good: no long-lived publishing secret; reproducible, verifiable artifacts; signing can be turned on later
  without code changes.
- Bad: the first npm publish needs the owner; unsigned binaries show OS warnings when downloaded in a browser.
