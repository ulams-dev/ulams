# 0041. SeaweedFS replaces MinIO; per-tenant S3 identities; server-side reads use the internal endpoint

- Status: Accepted
- Date: 2026-10-09
- Accepted: 2026-10-22 (owner approved the plan `docs/plans/phase-8-self-hosting.md` with its defaults; milestone P8-3)
- Plan: `docs/plans/leftovers-0-2.md` (L0-03), `docs/plans/phase-8-self-hosting.md` (4.3)

## Context and problem statement

Storage had three problems:

- **The MinIO image.** The compose stack ran `bitnamilegacy/minio:latest`, an unmaintained image of
  the AGPL-3.0 MinIO server. Upstream stopped publishing community builds in 2025.
- **Shared keys.** All tenants used the platform's S3 access key. The only isolation between tenants
  was the bucket name (`ulams-<slug>`, ADR 0007).
- **Public URLs and local paths on the server.** Server-side code read files through their public URL
  (`storage.localhost`, which inside a container resolves to the container itself) or through
  `Storage::path()`, which on S3 is only the object key.

## Considered options

1. SeaweedFS (Apache-2.0) with its S3 gateway and identities.
2. RustFS (Apache-2.0), which is MinIO-compatible but young.
3. Keep MinIO, pinned to the last community image.
4. Garage (AGPL-3.0). Rejected for licence reasons.

## Decision

Option 1, **SeaweedFS 4.37** (`chrislusf/seaweedfs:4.37`, one tag everywhere, checked by
`scripts/check-pinned-images.mjs`), confirmed by a compatibility test that ran the same S3 suite against
SeaweedFS, RustFS and MinIO (results below).

- **Checks.** `api/tests/S3Compat/S3CompatibilityTest.php` (opt-in, `S3_COMPAT=1`; `deploy/scripts/s3-compat.sh`
  starts the store in a throwaway container) covers every operation the code uses (put with
  `x-amz-acl: public-read`, content type and disposition, multipart over 16 MB, streamed and ranged reads,
  head, delete, delete many, copy, list with prefix, delimiter and pagination, put object ACL, bucket
  create, head, policy and delete), plus presigned GET and PUT, CORS, a public-read policy seen
  anonymously and denial for a limited identity.
- **Fallback.** RustFS only if SeaweedFS fails a check the application needs. It did not (below).
- **Per-tenant identities for the bundled store only.** A `StorageIdentityProvisioner` contract with two
  drivers, chosen by `TENANCY_S3_IDENTITY_DRIVER`:
  - `seaweedfs`: the embedded IAM API on the S3 port (`CreateUser`, `CreateAccessKey`, `PutUserPolicy`,
    `ListAccessKeys`, `DeleteAccessKey`, `DeleteUser`), signed with the admin key. The store runs with
    `-s3.iam.readOnly=false` (its default is read-only). The identity `ulams-<slug>` gets a policy that allows
    object, ACL, tagging and multipart operations and `ListBucket` on `arn:aws:s3:::ulams-<slug>` and its
    objects, nothing else. The embedded IAM API was usable, so the filer identity-file path of the plan
    was not needed.
  - `none` (default): **shared keys** for external S3 (AWS S3, Cloudflare R2, other providers), where the
    operator manages IAM.
