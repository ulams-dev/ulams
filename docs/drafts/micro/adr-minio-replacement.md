# Replace MinIO with SeaweedFS and per-tenant S3 identities

- Status: Proposed
- Date: YYYY-MM-DD

## Context and problem statement

MinIO is currently used for object storage in our system. However, it has some limitations and operational overheads that we aim to address. We are considering replacing MinIO with SeaweedFS or RustFS, or switching to a cloud S3 service, to improve our storage solution.

## Considered options

1. **SeaweedFS**: An open-source distributed file system that is designed for scalability and performance.
2. **RustFS**: A hypothetical file system written in Rust, which could offer high performance and safety.
3. **Keep MinIO**: Continue using MinIO with potential improvements or optimizations.
4. **Cloud S3 only**: Migrate entirely to a cloud S3 service like AWS S3, Google Cloud Storage, or Azure Blob Storage.

## Decision

After evaluating the options based on the criteria of licence, S3 compatibility, per-tenant identities, and operational weight, we recommend using SeaweedFS. SeaweedFS is open-source, compatible with S3 APIs, supports per-tenant identities, and has a lower operational weight compared to cloud S3 services.

## Consequences

- Good: SeaweedFS is open-source and has a permissive licence.
- Good: It supports S3 APIs, ensuring compatibility with existing tools and services.
- Good: It supports per-tenant identities, improving security and isolation.
- Bad: It may require additional setup and configuration compared to MinIO.
- Bad: It is not as widely adopted as cloud S3 services, which might lead to less community support and fewer third-party integrations.

## Alternatives considered

- **RustFS**: While RustFS could offer high performance and safety, it is not yet a mature solution and lacks community support and third-party integrations.
- **Keep MinIO**: While MinIO is familiar and has some operational advantages, it has limitations in scalability and performance that SeaweedFS addresses.
- **Cloud S3 only**: While cloud S3 services are highly scalable and reliable, they come with operational costs and vendor lock-in.

TODO(verify): Verify the operational weight and community support of SeaweedFS.
TODO(verify): Verify the compatibility of SeaweedFS with existing tools and services.
TODO(verify): Verify the support for per-tenant identities in SeaweedFS.
