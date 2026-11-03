# 0102. One-command Docker and Kubernetes stack launcher

- Status: Proposed
- Date: 2026-10-13

## Context and problem statement

The owner requested a CLI equivalent of Create-LMS-App for the whole ULAMS stack, usable for
local Phase 2 trials and production deployments with Docker or Kubernetes.

## Decision

Bundle declarative deployment generation in the CLI command registry. Provide resumable stack
up/render/status/down operations, generated private credentials, matching image releases,
readiness checks and tenant provisioning through the existing API services. Keep destructive
volume removal explicit. Production requires domain/DNS and uses TLS; development binds locally
and uses fake AI. Disable these host-management commands as MCP tools.

Bundle SeaweedFS mini as a separate authenticated Apache-2.0 S3 service; keep H5P (GPL) separate
from MIT code. Reuse published application images and existing yaml/zod dependencies. Use a
singleton Kubernetes application pod so API and H5P share persistent configuration without
requiring RWX storage, plus separate PostgreSQL, Valkey and object-store PVCs. State clearly
that HA and managed-cloud infrastructure provisioning are separate concerns.

Route optional server-side API traffic through an internal gateway while retaining tenant Host
and public protocol. Browser URLs remain public. Never disable TLS verification globally.

The existing MJML image is amd64-only. Publish a native Node MJML service image using pinned
MIT MJML and the existing /v1/render interface, bounded requests and no local file includes.
TENANCY_PORT appends a gateway port to public URLs while stored hosts remain port-free.

## Consequences

An installed CLI needs Node 22.12+ and Docker Compose v2 or kubectl access to an existing cluster.
Production needs DNS and reachable TLS ports; Kubernetes needs persistent-volume provisioning
and an accessible gateway. Operators retain generated files, credentials and backups. The
launcher owns only its Compose project or Kubernetes namespace, leaving existing deployments
and kubeconfig context unchanged.

## References

- https://github.com/EscolaLMS/Create-LMS-App
- https://github.com/seaweedfs/seaweedfs/blob/4.37/README.md
- https://kind.sigs.k8s.io/docs/user/quick-start/
