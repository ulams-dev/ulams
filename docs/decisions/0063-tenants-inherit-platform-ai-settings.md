# 0063. Tenants inherit the platform AI settings, with per-tenant overrides

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

`ulams:tenant:sync-env` rebuilds every `.env.<host>` from the `tenants` table (ADR 0007). The values it
writes are the tenant's own (database, bucket, keys, URLs). Anything else a tenant needs, such as
`ANTHROPIC_API_KEY` and the `AI_*` settings of the LLM layer (ADR 0009), only reached a tenant when it
happened to be in the platform `.env` at the moment the env file was first created. On a fresh container
the platform `.env` is generated from `LARAVEL_*` variables, so a key passed under its plain name never
arrived, and a key added later never propagated. Tenants silently lost AI features
(`AI_DRIVER=anthropic` without a key resolves to `disabled`).

## Considered options

1. Copy the platform values at sync time (inheritance), with an optional per-tenant override stored in
   the `tenants` table.
2. Only copy the platform values, no per-tenant override.
3. Store a key per tenant, required at creation.

## Decision

Option 1. `config/ulams_tenancy.php` lists the inheritable settings (`inherited_env`: `ANTHROPIC_API_KEY`,
with the legacy `ANTROPHIC_API_KEY`, `ANTHROPIC_BASE_URL`, `AI_DRIVER`, `AI_MODEL_*` and their labels),
read from the platform environment. `TenantNaming::envValues()` writes the non-empty ones into every tenant
env file on `create` and `sync-env`; a tenant's own value wins. Overrides live in `tenants.env_overrides`
(encrypted JSON, same protection as the other tenant secrets) and are set with
`ulams:tenant:set-env <slug> --set=KEY=value|--unset=KEY`. Only listed keys are accepted. Values are never
printed or logged: the commands print key names only.

## Consequences

- Good: one platform key serves every tenant by default; a restart or `sync-env` is enough to roll a new key
  out; a tenant can bring its own key or run `AI_DRIVER=disabled`.
- Good: the allow-list stops the override path from being used to change database, bucket or key settings.
- Bad: the platform key is copied in plain text into each tenant env file (as the other secrets already are);
  anything that can read a tenant env file can read it.
- Bad: cost of a shared key is not split per tenant by the key itself; usage is already logged per tenant
  (ADR 0009).
