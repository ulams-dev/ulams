# 0041. SeaweedFS replaces MinIO; per-tenant S3 identities; server-side reads use the internal endpoint

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/leftovers-0-2.md` (L0-03)

## Context and problem statement

Storage today has three problems:

- **The MinIO image.** The compose stack runs `bitnamilegacy/minio:latest`, an unmaintained image of
  the AGPL-3.0 MinIO server. Upstream stopped publishing community builds in 2025.
- **Shared keys.** All tenants use the platform's S3 access key. The only isolation between tenants
  is the bucket name (`ulams-<slug>`, ADR 0007).
- **Public URLs from inside containers.** Server-side code reads files through their public URL
  (`storage.localhost`), which inside a container resolves to the container itself. This breaks
  Image topic creation, for example.

## Considered options

1. SeaweedFS (Apache-2.0) with its S3 gateway and identities.
2. RustFS (Apache-2.0), which is MinIO-compatible but young.
3. Keep MinIO, pinned to the last community image.
4. Garage (AGPL-3.0). Rejected for licence reasons.

## Decision

Option 1, confirmed by a compatibility spike that runs the same S3 test suite against MinIO,
SeaweedFS and RustFS:

- **Checks.** The suite covers path-style URLs, presigned GET, a public-read bucket policy,
  multipart uploads and denial by identity.
- **Fallback.** If SeaweedFS fails a check that RustFS passes, use RustFS and record the result here.
- **Per-tenant identities.** Each tenant gets its own S3 identity, limited to its bucket, through a
  `StorageIdentityProvisioner` contract. A `NullProvisioner` keeps shared keys for external S3, where
  operators manage IAM.
- **Server-side reads.** Server code reads objects through the disk API (internal endpoint) and never
  through the public URL.

## Consequences

- Good: a maintained, permissively licensed default.
- Good: tenant credentials no longer grant access to other tenants' buckets.
- Good: Image topics work in containers.
- Bad: existing dev volumes must be migrated with `rclone` (documented).
- Bad: the identity API differs per backend, which is why it sits behind a contract.
- Default pending #42.
