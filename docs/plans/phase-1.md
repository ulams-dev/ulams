# Phase 1 plan: content formats and integrations

Status: **approved by the product owner (2026-10-09)**.

Phase 1 follows Phase 0 (the Laravel 13 / PHP 8.4 upgrade must be merged first) and precedes
Phase 2 (AI Course Builder, `docs/plans/phase-2.md`). It covers every Phase 1 item in
`docs/ROADMAP-PROMPT.md` and `docs/ROADMAP-TODO.md`: LiaScript (1.1), Adapt Learning (1.2),
LTI 1.3 (1.3), shared requirements (1.4) and the `(new)` H5P items listed under 1.4.

Facts below come from reading the code on 2026-10-08 (paths relative to the repo root).

---

## 1. What exists today

- **Topic types** plug into one registry: a content model extending
  `api/packages/topic-types/src/Models/TopicContent/AbstractTopicContent.php` (static `rules()`,
  `topic(): MorphOne`), registered in a service provider with `Topic::registerContentClass()` and
  `Topic::registerResourceClasses()` (admin, client, export resources). Topics are created by
  `TopicRepository::createFromRequest`. `api/packages/topic-type-project` is the most complete small
  example. Import/export (`courses-import-export/src/Services/ExportImportService.php`) has a
  hard-coded list of file-backed types with per-type strategies.
- **SCORM**: `POST api/admin/scorm/upload`, validated only by `mimes:zip` and the presence of
  `imsmanifest.xml`; `ScormService::unzipScormArchive` extracts with `ZipArchive::extractTo` and copies
  entries by their raw names (**no zip-slip guard, no size or entry limits**). Packages are stored on
  the `scorm.disk` (MinIO bucket in dev) and served from `storage.localhost`; the player
  (`scorm/resources/views/player.blade.php`) runs on the API origin and loads `scorm-again` **from the
  jsDelivr CDN**. Tracking: `POST /api/scorm/track/{uuid}` → `scorm_sco_tracking`.
- **cmi5**: `Cmi5UploadService` extracts straight into the disk path, **also without a zip-slip
  guard**.
- **Uploads**: the files package allows zip and svg (`FILES_MIMES`); PHP allows 2 GB bodies; Caddy caps
  only `/h5p` at 300 MB; there is **no virus-scan hook** anywhere.
- **CSP**: only the H5P service sets `frame-ancestors`. Caddy and Laravel set no CSP.
- **LTI**: nothing exists. `firebase/php-jwt` ^7 is already a dependency.
- **Progress**: `CourseProgressRepository::updateInTopic($topic, $user, ProgressStatus::COMPLETE, …)`
  fires `TopicFinished` and the lesson/course completion checks; this is what new types call.
- **Players**: dispatch in `front/src/components/Courses/Course/CourseProgramContent.tsx` (switch on
  `TopicType` from `front/src/lib/sdk/types/enums.ts`); admin topic forms in
  `admin/src/components/ProgramForm/ThreeColProgram/TopicForm/media/*`.
- **Tenancy**: database per tenant, `TenantProvisioner::STEPS` = database, bucket, env, migrate,
  passport_keys, passport_client, permissions, demo (ADR 0007).
- **Reference frontend**: `front/web` (Astro) and `front/ui` exist as skeletons; ADR 0008 is being
  written. Phase 1 learner players are built in the current `front` app (they are thin iframe wrappers)
  and ported to `front/web` when ADR 0008 lands.

---

## 2. Milestones (each small and demoable)

