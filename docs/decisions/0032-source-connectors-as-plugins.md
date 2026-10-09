# 0032. Source connectors as plugins; Git through host APIs; one SSRF-safe HTTP client

- Status: Accepted (2026-10-09)
- Date: 2026-10-09

## Context and problem statement

The spec asks for source connectors in this order: file re-upload, Git repository (path + branch),
then Google Drive / Notion, designed so new connectors are plugins (Phase 7.4). Changes arrive by
webhook, poll or a manual check. Connectors fetch untrusted content from addresses chosen by tenants,
so SSRF and secret handling matter. The API image has no `git` binary; the LTI package already has
an SSRF-guarded Guzzle client.

## Considered options

For Git: (a) the hosts' REST APIs (GitHub, GitLab, Gitea/Forgejo) over Guzzle; (b) the `git` CLI
with a hardened configuration; (c) a PHP Git library.

## Decision

- A `SourceConnector` contract (`key`, `label`, `configSchema`, `secretFields`, `validate`,
  `fetch → FetchResult{unchanged, ref, files[], metadata}`, `supportsWebhooks`, `verifyWebhook`)
  and a `SourceConnectorRegistry` filled by service providers; enabled keys in config. Connectors
  return files; conversion and fragmenting stay in shared code.
- Built in Phase 3: `upload`, `git` (GitHub, GitLab, Gitea/Forgejo through their REST APIs: head
  SHA check, recursive tree, changed blobs only, path globs, size and count caps) and `url` (pages on
  one host, CSS selector, HTML → Markdown with `league/html-to-markdown`). Drive and Notion are
  designed (plan section 6.6), not built.
- Triggers: manual, upload, per-tenant scheduled polling (hourly/daily/weekly) and signed webhooks
  (GitHub HMAC, Gitea/Forgejo HMAC, GitLab token, a generic HMAC header), deduplicated by delivery id,
  rate limited and debounced into one unique check job per connection.
- `Ulams\Core\Http\SafeHttp` (extracted from the LTI package) is used for every outgoing request:
  https only, A and AAAA records checked against private, reserved, link-local, CGNAT and mapped
  ranges, resolution pinned, redirects off or re-checked per hop to the same host, size and time
  caps, optional tenant host allow-list.
- Secrets (tokens, webhook secrets) are encrypted at rest, write-only in the API, never logged or
  sent to the model.

## Consequences

- Good: no new binary in the image and no Git client attack surface; every request goes through one
  tested SSRF guard; plugins add connectors without touching core.
- Good: the Git host clients are reusable by course-as-code (Phase 7.1).
- Bad: Git hosts without a supported API (plain Git over HTTPS, Bitbucket) need a later connector;
  a generic `git` CLI connector is a roadmap item.
- Bad: API rate limits (GitHub unauthenticated: 60 requests per hour); mitigated by the head-SHA
  check and changed-blob-only downloads, and a token is recommended.
