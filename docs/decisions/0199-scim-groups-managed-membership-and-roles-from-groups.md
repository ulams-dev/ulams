# 0199. SCIM groups: managed groups, provider-visible members and roles from group names

- Status: Proposed
- Date: 2026-10-23
- Plan: `docs/plans/phase-6-identity.md` (4.4, 4.7); builds on ADR 0198

## Context and problem statement

A group grants access (course and path enrolment, compliance rules), so a SCIM client that can create, fill or delete groups can grant access.
The plan says groups created by SCIM are "managed" and that IdP groups may map to roles; it leaves open name collisions, which members a
client may see, and how a role granted by a group is taken back.

## Considered options

1. Adopt an existing group with the same name, or answer `409`.
2. Show and replace every member of a managed group, or only the members the provider provisioned.
3. Roles from groups: never; a mapping that can name any role; a mapping limited to `student` and `tutor` that only undoes what it did.

## Decision

1. `409 uniqueness` for a display name used by any other group, so a client cannot take over or fill a group it did not create.
2. Only members the provider provisioned are shown, added and removed; members an administrator added by hand are invisible to it and untouched.
   Member filters count only those users. A `PUT` without `members` keeps the membership.
3. `identity_providers.scim_group_roles` maps a group display name to `student` or `tutor` (`admin` is rejected at input and ignored at apply; administrators are never changed).
   `GroupRoleMapper` records the roles it added in the user's external-id row and only removes those, on leaving, renaming or deleting the group, or changing the mapping; every grant and revocation is audited.
   Membership changes go through new bulk methods of `UserGroupService` (`addMembers`, `removeMembers`) that fire the same events as `addMember`.

## Consequences

- Good: a leaked token cannot take over groups, and cannot reveal or remove accounts it does not manage; roles follow membership and never survive it.
- Good: bulk membership is one query per call, not one reload of the group per user.
- Bad: an IdP that expects to adopt an existing group by name gets `409` and an administrator must rename one of them.
- Bad: a role given by hand while a mapping also grants it is not removed when the membership ends (it was not ours).
