# 0141. Department academies as branded host aliases inside one tenant

- Status: Proposed
- Date: 2026-10-21
- Plan: `docs/plans/phase-6-remaining.md` (4.3); spec `docs/ROADMAP-PROMPT.md` 6.1.1

## Context and problem statement

The policy lifecycle asks for distinct branded department domains. Each tenant has its own database and one
`front_host`. Departments of one organisation share one policy, its versions, its owner dashboard and its
certificates.

## Considered options

1. One tenant; `tenant_host_aliases` (platform database: host, label, theme, default group) resolved like
   `front_host`.
2. One tenant per department, linked to a parent tenant.
3. One host; departments only as themes chosen after login.

## Decision

Option 1. An alias resolves to its tenant, applies its own theme and shows its department's catalogue first.
Access is still decided by enrolment, never by the host. Aliases are unique across the platform and validated like
`front_host`; DNS and TLS stay with the operator, and demos and tests use `.example.test` hosts.

## Consequences

- Good: one policy, one audit chain, one dashboard; no cross-database joins.
- Good: tenant resolution keeps one code path with one extra lookup.
- Bad: department data is not physically separated; departments that need that are separate tenants with
  separate policies.

## Alternatives considered

- Option 2 splits one policy over several databases and needs cross-tenant reporting.
- Option 3 does not give the department its own address, which the spec asks for.

## Amendment (Proposed, 2026-10-23): separate academies created from a template

Status of the amendment: Proposed. The owner decided that "separate branded academies per department, created from one
policy" must exist, besides the branded entry points of ADR 0195 (hosts inside one tenant). This amendment adds the second shape:
a department academy as a **tenant of its own**. ADR 0141's decision (host aliases) is superseded by ADR 0195 for hosts and stays
as the record of why departments that need no separate data should not get a tenant.

### Design

- **Template tenant.** A *tenant template* (platform database, `tenant_templates`) is a named starting point: theme preset,
  accent, demo flag, demo users and allow-listed inheritable settings (the keys of `ulams_tenancy.inherited_env`, encrypted at
  rest). It applies only when a tenant is created; options win over it; it never changes an existing tenant, and editing it
  does not touch tenants made from it. Managed by `ulams:tenant:template` and `/api/platform/templates`. No content is copied
  from a template: the department's course comes from the policy (below).
- **Theme preset per department.** The owner picks a preset (the product's `ThemePresets`) and an accent for each department;
  they are passed as `--theme` and `--accent` and win over the template.
- **Isolation and the object store.** Every academy goes through the same `TenantProvisioner` steps as any tenant: own database
  and role, own bucket and, with `TENANCY_S3_IDENTITY_DRIVER=seaweedfs`, its own object-store key limited to that bucket (ADR 0041);
  own env file, Passport keys and Redis prefix. Nothing is shared with the parent beyond the platform's inheritable settings.
- **How the course gets there.** As "new site" (ADR 0048): the policy tenant's worker runs `ulams:tenant:create` under the platform
  host, exports the department's builder session (`SessionArchive`: brief, versions, sources, fragments, so citations hold) and
  imports it in the academy, inviting the author as admin. The author applies and publishes there. The policy, its versions,
  certificates and the owner dashboard stay in the policy tenant; the academy's learners and certificates are the academy's.
- **Who may ask.** Provisioning a database and a bucket is an operator-sized action: it needs `TENANCY_NEW_SITES=true` and
  `platform_admin`, exactly like a new site for a course. The policy owner without that permission sees that a platform
  administrator creates academies.
- **Audit.** The platform records a *derivation* (`tenant_derivations`: tenant, parent, template, purpose, reference, who asked,
  when; the row stays, status `deleted`, after the tenant is deleted) and the usual operation; the policy tenant writes
  `policy.academy.requested|provisioned|failed|removed` to the Living Course audit chain.
- **Quota and limits.** At most `TENANCY_MAX_DERIVED_PER_PARENT` (default 5) active derived tenants per parent; a derived tenant
  cannot be a parent (no chains); the slug is derived from the parent, the department and four characters of the variant id, within
  the 30 characters of a slug.
- **Idempotent re-run.** One tenant per (parent, purpose, reference); `ulams:tenant:create` skips finished steps, so a retry after
  a failure resumes the same tenant; the transferred session id is stored, so the course is not moved twice. The ledger row is
  written before provisioning and removed again when the options are refused, so a refusal leaves nothing behind.
- **Cleanup.** The owner removes an academy (`DELETE .../academy`): `ulams:tenant:delete --force` removes database, role, bucket
  objects and identity, env file and Redis keys, and the request is cleared. A parent with derived tenants is not deleted unless
  `--with-derived` removes them first (the platform API refuses with 409 and names them).

### Consequences

- Good: a department gets a real, separately branded academy with its own data; the existing provisioning, storage identity and
  quota machinery is reused, and the same request never creates two tenants.
- Bad: the department's people exist in two places; the owner dashboard and the policy changes of ADR 0140 do not reach the academy
  (a later change is a new transfer, not built); each academy costs a database and a bucket.
- Not in scope: the entry-point hosts and their sessions (ADR 0195, EP-1 to EP-4), the style guide tokens (ADR 0196).