| # | Milestone | TODO items | Demo |
|---|---|---|---|
| M1.1 | Upload hardening and the isolated content origin | 1.4 upload hardening; 1.4 isolated origin / strict CSP | A zip-slip SCORM fixture is rejected with a clear message; a valid SCORM course plays from `content.<tenant>` with CSP headers; `scorm-again` is served locally |
| M1.2 | LTI 1.3 Platform: launch and AGS | 1.3 Platform (launch, AGS); 1.3 key rotation, nonce/state, per-tenant registrations | An author adds the 1EdTech reference tool (saLTIre) as an LTI topic; a learner launches it; a score posted by the tool completes the topic |
| M1.3 | LTI 1.3 Platform: deep linking | 1.3 Platform deep linking | The author picks content inside the tool; the topic is created from the deep-linking response |
| M1.4 | LTI 1.3 Tool | 1.3 Tool | Moodle (compose profile) launches a ulams course; the learner lands in the course with access; finishing a topic sends a grade back to Moodle |
| M1.5 | LiaScript topic type | 1.1 (all three items) | An author pastes Markdown, sees a live preview, saves version 2, restores version 1; the learner plays it and completion is tracked |
| M1.6 | Adapt Path A: built SCORM import | 1.2 Path A | An Adapt course exported with `adapt-contrib-spoor` is imported and tracked like any SCORM course, labelled "Adapt" |
| M1.7 | Adapt Path B: JSON source and build worker (feature flag) | 1.2 Path B | With `ADAPT_SOURCE_ENABLED=true`, an Adapt JSON source is validated, built by the worker into SCORM and played |
| M1.8 | H5P multitenancy and token refresh | 1.4 (new) H5P items | Two tenants with separate H5P keys, databases and buckets; the player keeps working past a 5-minute token rotation; `_token` redacted in access logs |
| M1.9 | Policies, OpenAPI, fixtures, conformance | 1.4 policies/OpenAPI/fixtures/tests | CI runs the LiaScript, Adapt A+B and LTI round-trip fixtures |

Order rationale: hardening first because every later milestone uploads or serves third-party
content and because the SCORM/cmi5 zip-slip is a live vulnerability. LTI is marked high priority in
the spec, so it comes next. LiaScript follows (small, text-native, and Phase 2 M2.3 needs it). Adapt
Path B is last of the formats because it is the most expensive to run and sits behind a flag.

---

## 3. M1.1 Upload hardening and the isolated content origin

### 3.1 Upload guard

A new package `api/packages/uploads` (`Ulams\Uploads`) used by scorm, cmi5, courses-import-export,
files and later the LiaScript, Adapt and course-builder uploads:

- `UploadPolicy` per kind (`scorm`, `cmi5`, `adapt`, `liascript`, `course-import`, `image`, `document`,
  `source`): allowed extensions, allowed sniffed MIME types (`finfo`, not the client header), max size.
- `ZipInspector`: entry count limit, total uncompressed size limit, compression-ratio limit (zip
  bombs), rejects absolute paths, `..` segments, backslashes, drive letters, symlink entries, duplicate
  normalised names and nested archives beyond depth 1 where not expected.
- `SafeExtractor`: streams entries one by one to the target disk under a normalised path; never calls
  `ZipArchive::extractTo`. Replaces the extraction in `ScormService`, `ScormManager`,
  `app/Library/ScormHelper.php` and `Cmi5UploadService`.
- `VirusScanner` contract with `NullScanner` (default) and `ClamdScanner` (clamd over TCP; ClamAV runs
  as a separate, optional compose profile `av`, GPL-2.0 as a separate process). Uploads are scanned
  before extraction; infected files are rejected and logged.
- SVG and HTML uploads served from the public files bucket get `Content-Disposition: attachment` and
  `Content-Type` fixed (the follow-up noted in `docs/plans/phase-0.md`).
- Caddy: request body limits per route (`/api/admin/scorm/upload` etc.) instead of PHP's 2 GB.

### 3.2 Content origin and CSP

Third-party packages (SCORM, cmi5, Adapt builds, LiaScript) run arbitrary JavaScript. Today they run
on `storage.localhost`, shared by all tenants.

- **Per-tenant content origin**: `<slug>.content.<base>` (dev: `<slug>.content.localhost`), a Caddy site
  that proxies read-only to the tenant bucket for package paths only (`scorm/`, `cmi5/`, `adapt/`,
  `liascript/`), with no cookies and no API routes.
