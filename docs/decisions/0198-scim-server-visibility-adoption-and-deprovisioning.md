# 0198. SCIM server: visibility per provider, adoption by trust, tombstones and content-hash ETags

- Status: Proposed
- Date: 2026-10-23
- Plan: `docs/plans/phase-6-identity.md` (4.7, 4.9, 8); builds on ADR 0120 and 0121

## Context and problem statement

ADR 0120 decided to write the SCIM server in-house over `UserService`, and ADR 0121 decided that SCIM clients authenticate with
scoped Passport tokens of a per-provider service user. The plan leaves open what a SCIM client may see and touch, what happens
when a client pushes a person who already has a local account, how "never hard-delete" and re-provisioning fit together, and what
an `ETag` is. A SCIM token is a standing credential that can create, change and deactivate accounts, so these are
security decisions.

## Considered options

1. **Visibility.** (a) The client sees every user of the tenant. (b) The client sees only the users its provider provisioned.
2. **Existing accounts.** (a) `409` always. (b) Adopt by e-mail whenever the address matches. (c) Adopt only under the rule used
   for linking at sign-in (`trust_email`, domain of the organisation, verified address, active, not an administrator or tutor), else `409`.
3. **Delete.** (a) Hard delete. (b) Soft delete and mask. (c) Deactivate and unlink, keeping the row as a tombstone that lets the same
   `userName` be provisioned again.
4. **Version.** (a) A `version` column on the external-id row. (b) A weak ETag computed from the SCIM representation.

## Decision

1. **(b)**: `ScimDirectory` joins every query on `identity_external_ids` of the token's provider. Administrators, other
   providers' users and all unmanaged accounts are invisible (`404`) and cannot be read, changed or deactivated. Administrators
   that were provisioned and promoted later are refused with `403`.
2. **(c)**: the rule is the one of `AccountResolver` (ADR 0121), so SCIM never links more than SSO would. The first sign-in of a
   provisioned person links without `trust_email`, because the provider already vouched for the address.
3. **(c)** by default: `active=false` and `DELETE` deactivate (the `AccountBlocked` listener of `auth` revokes every token), `DELETE`
   also removes the `identity_links` row and sets `deleted_at` on the external-id row; a later `POST` with the same `userName` revives the same account. `IDENTITY_SCIM_DELETE=anonymise` soft-deletes through the existing `MaskUserData` listener and frees the keys. Nothing is hard-deleted.
4. **(b)**: `meta.version` is `W/"` plus 24 hex characters of the SHA-256 of the canonical representation. No column to keep in step; a change that does
   not change what the client sees does not change the version. `If-Match` is checked against the current representation before any write.

Smaller decisions: the service user is inactive, has no password and an `identity.invalid` address, so it cannot sign in by any
method and is hidden from the admin user list; a `PUT` without `active` keeps the current state, so a replace cannot switch an
account on; filter columns come from a fixed map and values are bound parameters; the server makes no outbound request
for any SCIM input (`$ref` is never dereferenced).

## Consequences

- Good: a leaked SCIM token reaches only the accounts its provider created, can never touch administrators, and is revoked by
  deleting the provider or the token.
- Good: re-provisioning after a leaver/rejoiner cycle restores the same account and its learning record.
- Good: no schema field for versions; the ETag works for groups with their members too.
- Bad: an organisation that wants SCIM to take over existing accounts must turn `trust_email` on (documented); otherwise the IdP's first
  import of existing people gets `409` for them.
- Bad: the ETag costs a representation build per conditional request.
