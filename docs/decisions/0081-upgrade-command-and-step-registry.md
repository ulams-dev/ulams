# 0081. `ulams:upgrade`: an idempotent per-tenant upgrade command with a step registry

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

Start-up runs `migrate --force` and `ulams:tenant:sync-env --migrate`. Releases now need more work that
must happen once for the platform and for every tenant: re-seeding permissions, creating LTI key sets,
moving cmi5 packages to the bucket, purging stored webcam frames, recreating SQL views, exporting the H5P
service config. Each used to be a note in a changelog for the operator to run by hand, tenant by tenant.

## Decision

- One command, `ulams:upgrade`, runs ordered steps for the platform first (it rebuilds the tenant env
  files the tenant steps need) and then for each active tenant. Tenant steps run in a child
  `artisan --domain=<host>`, like provisioning; platform steps run in the process.
- Steps live in a registry (`Ulams\Tenancy\Upgrade\UpgradeSteps::register(name, Closure, since:, once:,
  scope:, requiresCommand:)`), so packages add their own from a service provider. A step is idempotent by
  contract.
- A one-off step is recorded in the platform table `tenant_upgrade_steps` (`target` = `platform` or the
  tenant slug) and skipped afterwards; `--force-step=<name>` runs it again. Steps registered with
  `once: false` (migrate, permissions, LTI keys, env sync) run on every upgrade and are not recorded.
- A step whose artisan command is not registered in the running release is skipped and not recorded, so a
  step can ship before its command and run on the first upgrade that has it.
- A failing step stops that target and the command goes on with the other targets; the exit code is
  non-zero when any target failed. `--dry-run` prints the plan and changes nothing.
- Deleting a tenant deletes its step records, so a new tenant with the same slug starts clean.
- Start-up does not call `ulams:upgrade` yet: it stays `migrate` + `sync-env` so a container restart stays
  fast and predictable. Operators run `ulams:upgrade` after a release (documented in Upgrades).

## Consequences

- Good: a release's one-off data work is code, reviewed and tested, not an operator checklist.
- Good: it is safe to re-run after a partial failure.
- Bad: another table in the platform database and a static registry (tests flush it).
- Bad: a one-off step that turns out wrong must be fixed by a new step name or `--force-step`.

## Alternatives considered

- Laravel migrations for data work: they run once per database but cannot run external commands per
  tenant process, and they cannot be re-run on demand.
- Run everything at container start: slows every restart and hides failures in a log.
