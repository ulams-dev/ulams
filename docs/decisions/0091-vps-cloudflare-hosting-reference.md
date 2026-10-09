# 0091. Production reference: one VPS behind Cloudflare, flat tenant hosts, a tunnel and R2

- Status: Proposed
- Date: 2026-10-09
- Files: `deploy/vps-cloudflare/`; guide: documentation site, Operators, "Install on a VPS with Cloudflare"
- Related: ADR 0007 (tenancy), 0014 (content origin, amended 2026-10-09), 0021 (HA), 0041 (S3 identities), 0044 (CSP), 0066 (APP_KEY); issue #24 (domain layout)

## Context and problem statement

The product owner bought `ulams.app` (on Cloudflare) and wants a production install that costs about EUR 10 to 25
a month: one VPS plus Cloudflare, with the layout of issue #24 (`ulams.app` landing, `docs.ulams.app`,
`*.ulams.app` tenant fronts, `*.admin.ulams.app`, `*.api.ulams.app`, `*.content.ulams.app`). Nothing is deployed yet;
this ADR fixes what the files and the guide prepare. Three questions need an answer: how the hosts get certificates,
how Cloudflare reaches the server, and where the object storage lives.

Facts from the Cloudflare documentation (checked 2026-10-09):

- Universal SSL (free) covers the apex and **first-level** subdomains only. `*.admin.ulams.app` and
  `acme.admin.ulams.app` are not covered; they need Advanced Certificate Manager (a paid add-on, with Total TLS to
  issue them automatically) or an uploaded custom certificate. A Cloudflare Tunnel does not change this: the browser
  talks TLS to Cloudflare's edge, and the edge needs a certificate for the host.
- A tunnel's ingress accepts a leading wildcard (`*.example.com`), or one catch-all rule; proxied DNS records can be
  created for any host that points at `<tunnel id>.cfargotunnel.com`.
- R2 implements the S3 API (`CreateBucket` works) but has no bucket policies. Public read comes from a custom domain
  (or the rate-limited `r2.dev` URL) per bucket, and R2 custom domains get their own certificates.
- Request bodies are limited to 100 MB on the Free and Pro plans, 200 MB on Business. The proxy gives up on a slow
  origin response after 125 s (524).
- Cloudflare does not use `Vary` for cache keys by default; "Vary in Cache Rules" (all plans) makes it opt-in per rule.

## Considered options

Host layout and certificates:

1. **Flat hosts**: `acme.ulams.app`, `acme-admin.ulams.app`, `acme-api.ulams.app`, `acme-content.ulams.app`. All are
   first-level, so Universal SSL (free) covers them. Slugs cannot contain `-` (`TenantNaming::SLUG_PATTERN`), so a
   `-admin` suffix never collides with another tenant.
2. **Nested hosts** as in issue #24, with Advanced Certificate Manager and Total TLS (about USD 10 a month, check the price).
3. Nested hosts without Cloudflare's proxy (DNS only, Caddy and Let's Encrypt with DNS-01). Loses the CDN, the DDoS
   protection and the tunnel.

Reaching the server:

A. **Cloudflare Tunnel** (cloudflared container, outbound only, nothing listens publicly).
B. Origin CA certificate and Authenticated Origin Pulls on an open port 443, plus a firewall that allows Cloudflare's ranges.

Object storage: one shared bucket with a prefix per tenant, or **one bucket per tenant** (as the tenancy package already
provisions, ADR 0007).

## Decision

1. **Hosting reference.** One Ubuntu 24.04 or Debian 12 host (4 vCPU, 8 GB is the sizing target) runs Docker Compose
   with the published images, pinned to a `sha-<short>` or release tag, memory limits that add up to 6.65 GB, health
   checks and restart policies. Cloudflare provides DNS, TLS, the CDN cache, R2 and (optionally) Pages for the admin and
   the docs. The files are `deploy/vps-cloudflare/` (compose, Caddyfile, cloudflared config, scripts, systemd units). The
   generic example in `front/docs-site/examples/production` stays for operators without Cloudflare.
