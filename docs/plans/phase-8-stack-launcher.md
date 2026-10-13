# Phase 8: one-command stack launcher

## Approval and scope

Approved by the owner in chat on 2026-10-13: implement, make assumptions, test end to end,
update the landing and documentation, and continue without asking for confirmation.

Provide `ulams stack up` with Docker Compose or Kubernetes and development/production modes.
Development means a local trial with the Phase 2 Course Builder's fake AI driver and no paid
provider. Production uses generated credentials, persistent storage, non-debug settings and
AI disabled until configured. CLI installations carry their templates; no source checkout,
Bash, Helm, Compose-to-Kubernetes converter or remote shell script is required.

## Design

- Render inspectable Compose/Kubernetes configuration, retain secrets on resume, pin a complete
  ULAMS image release, generate APP_KEY and Passport RSA keys, provision an initial academy
  through existing tenant services, and wait for readiness. Add render/status/down commands.
- Include PostgreSQL, Valkey, the API/workers/scheduler, H5P, PDF, MJML, learner front, admin,
  Caddy and a persistent S3-compatible store. Commerce remains the existing optional roadmap.
- Use SeaweedFS mini (Apache-2.0, standalone service) for bundled S3. Its authenticated S3 API
  and bucket policy support are verified with the real API; avoid relying on obsolete MinIO
  community image distribution. No new JavaScript dependency: yaml/zod already exist.
- Docker uses an isolated project/network. Kubernetes uses a dedicated namespace, PVCs and a
  singleton application pod for API/H5P shared files; databases/storage get separate pods.
  This is a single-server baseline, not an untested HA claim. A default StorageClass is needed.
- Production uses Caddy ACME TLS and operator-provided DNS; private/internal TLS is explicit.
  Kubernetes supports a LoadBalancer gateway; local tests use port-forward/NodePort.
- Add an optional private API transport to the learner server: preserve the public tenant Host
  while reaching the internal gateway, so it does not depend on public DNS hairpin routing.
- Never overwrite arbitrary directories, regenerate existing keys, erase volumes on normal down,
  change the current kubectl context, or modify the existing local Docker stack.

## Verification

Registry/help/schema/dry-run, credential permissions, validation, cancellation, failure/resume,
secret redaction, manifests and fresh packed CLI installation. Real Docker and disposable kind
Kubernetes E2E for development and production/private TLS: service readiness, platform/tenant
login, authoring and Phase 2 builder, object storage, H5P/PDF services, restart/persistence and
cleanup. Run relevant existing CLI/web tests and documentation checks. Publish only claims
proved by these runs; include prerequisites, resource requirements and recovery instructions.

## E2E findings incorporated

- Quote YAML scalars for Kubernetes' YAML 1.1 parsing, including on/off values.
- Disable Kubernetes service environment injection: an admin Service otherwise injects an
  ADMIN_PORT tcp URL that collides with SeaweedFS configuration.
- Bundle a native multi-architecture MJML renderer instead of the old amd64-only image.
  It uses the same /v1/render contract, pinned MIT MJML, request limits and disabled file includes.
- Add TENANCY_PORT without changing stored hosts, so local nonstandard ports survive tenant
  provisioning, trusted origins, content URLs and callbacks.
