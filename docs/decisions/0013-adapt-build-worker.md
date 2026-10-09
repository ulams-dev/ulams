# 0013. Adapt Path B: JSON sources in the API, builds in an isolated GPL-3.0 worker

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

The spec (1.2) asks for two Adapt Learning paths. Path A, importing a course built with
`adapt-contrib-spoor`, is a SCORM upload. Path B stores the Adapt JSON source (`course`, `config`,
`contentObjects`, `articles`, `blocks`, `components`) as structured, schema-validated data and builds
it into a playable course with an isolated worker, behind a feature flag. The Adapt framework and its
plugins are GPL-3.0 (ADR 0003 and LICENSING: GPL code only in separate programs), the build needs
Node and takes 30–90 s of CPU, and the plugins' property schemas belong to the plugins.

## Considered options

1. Build inside the API container (Node in the PHP image): GPL code next to the API, heavy image.
2. **A separate build service `api/adapt-builder` (GPL-3.0), reached over HTTP like `api/h5p`.**
3. A one-off build container per job (Docker in Docker): needs access to the Docker socket.

## Decision

Option 2, off by default (`ADAPT_SOURCE_ENABLED=false`, compose profile `adapt`).

- **API (`api/packages/adapt`, MIT)**: versioned sources (`adapt_sources`, `adapt_source_versions`),
  structural validation without GPL code (six parts, unique `_id`s, parent links down the
  course → contentObjects → articles → blocks → components hierarchy, components from an allow-list
  of core plugins), and a queued build job. Permission `adapt_manage` (admins, tutors).
- **Worker contract**: `POST /build` with `X-Internal-Token`, body
  `{"id": "<source>-v<version>", "source": {…}}`; answers `200 application/zip` (a SCORM 1.2 export
  made with `adapt-contrib-spoor`) or `4xx/5xx {"error": "…"}`. The worker runs the framework build in
  a temporary directory with no network, a CPU/memory limit and a timeout, validates plugin
  properties with the plugins' schemas, and keeps nothing.
- **Import**: the zip goes through the normal SCORM upload (Path A): upload guard, safe extraction,
  `source_format = adapt`, played from the tenant content origin and tracked like any SCORM package.
  Authors put the built package in a SCORM topic; there is no separate topic type.

## Consequences

- The worker is `api/adapt-builder` (GPL-3.0-or-later, Node built-ins only): `adapt_framework`
  v5.56.2 (released 2026-04-13; the plan named 5.19.x) with the plugins of its
  `adapt.json` installed by `adapt-cli` 3.4.0 at image build time, versions recorded in the image.
  Image about 430 MB; the fixture course builds in about 8 s on two CPUs. Compose service
  `adapt-builder` in the profile `adapt` on an internal network shared with the API only, read-only
  root, CPU/memory/PID limits; one build at a time, `ADAPT_MAX_QUEUED` waiting, then 503.
- The real build runs in the nightly conformance workflow (`AdaptBuilderRoundTripTest`, skipped
  without `ADAPT_BUILDER_E2E_URL`); unit tests use a fake worker (API) and a fake framework (worker).
- Builds run from the queue, one at a time per source; a failed build keeps the last error on the
  source.
- Path A works without any of this.
