# 0195. Academy entry points: branded front hosts that target a department, a course or a role inside one tenant

- Status: Proposed
- Date: 2026-10-22
- Plan: `docs/plans/academy-entry-points.md`; amends ADR 0141 (department academies as host aliases); spec
  `docs/ROADMAP-PROMPT.md` 6.1.1 and 6.4 ("Per-tenant branding, catalogue, sales pages")

## Context and problem statement

The owner decided on 2026-10-22 that everything the public site labels "Coming" must be built. The policy lifecycle
story promises "one policy, distinct branded academies" with "its own design and domain" per department, and the
white-label story promises per-course and per-role addresses. ADR 0141 (Proposed) covers department hosts only, as
`tenant_host_aliases` resolved like `front_host`.

Facts that shape the choice:

- A tenant has one database, one API host (`<slug>.api.<domain>`, an env file per host through
  `gecche/laravel-multidomain`, `MultidomainRegistry`) and one `front_host`. The learner front (`front/web`) maps the
  request host to a tenant with `ULAMS_TENANT_HOSTS` patterns (`tenantForHost`).
- Session cookies are `__Host-` cookies without `Domain` (ADR 0014 amendment, `front/web/src/lib/cookies.ts`), so a
  session is bound to one host. That is a security property: a sibling host cannot read or toss it.
- Redirect logins (social, OIDC, SAML) return only to the tenant's trusted origins (`ReturnUrlPolicy`,
  `EnforceTrustedOrigin`), and the BFF checks the exact `Origin`.
- Access is decided by enrolment and entitlements (ADR 0130), never by the host.

## Considered options

1. **Entry points**: one platform table of front hosts, each with a kind (`academy`, `department`, `course`, `role`),
   a target inside the tenant, a theme override and SEO settings. The API host stays one per tenant; only the front
   and the trust lists learn the extra hosts. Department aliases of ADR 0141 become entry points of kind `department`.
2. One tenant per department, course or role, linked to a parent tenant.
3. Path prefixes on the tenant host (`/d/sales`, `/c/kubernetes`) with a theme switch, no extra hosts.

## Decision

Option 1. An entry point resolves to its tenant, applies its theme override and narrows what the front shows first
(a department's catalogue, one course's landing, a role's variant preselected for CV-5 routing). It never grants
access, never joins a learner to a group and never changes what the API returns to a token. Each entry host keeps
its own `__Host-` session; moving between hosts of one tenant uses a one-time hand-off code (60 s, single use, bound to
the target host and an S256 verifier), the same mechanism as the social login exchange. Entry hosts are added to the
tenant's trusted origins and return-URL list automatically and removed with the entry point. Platform-provided
hosts follow the deployment's flat host pattern; custom domains need a DNS TXT ownership check before they serve.
Canonical URLs point to the tenant's main host unless the entry point is a course entry point with
`canonical: self`; department and role hosts are `noindex` by default.

Option 3 stays available as a fallback without DNS: every entry point also answers at
`<front_host>/go/<key>`, which sets the same context for the session.

## Consequences

- Good: one policy, one audit chain, one dashboard and one database per organisation; tenant resolution keeps one
  code path with one extra cached lookup; the API and its tenant isolation tests do not change shape.
- Good: per-host sessions keep the cookie hardening; the hand-off is a reviewed, already-tested pattern.
- Good: role entry points feed learner routing (CV-5) as a hint the learner confirms, never a silent assignment.
- Bad: department, course and role data are not physically separated; audiences that need that are separate tenants.
- Bad: TLS for many hosts is an operator task (on-demand TLS in Caddy with an `ask` endpoint, or Cloudflare custom
  hostnames); the plan documents both, and demos use `.example.test` hosts.
- Bad: SEO needs care to avoid duplicate content; the canonical and `noindex` defaults above handle the common case.

## Alternatives considered

- Option 2 splits one policy across databases, needs cross-tenant reporting and multiplies provisioning, backups and
  upgrades for what is a branding concern.
- Option 3 alone does not give the department or course its own address, which the landing promises.
- Sharing one session across sibling hosts with a `Domain` cookie was rejected: it undoes the `__Host-` protection
  against the same-site content origin (ADR 0014).