- **Where the keys live.** Encrypted on the tenant row (`tenants.storage_key`, `tenants.storage_secret`, the
  plan's only migration), written to the tenant env file as `AWS_ACCESS_KEY_ID` and
  `AWS_SECRET_ACCESS_KEY` (the H5P service already reads them from there). Provisioning gets a
  `storage_identity` step after `bucket`; deleting a tenant revokes the identity before the bucket goes;
  `ulams:tenant:sync-env --rotate-storage-keys` creates identities for existing tenants and adds a new key
  for the others, `--prune-storage-keys` deletes the old ones (two phases, so running workers survive).
  The secret is never in a log, an exception message, the platform API or `var_export`
  (`StorageCredentials`; the IAM failure messages carry the action and the error code only).
- **Reads by name stay public.** The public-read bucket policy serves uploaded files by URL, so another
  tenant's key can read a file whose exact name it knows, as can anyone. Listing, writing, overwriting
  and deleting are refused (proved against a real store, `StorageIsolationTest`). With
  `TENANCY_S3_PUBLIC_READ_POLICY=false` even reads are refused.
- **Server-side reads go through the disk.** No server code fetches a public URL (`NoPublicStorageFetchTest`,
  TD-3) and none treats `Storage::path()` as a file on a disk that may be S3 (`NoLocalPathOnObjectStorageTest`).
  A library that needs a file gets `Ulams\Core\Storage\LocalCopy::with($disk, $path, $callback)`: the file
  itself on a local disk, else a temporary copy that is deleted afterwards. No `internal_endpoint` setting
  was needed. Fixed callers: PDF topics, Markdown image sizing, H5P export paths, the images service,
  SCORM archive reads, course and topic file deletion, the file manager's directory delete.
- **The public host is read-only.** Caddy answers 405 to anything but GET, HEAD and OPTIONS on
  `storage.<host>`, because the S3 port also serves the IAM API and every write.
- **Migration.** `deploy/scripts/storage-migrate.sh` (`make storage-migrate`) copies every `ulams*` bucket
  with `rclone copy` (never sync, move or delete), verifies by downloading both sides, re-applies the
  bucket policies (`ulams:tenant:ensure-buckets`) and issues the tenant keys. Dry run, `--verify-only`
  and idempotent re-runs; the source is only read.

## Compatibility results

Run on 2026-10-22 with `deploy/scripts/s3-compat.sh` (31 tests, among them the Laravel `s3` disk; the identity tests apply to SeaweedFS only).

| Check | SeaweedFS 4.37 (`weed mini` and `weed server -s3.config`) | RustFS (latest) | MinIO (`bitnamilegacy/minio`, baseline) |
|---|---|---|---|
| Bucket create, head, list, delete | pass | pass | pass |
| PutObject with public-read ACL, content type, disposition | pass (the ACL is accepted and ignored, see below) | pass | pass |
| Streamed and ranged reads, head | pass | pass | pass |
| Delete, delete many, copy | pass | pass | pass |
| List with prefix, delimiter, pagination | pass | pass | pass |
| Multipart over 16 MB (SDK `upload()` and `MultipartUploader`, abort) | pass | pass | pass |
| Presigned GET and PUT, expiry | pass | pass | pass |
| Public-read policy seen anonymously, anonymous write and list refused | pass | pass | pass |
| Policy replace and read | pass | pass | pass |
| CORS (put, get, preflight) | pass | pass | 501 (server wide only) |
| Unicode and spaces in keys | pass | pass | pass |
| Empty a bucket with many objects, delete it | pass | pass | pass |
| PutObjectAcl, top-level key | pass | pass | 501 |
| PutObjectAcl, key with `/` | **500** | pass | 501 |
| Limited identity: own bucket (put, get, list, copy, multipart, delete) | pass | not run | not run |
| Limited identity: another bucket (get, head, list, put, delete, copy, policy, delete bucket, create bucket) | 403 on all | not run | not run |
| Rotate, prune and revoke keys | pass | not run | not run |

**The one failure.** SeaweedFS 4.37 answers 500 to `PutObjectAcl` on a key that contains `/` (its log shows
it looking the entry up without the directory part); a top-level key works. It is not a needed operation:
the only caller is `ProcessVideo`, which calls `setVisibility('public')` on files that were written with the
public ACL and that the bucket policy already makes public, and Laravel returns `false` instead of throwing.
MinIO, the baseline, fails the same call with 501, so nothing regressed. RustFS passes everything, but it is
pre-1.0, has no verified identity API for this use and would need a second provisioner, so the plan's
fallback was not taken. This decision is cheap to reverse: the contract keeps the identity API behind one
interface, and the compatibility job runs against RustFS every night for information. The test records the
failure as a skip, so it turns green on its own when a newer SeaweedFS fixes it.

Other findings:

- SeaweedFS accepts `x-amz-acl: public-read` and ignores it: `GetObjectAcl` shows the owner only and an
  anonymous read of such an object without a bucket policy is 403. Public reads on the bundled store come from
  the bucket policy alone, so keep `TENANCY_S3_PUBLIC_READ_POLICY=true` (the default) with SeaweedFS and let
  `ulams:tenant:ensure-buckets` (run by `init.sh`) apply it to the platform bucket.
- SeaweedFS rejects `s3:ListBucketMultipartUploads` and `s3:ListMultipartUploadParts` in a policy as invalid
  actions. Multipart uploads work without them, also for a limited identity.
- The AWS SDK retries 5xx answers, so a failing call is repeated up to three times.
- `weed mini` also opens master, volume, filer and admin UI ports with no authentication. They must stay on
  the internal network (documented in operators/storage).

## Consequences

- Good: a maintained, permissively licensed default.
- Good: tenant credentials no longer grant write or delete access to other tenants' buckets on the bundled store.
- Good: Image, PDF, Markdown, SCORM and cache paths work in containers and on S3.
- Good: a deleted topic or course now removes its files (the topic cleanup tested a class from a namespace that
  no longer exists and never ran).
- Bad: existing dev volumes must be migrated with `storage-migrate.sh` (documented, tested, never destructive).
- Bad: the identity API differs per backend, which is why it sits behind a contract. With external S3 tenants
  still share the platform key; operators manage IAM there.
- Bad: reading a file by exact name remains public by design; private reads need
  `TENANCY_S3_PUBLIC_READ_POLICY=false` and another way to serve files.
- Open (owner only, needs accounts): the checks against AWS S3 and Cloudflare R2 with real credentials are not
  run by the project (GitHub issue linked from the P8-3 pull request).
