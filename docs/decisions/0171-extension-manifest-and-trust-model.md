# 0171. Extensions: operator-installed composer packages with an `ulams-extension.json` manifest, per-point contract versions and optional Sigstore signing

- Status: Proposed
- Date: 2026-10-21
- Plan: `docs/plans/phase-7-agents-extensions.md` (4)

## Context and problem statement

Spec 7.4 asks for extension points (content, UI catalogue, connectors, risk rules, webhooks, admin
pages), a manifest with versioning and compatibility checks, installation without forking core, and a
marketplace-ready design. Extension points exist today only as code conventions; PHP code always runs
with full application privilege.

## Considered options

1. Runtime-installable PHP plugins uploaded through the admin.
2. Operator-installed composer packages with a declarative manifest and compatibility checks; tenant
   admins install only sandboxed or declarative contributions.
3. Out-of-process plugins over HTTP only (webhooks and APIs), no in-process extension.

## Decision

Option 2. An extension is a composer package with `ulams-extension.json` (id, semver version, SPDX
licence, `requires.ulams`, extension dependencies, the extension points it uses with their integer
contract versions, what it provides, the permissions it needs, its provider, an optional signature).
A new `extensions` package validates manifests, checks compatibility at boot and in the upgrade
pre-flight (ADR 0081), loads only compatible extensions listed in `ULAMS_EXTENSIONS`, and exposes them
in `GET /api/meta` and an admin list. Each extension point has an integer contract version kept for
one minor release after a breaking change. Admin pages and UI components are merged at build time.
Release archives may carry a Sigstore (cosign keyless) bundle, verified by the CLI and the index
build. No PHP upload through the UI or API.

## Consequences

- Good: no new remote-code-execution surface; clear compatibility errors before upgrades.
- Good: a future marketplace needs only an index file and the existing signatures.
- Bad: installing a server extension requires a rebuild/redeploy by the operator.
- Bad: declared permissions are a disclosure, enforced only where a choke point exists.