- **Player on the content origin**: the SCORM/cmi5 player page moves to the content origin so the SCORM
  API and the content are same-origin (no cross-origin API discovery); it talks to the API with a
  short-lived, topic-scoped tracking token (not the learner's Passport token) issued by the API when the
  learner opens the topic.
- **CSP on the content origin**: `default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval';
  style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; media-src 'self' blob:; connect-src
  'self' <api host>; frame-ancestors <tenant front hosts> <tenant admin host>; form-action 'none'`.
  `unsafe-inline/eval` are needed by most authoring tools' output and are acceptable because the origin
  holds nothing else. The front embeds it with `sandbox="allow-scripts allow-same-origin allow-forms
  allow-popups"` (same-origin relative to the content origin only).
- **CSP for the API, front and admin** (report-only first, enforced after a week of clean reports):
  `frame-src` lists the content origin, the H5P host and registered LTI tool origins.
- `scorm-again` vendored (MIT) and served locally instead of jsDelivr (principle 8, air-gapped).

### 3.3 Tests

Fixtures: zip-slip entry, absolute path, symlink, 10 000 entries, 1 KB → 2 GB bomb, wrong MIME with
right extension, EICAR test string (with the clamd profile). Feature tests per upload endpoint
(SCORM, cmi5, course import, files). A header test asserts CSP and `X-Content-Type-Options` on the
content origin. Existing SCORM/cmi5 tests stay green (behaviour unchanged apart from the origin).

---

## 4. M1.2–M1.4 LTI 1.3

### 4.1 Package and libraries

One package `api/packages/lti` (`Ulams\Lti`) with `Platform` and `Tool` namespaces sharing key
management, registrations storage, nonce store and claim builders.

| Side | Library | Why |
|---|---|---|
| Platform (we launch tools) | First-party on `firebase/php-jwt` (already present) | No maintained, permissively licensed PHP platform library exists; the platform side is a handful of endpoints we must own anyway (OIDC authorize, JWKS, token, AGS, deep-linking return) |
| Tool (others launch us) | `packbackbooks/lti-1p3-tool` 6.4.x (Apache-2.0, released 2026-09) | Mature tool-side implementation (launch validation, nonce/state, deep linking, AGS and NRPS clients); we implement its database and cache interfaces on our tables |

Rejected: `celtic/lti` (LGPL-3.0), `oat-sa/lib-lti1p3-core` (GPL-2.0), `ltijs` (Node; would add a
service). A separate ADR (0012, "LTI 1.3 package and libraries") will be proposed with M1.2.

### 4.2 Data model (tenant database)

`lti_keys` (kid, encrypted private key, public JWK, status `next`/`active`/`retired`, rotated_at) ·
`lti_tools` (Platform side: name, client_id we issued, deployment_id, OIDC login URL, launch URL,
deep-linking URL, JWKS URL or static public key, allowed scopes, custom params, enabled) ·
`lti_platforms` (Tool side: issuer, client_id, deployment ids, auth login URL, token URL, JWKS URL,
audience) · `lti_resource_links` (topic ↔ tool, target URI, custom params, line item) ·
`lti_line_items`, `lti_scores` (AGS) · `lti_user_links` (issuer + sub → user) · `lti_nonces`
(nonce/state with expiry, unique) · `lti_launches` (audit: who, which tool/platform, message type,
result).

### 4.3 Keys, nonce and state

- Per-tenant RSA-2048 key set; JWKS at `/.well-known/jwks.json` on the tenant API host publishes `next`,
  `active` and recently `retired` keys. `ulams:lti:rotate-keys` promotes `next` → `active`, generates a
  new `next` and retires the old key after a grace period; the scheduler runs it monthly. A new
  provisioning step `lti_keys` is added to `TenantProvisioner` (resumable like the others).
- Nonces and states are single-use with a 10-minute TTL; replays are rejected and logged. `iss`, `aud`,
  `azp`, `exp`, `iat`, `nonce` and deployment ID are checked on every inbound JWT; tool/platform JWKS are
  fetched with a cache and an SSRF-safe HTTP client (HTTPS only, no private IP ranges unless the tenant
  enables a dev flag).

### 4.4 Platform (M1.2, M1.3)

- **Topic type `LtiLink`** (`topic_lti_links` table) referencing a resource link.
- **Launch**: the learner opens the topic → the front asks `POST /api/lti/launches/{topic}` → the API
  returns an auto-submitting form to the tool's OIDC login URL with a signed, 2-minute `login_hint`
  (user, topic) and `lti_message_hint` → the tool calls `/api/lti/platform/authorize` → we verify the
  hint, the registered redirect URI and the client, and post a signed `id_token`
  (`LtiResourceLinkRequest`: roles mapped from LMS roles, context = course, resource link = topic, AGS
  and NRPS claims, custom params). No cookie dependency, so it works inside iframes.
- **AGS**: `/api/lti/platform/token` (client-credentials with a JWT assertion verified against the
  tool's JWKS), line items and scores endpoints scoped per tool and course. A score with
  `activityProgress=Completed` or `gradingProgress=FullyGraded` marks the topic complete through
  `CourseProgressRepository::updateInTopic`; the score is stored and shown in reports. Past scores are
  never overwritten silently (history kept).
- **Deep linking (M1.3)**: in the admin topic form, "Pick content from tool" launches
  `LtiDeepLinkingRequest`; the tool posts `LtiDeepLinkingResponse` to `/api/lti/platform/deep-links`;
  we verify it and create one `LtiLink` topic per item through `TopicRepository`.
- Player: iframe on the front (`allow` list from the tool registration), with "open in new window" for
  tools that refuse framing.

### 4.5 Tool (M1.4)

- Endpoints: `/api/lti/tool/login` (OIDC initiation), `/api/lti/tool/launch`, `/api/lti/tool/deep-link`,
  JWKS (shared).
- Launch: validate with the library; map `(iss, sub)` to a user (create on first launch with name and
  email if the platform sends them, else a pseudonymous account); map roles (Learner → student,
  Instructor → tutor); grant course access through the course-access service; issue a one-time code that
  the front exchanges for a Passport token, then open the course.
- Cookies in iframes: state is stored server-side keyed by `state`, and the LTI Client-Side OIDC
  (platform storage via `postMessage`) is used when the platform supports it.
- Grade passback: a listener on `TopicFinished` / `CourseFinished` queues an AGS score to the platform
  for launches that carried an AGS claim; retries with backoff; failures visible in the admin.
- Deep linking: a course picker page (screen in Stitch, see section 9) returns `LtiResourceLink` items.

### 4.6 Admin and front

Admin: "Integrations → LTI" with two tabs: **Tools** (platform side: list, add/edit with JWKS URL or
key, our endpoints to copy, test launch) and **Platforms** (tool side: list, add/edit, the URLs and
JWKS to paste into Moodle/Canvas). Topic form: new type "External tool (LTI)" with tool select, URL,
custom params and "Pick content from tool". Front: `LtiPlayer` iframe component.

### 4.7 Tests

- Unit: claim builders, key rotation and JWKS content, nonce/state single use and expiry, role mapping,
  JWT validation failures (wrong `aud`, expired, unknown `kid`, replayed nonce, wrong deployment).
- Feature: full Platform launch against an in-test fake tool (signs and verifies with test keys);
  AGS token + score → topic complete; deep-linking response → topics created.
- Round-trip fixture (required by the spec): Platform side against the 1EdTech **saLTIre** tool
  emulator in a manual/nightly job; Tool side against **Moodle** in an optional compose profile
  (`lti-e2e`) with a scripted Playwright launch and grade check.
- Tenant isolation for every new endpoint: tenant B cannot use tenant A's registrations, keys, line
  items or launches; JWKS of A never contains B's keys.

---

## 5. M1.5 LiaScript

### 5.1 Storage

- Topic type `LiaScript` (`topic_liascripts`): current version pointer, title, settings.
- `liascript_versions`: version number, Markdown source, assets manifest (path → stored file, hash),
  author, created at, change note. Assets in the tenant bucket under `liascript/<topic>/<version>/`.
- CRUD API (spec 1.1): create from Markdown (`POST /api/admin/liascript` with `markdown`), upload `.md`
  or `.zip` (Markdown + assets; through the upload guard), update (creates a version), delete, fetch
  source (`GET …/source?version=`), list versions, restore. The Markdown stays the source of truth (text
  form for AI in Phase 2/3).

### 5.2 Rendering decision

| Option | Progress tracking | Custom code | Runtime dependencies |
|---|---|---|---|
| (a) Self-hosted LiaScript web build loading our Markdown | LiaScript's default build keeps state in the browser; reporting completion needs our own connector or a "mark complete" button | Connector or button, plus a Markdown endpoint | Vendored static build |
| (b) Export to SCORM with LiaScript-Exporter, played by our SCORM runtime | Full CMI tracking through the existing SCORM path | Packaging step only | Exporter CLI (Node, pulls Puppeteer and Electron-related packages) in a build container |
| **(b′) Recommended: our own SCORM packaging of the vendored LiaScript SCORM build** | Same as (b) | A PHP packager that writes `imsmanifest.xml` and copies the vendored SCORM-flavoured LiaScript build (BSD-3-Clause) plus the Markdown and assets | Vendored static build, no Node at runtime |

**Recommendation: (b′)**, decided by a one-day spike at the start of M1.5: confirm that the
SCORM-connector build shipped with LiaScript-Exporter (BSD-3-Clause, ISC on npm) can be vendored and
packaged without the exporter's Node toolchain. If the spike fails, fall back to (b) with the exporter
in a short-lived build job container. Either way: no runtime dependency on `liascript.github.io`; the
build is served from the tenant content origin (M1.1); external LiaScript imports (`import:` of remote
macros) are blocked by CSP and flagged by the validator.

### 5.3 Admin and front

Admin topic form "LiaScript": Markdown editor (the existing admin markdown editor) with a live preview
(the packaged player in an iframe, rebuilt on save), version list with restore and diff. Front:
plays as a SCORM SCO through the existing player; no new learner component.

### 5.4 Tests

Minimal LiaScript fixture course (headings, quiz, code block, an image asset); packaging produces a
valid SCORM 2004 manifest; the package passes the upload guard; play → completion recorded via CMI;
versions create, restore and keep progress rules (a new version keeps completion, as for other topic
edits today); export/import strategy for the new type.

---

## 6. M1.6–M1.7 Adapt Learning

### 6.1 Path A: built SCORM (M1.6)

Adapt courses exported with `adapt-contrib-spoor` are SCORM packages. Path A needs no new runtime:
upload through the SCORM import (now hardened), detect Adapt (manifest plus `adapt/js/adapt.min.js` or
`course/config.json`), store `source_format = adapt` on the SCO for labelling and reporting, and add a
fixture course. Adapt's runtime is GPL-3.0; it runs only inside the content-origin iframe as a separate
program (LICENSING rule 1), the same position as any third-party SCORM package.

### 6.2 Path B: JSON source and build worker (M1.7, behind `ADAPT_SOURCE_ENABLED`)

- Topic type `AdaptSource` storing the Adapt JSON (`course`, `contentObjects`, `articles`, `blocks`,
  `components`, `config`) in `topic_adapt_sources` plus a version table like LiaScript.
- **API-side validation** with our own structural JSON Schema (IDs, parent links, component types from
  an allow-list of core plugins) using `opis/json-schema` (also needed in Phase 2). Plugin-specific
  property schemas belong to Adapt (GPL) and are validated only inside the worker.
- **Build worker `api/adapt-builder`**: a small Node HTTP service (`POST /build` → SCORM zip),
  **GPL-3.0** because it bundles `adapt_framework` 5.19.x and core plugins, reached over HTTP like
  `api/h5p` (ADR 0003 pattern, LICENSING rules 1–3). It runs the framework build in a temp directory
  with no network, a CPU/memory limit and a timeout, and returns the zip. The API imports the zip
  through Path A.
- **Infra cost (to flag)**: one more image (~500–700 MB with Node and the framework), a build takes
  roughly 30–90 s and 1 CPU; builds run from a queue with concurrency 1 per worker. Off by default; the
  compose service is in an optional profile `adapt`.
- A separate ADR (0013, "Adapt build worker as an isolated GPL service") will be proposed with M1.7.

### 6.3 Tests

Fixtures: a minimal Adapt course both as a spoor SCORM export (Path A) and as JSON source (Path B).
Path A: import, play, CMI completion. Path B: schema rejects broken parent links and unknown
components; build job against a fake builder in unit tests; a real build in the `adapt` compose
profile in a nightly job.

---

## 7. M1.8 H5P multitenancy and token refresh (new items under 1.4)

Continue the env-file resolver already live for the demo tenants: per-tenant `H5P_INTERNAL_TOKEN`
written at provisioning (new step in `TenantProvisioner`), library administration restricted to the
platform (libraries are shared), production mounts limited to env files and key directories, idle
tenant eviction in the resolver cache. Player: refresh the player model when the 5-minute Passport
token rotates (front and admin), and redact `_token` in Caddy, H5P service and API access logs.
Tests: two tenants with separate keys, databases and buckets (the H5P service rejects cross-tenant
content); token rotation test in the front; log redaction test.

---

## 8. M1.9 Policies, OpenAPI, fixtures, conformance

Every new endpoint has a policy using the existing permissions package (`lti_manage`,
`liascript_manage`, `adapt_manage` seeded for admin; tutors get create/update on their courses), OpenAPI
annotations like other packages (regenerated into `@ulams/sdk`), and is covered by the tenant isolation
helper. Fixtures live in each package's `tests/fixtures`. CI: the LiaScript and Adapt Path A fixtures
run in PHPUnit; Adapt Path B and the LTI round-trips run in a nightly workflow with the compose profiles.

---

## 9. Designs (Stitch)

Phase 1 adds admin and integration screens, requested in the Stitch project "ulams Course Builder"
(same platform design language) and indexed in `front/docs/design/stitch/course-builder/README.md`
under "Phase 1". Only the LiaScript editor was exported (screen `48ede9329c8947f59ba2447d4d9633ef`);
the other three timed out in the Stitch API and need to be exported or regenerated:

- Admin "Integrations → LTI": tool and platform registrations with the add-tool drawer.
- Admin LiaScript topic editor: Markdown, live preview, versions.
- LTI Tool deep-linking course picker (our page shown inside Moodle or Canvas).
- Admin content package import report (SCORM / Adapt / LiaScript upload with upload-guard results).

The admin is an Ant Design app; the designs set the layout and states, and implementation uses the
admin's components with the `--ulams-*` variables (ADR 0004).

---

## 10. New dependencies

| Package | Where | Licence | Why | Maintenance and size | Self-hosting impact |
|---|---|---|---|---|---|
| `packbackbooks/lti-1p3-tool` 6.4.x | api (lti, Tool side) | Apache-2.0 | Mature LTI 1.3 tool library; avoids re-implementing launch validation, AGS/NRPS clients | Active (release 2026-09); pure PHP; depends on `firebase/php-jwt` (present) and Guzzle (present) | None |
| `opis/json-schema` 2.6.x | api (Adapt Path B validation; also Phase 2) | Apache-2.0 | Draft 2020-12 validation | Active; pure PHP | None |
| LiaScript SCORM build (vendored static files) | api content packager | BSD-3-Clause | Player for LiaScript courses without `liascript.github.io` | Active upstream (commits 2026-10); ~3–5 MB static | Ships in the API image; no service |
| `scorm-again` 3.x (vendored) | SCORM/cmi5 player | MIT | Replace the jsDelivr CDN load (air-gapped) | Active | None |
| `adapt_framework` 5.19.x + core plugins | `api/adapt-builder` only | GPL-3.0 | Path B builds | Active | Optional image (profile `adapt`); GPL source offer like `api/h5p` |
| ClamAV (clamd) | optional compose profile `av` | GPL-2.0 (separate process) | Virus-scan hook implementation | Active | Optional container; `NullScanner` by default |
| Moodle (test only) | compose profile `lti-e2e` | GPL-3.0 (separate program, test infra) | LTI Tool round-trip | Active | Not shipped |

No new frontend libraries: players are iframes; the admin uses its existing editor and Ant Design.

---

## 11. Migrations

Tenant database: `topic_lti_links`, `lti_keys`, `lti_tools`, `lti_platforms`, `lti_resource_links`,
`lti_line_items`, `lti_scores`, `lti_user_links`, `lti_nonces`, `lti_launches`, `topic_liascripts`,
`liascript_versions`, `topic_adapt_sources`, `adapt_source_versions`, a `source_format` column on the
SCORM SCO table (nullable, no backfill needed), permission seeders. Tenancy package: new provisioning
steps `lti_keys` and `h5p_token`, with `ulams:tenant:sync-env` covering existing tenants.

---

## 12. Risks

| Risk | Mitigation |
|---|---|
| Moving the SCORM player to the content origin breaks existing packages | Feature flag per tenant for one release; both paths tested on the existing SCORM fixtures |
| LTI in iframes and third-party cookies | No cookie dependency on the platform side; server-side state plus LTI client-side OIDC on the tool side |
| LiaScript SCORM build cannot be packaged without the exporter | Spike first; fallback to the exporter in a build container |
| Adapt build worker cost and GPL distribution | Off by default, optional profile, isolated service with source offer |
| CSP breaks embedded tools | Report-only first; per-tool `frame-src` from registrations |
| Laravel 13 upgrade slips | Phase 1 starts after it is merged; the upload guard can be developed against Laravel 12 if needed |

---

## 13. Commit order

M1.1: `feat(uploads): add upload policies, zip inspector, safe extractor and virus-scan hook` ·
`fix(scorm): extract packages through the safe extractor` · `fix(cmi5): extract packages through the
safe extractor` · `fix(courses-import-export): validate imports with the upload guard` ·
`feat(scorm): vendor scorm-again and serve players from the tenant content origin` ·
`feat(infra): per-tenant content origin and CSP headers in Caddy` · `chore: report-only CSP for api,
front and admin`.

M1.2: `docs: ADR 0012 LTI 1.3 package and libraries` · `feat(lti): key sets, rotation and JWKS` ·
`feat(tenancy): provision LTI keys` · `feat(lti): tool registrations and the LtiLink topic type` ·
`feat(lti): platform OIDC launch` · `feat(lti): AGS token, line items and scores to topic progress` ·
`feat(admin): LTI tools screens and topic form` · `feat(front): LTI player`.

M1.3: `feat(lti): platform deep linking` · `feat(admin): pick content from an LTI tool`.

M1.4: `feat(lti): tool launch on packbackbooks/lti-1p3-tool with user and role mapping` ·
`feat(lti): tool grade passback` · `feat(lti): tool deep-linking course picker` ·
`feat(admin): LTI platforms screens` · `test(lti): Moodle round-trip profile`.

M1.5: `spike(liascript): package the LiaScript SCORM build` (throwaway branch, result in the plan) ·
`feat(liascript): topic type with versioned Markdown and assets` · `feat(liascript): SCORM packaging
and player` · `feat(admin): LiaScript editor with preview and versions` ·
`feat(courses-import-export): LiaScript strategy`.

M1.6: `feat(scorm): detect and label Adapt packages` · `test(scorm): Adapt spoor fixture`.

M1.7: `docs: ADR 0013 Adapt build worker` · `feat(adapt-builder): GPL build service` ·
`feat(adapt): JSON source topic type behind a flag` · `feat(adapt): build jobs and import through
Path A`.

M1.8: `feat(tenancy): per-tenant H5P internal token` · `fix(h5p): restrict library administration to
the platform` · `fix(front,admin): refresh the H5P player model on token rotation` · `fix(infra):
redact _token in access logs`.

M1.9: `docs: OpenAPI and READMEs for lti, uploads, liascript, adapt` · `ci: nightly LTI and Adapt
round-trips` · `docs(roadmap): Phase 1 status`.

---

## 14. Decisions taken (to confirm)

1. **Milestone order**: hardening → LTI Platform → LTI deep linking → LTI Tool → LiaScript → Adapt A →
   Adapt B → H5P items → conformance.
2. **One `uploads` package** for every upload path, with a safe extractor that replaces all
   `ZipArchive::extractTo` calls; virus scanning via an optional clamd profile, off by default.
3. **Per-tenant content origin** `<slug>.content.<base>` with the SCORM/cmi5 player moved there and a
   topic-scoped tracking token; CSP report-only first.
4. **LTI**: one `lti` package; Platform side first-party on `firebase/php-jwt`; Tool side on
   `packbackbooks/lti-1p3-tool`; per-tenant keys rotated monthly; no cookies on the platform side.
5. **LTI scores complete topics** through `CourseProgressRepository`; past scores are kept, never
   silently changed.
6. **LiaScript rendering (b′)**: our SCORM packaging of the vendored LiaScript SCORM build, played by
   the existing SCORM runtime; spike decides, exporter container as fallback.
7. **Adapt Path B** in a separate GPL-3.0 build service (`api/adapt-builder`), off by default, imported
   through Path A.
8. **Learner players in the current `front`** for Phase 1 (iframe wrappers), ported to `front/web` with
   ADR 0008.
9. **ADRs to propose**: 0012 (LTI package and libraries) with M1.2, 0013 (Adapt build worker) with M1.7.
