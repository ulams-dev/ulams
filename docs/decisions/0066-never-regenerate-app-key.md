# 0066. init.sh never regenerates an existing APP_KEY

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

`init.sh` ran `key:generate --force` together with `passport:keys` whenever `storage/oauth-private.key`
was missing, for example on a fresh container or volume. The new `APP_KEY` replaced the configured one
in `.env`, while the tenant secrets in the platform database (database passwords, Passport keys, tenant
`APP_KEY`s, AI overrides) are encrypted with the old one (ADR 0007, ADR 0021). They became unreadable.

## Decision

`api/init-keys.sh` (used by `init.sh` and `init_multidomains.sh`) generates an `APP_KEY` only when none is
configured (the `APP_KEY` variable or the env file value is empty). Missing Passport keys are recreated on
their own, together with the personal access client, without touching `APP_KEY`. In multi-domain mode a
domain is judged by its own env file.

## Consequences

- Good: losing the Passport key files can no longer make tenant secrets unreadable.
- Good: covered by a unit test with a stand-in `php`.
- Bad: an operator who really wants a new `APP_KEY` must clear it deliberately (and re-encrypt the
  tenants); a fresh Passport key pair still invalidates issued tokens, which is why the key pair stays mandatory
  in the self-hosting example.