2. **Flat tenant hosts by default** (`ULAMS_HOST_STYLE=flat`), the cheapest correct option: no add-on, no custom
   certificates. Nested hosts remain supported by the same files (`ULAMS_HOST_STYLE=nested`, `scripts/cloudflare-setup.sh
   --total-tls`) for an owner who wants the layout of #24 and pays for Total TLS. The Caddyfile takes the host patterns as
   regular expressions from the environment, and `scripts/install.sh` derives them (and the `TENANCY_*_HOST`, front and
   admin patterns) from one setting. The ADR 0014 amendment is unchanged: content stays on the same registrable domain, with
   the same mitigations; only the host spelling differs (`acme-content.ulams.app`).
3. **Cloudflare Tunnel by default.** It needs no open port, no origin certificate, no firewall rules (Docker publishes
   ports around ufw, so a published 443 needs an iptables rule in `DOCKER-USER` too) and it keeps the server's address
   private. Origin CA with Authenticated Origin Pulls is kept as `compose.origin.yml` with `scripts/cloudflare-firewall.sh`
   for operators who cannot run cloudflared. Both modes use the same Caddy, which listens on plain HTTP behind the tunnel
   and does no ACME or on-demand TLS; the API already answers 404 for unknown hosts (`TENANCY_ENFORCE_HOSTS`).
4. **R2: one bucket per tenant.** `ulams` (platform assets, public at `files.ulams.app`), `ulams-<slug>` (public at
   `<slug>-files.ulams.app`, attached by `scripts/create-tenant.sh`) and `ulams-backups` (private). One S3 key for the
   application, as today (the `NullProvisioner` case of ADR 0041); bucket-scoped R2 tokens per tenant are the follow-up
   once the identity contract exists. Two switches make this work without changing defaults:
   `TENANCY_S3_PUBLIC_POLICY=false` (skip `PutBucketPolicy`, which R2 lacks) and `TENANCY_BUCKET_PUBLIC_URL`
   (`https://{slug}-files.ulams.app`, a URL per bucket instead of `<store>/<bucket>`). Uploaded sources stay on the
   private local disk of the Course Builder (`api_storage` volume, in the backups) because no private S3 disk exists; a
   public tenant bucket must never hold them. MinIO is only used by the local trial (`install.sh --local`).
5. **Cache rules** (`scripts/cloudflare-setup.sh cache`): hashed front assets (`/_astro/`) cached for a year; anonymous
   `GET /api/*` on API hosts eligible for cache but only where the origin sends `s-maxage` (`bypass_by_default`), never
   with an `Authorization` header or `_token`, with Vary passed through so `Host`, `Accept-Language` and `X-Locale` stay
   in the key.
6. **E-mail** is plain SMTP from any provider (variables only); Cloudflare Email Routing is inbound only and cannot send.
7. **Later options, not built:** the Astro front on Workers (it uses the Node adapter now); the admin and the docs on
   Pages (the guide documents both).

## Consequences

- Good: about EUR 10 to 25 a month in total, nothing publicly listening, certificates and DDoS handled by Cloudflare,
  every host-dependent value comes from one setting.
- Good: the same Caddyfile serves both layouts and both origin modes; `scripts/validate.sh` checks all four combinations.
- Bad: the tenant host spelling differs from issue #24 (`acme-admin.ulams.app` instead of `acme.admin.ulams.app`). Moving
  to the nested layout later changes every tenant's URLs (re-run `ulams:tenant:sync-env`, and the tenants' env files and
  frontends follow the patterns), so decide before the first real tenant.
- Bad: uploads larger than 100 MB (Free/Pro) fail at Cloudflare's edge, although Caddy allows 1100 MB for SCORM and course
  packages; the options are a larger plan, chunked uploads or a DNS-only upload host (not possible through a tunnel).
  Requests slower than 125 s answer 524; long-running streams (the course builder's server-sent events) must send data at least that often.
- Bad: the learner front calls the tenant API by its public host name, so each server-side call goes through Cloudflare
  and back. An internal route is a possible optimisation.
- Bad: bucket data in R2 is durable but not backed up by `backup.sh` (databases, volumes and secrets are); object
  versioning does not exist, so a deleted file is gone unless the operator mirrors the buckets.
- Owner decisions with defaults used (issues labelled `owner-action`): flat or nested hosts (flat), tunnel or Origin CA
  (tunnel), VPS provider (Hetzner CX/CPX class, any works), SMTP provider (any, none chosen).
