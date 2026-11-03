# CLI stack services

Deployment manifests are bundled in `front/cli/src/stack`, not copied from an external template
repository. `ulams stack render` writes inspectable Compose and Kubernetes files, and `stack up`
starts the same files with generated private credentials and academy provisioning.

`mjml/` is the native Node MJML service used by both targets. It serves `GET /healthz` and
`POST /v1/render` on private port 15500, accepts MJML JSON or plain MJML, limits bodies to 1 MiB
and disables local file includes. The service is not exposed by the public gateway. MJML is
MIT licensed; it contains no H5P code. Publishing builds both amd64 and arm64 images.

The whole stack includes API, web, admin, H5P, PDF, MJML, PostgreSQL, Valkey, authenticated
SeaweedFS mini S3 storage and Caddy. Production AI is initially disabled and email is logged
until configured; development uses deterministic AI. Persistent keys and storage survive stop.

See [Operators: one-command stack](https://ulams-dev.github.io/ulams/operators/one-command-stack/)
and ADR 0102 for requirements and topology limits.
