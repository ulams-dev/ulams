# 0086. Interactive topic type: author-uploaded JavaScript packages in an opaque sandbox on the content origin

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/interactive-demos.md` (M1, M2); availability default pending #150

## Context and problem statement

Authors want course items that are live JavaScript: a 3D solar-system scene, a scrolling map with charts,
small mathematical toys. They want them as real course items with progress, not as an external link.
SCORM and LiaScript can carry JavaScript, but they bring their own runtime contracts (`window.API`,
Markdown). Both run with `allow-same-origin` on the content origin. Neither lets one package back
several topics, each opening at its own step.

The spec allows model-written HTML/JS only in the `simulation` component (ADR 0053), which is off by
default (#56). That rule is about code **the model** writes. Here a human author uploads the code and
answers for it, as they already do for SCORM, cmi5 and H5P.

## Considered options

1. **A new topic type `InteractiveTopic`, backed by a versioned zip package with a manifest
   (`ulams-interactive.json`), played from the per-tenant content origin in an opaque sandbox, and
   talking to the lesson page only through a typed `postMessage` bridge (ADR 0087).**
2. Ship the apps as SCORM 1.2 packages. They would get `allow-same-origin`, a full-package CSP with
   `'unsafe-eval'`, and no step addressing. There would be no background mode.
3. Reuse the planned `simulation` component. It is single-file, limited to 200 KB and inline-only,
   and it is gated by the model-written-code flag. A 0.8 MB Three.js build does not fit, and author
   code would be wrongly tied to the AI flag.

## Decision

Option 1.

- **Package.** A zip with `index.html`, its assets and `ulams-interactive.json`. The manifest holds:
  title, version, licence (an SPDX id), attribution, source URL, locales, steps (id, title per locale,
  text alternative, optional poster), capabilities, network allow-list and accessibility notes. The
  JSON Schema lives at `api/packages/interactive/resources/schemas/ulams-interactive/v1.json`.
- **Storage.** `interactive_packages` holds the library entry. `interactive_package_versions` holds
  immutable versions: manifest JSON, a file list with sha256 hashes, sizes and the author. Files are
  stored at `interactive/<storage_key>/v<n>/` on the package disk. `storage_key` is a random UUID, so
  a path cannot be guessed from an id. Topics point to a package and pin a version; a new version
  never changes a published topic until the author re-pins it.
- **Topic.** `topic_interactives` stores `interactive_package_id`, `version` (nullable means
  "current"), `start_step`, `end_step`, `completion_rule` (`on_open|on_range_end|on_complete|on_score`),
  `pass_score`, `display` (`inline|background`), `height` and `text` (Markdown with citations, shown
  beside the frame or over the background). One package can back many topics.
- **Delivery.** Files are served only from the tenant content origin under `/interactive/*`. The
  existing `ContentFileController` sets the CSP for this prefix itself, from the version's manifest:
  `default-src 'none'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; media-src 'self' blob:; font-src 'self' data:; connect-src 'self' <allow-list>; worker-src 'self' blob:; frame-ancestors <app origins>; form-action 'none'; base-uri 'none'; object-src 'none'`.
  No `'unsafe-eval'`. Caddy keeps its generic CSP for the other prefixes and sets it only when absent
  (`header ?Content-Security-Policy`).
- **Sandbox.** The iframe has `sandbox="allow-scripts allow-popups allow-popups-to-escape-sandbox"`,
  with no `allow-same-origin`, so the package runs in an opaque origin. It can read no cookies, no
  storage and no other tenant's files. It sets `referrerpolicy="no-referrer"`. The parent accepts a
  message only if `event.source` is the frame, `event.origin === "null"` and the per-launch nonce
  matches.
- **No credentials in the frame.** The frame never receives a token. Progress, score and events go
  frame → lesson page (bridge) → front BFF (learner session) → `POST /api/interactive/topics/{topic}/events`
  (`auth:api`, `attend` gate). The API turns them into progress through
  `CourseProgressRepositoryContract::updateInTopic` (ADR 0018).
- **Network.** The default is none: `connect-src 'self'`, which covers only the package's own files.
  A manifest `network` allow-list (exact `https://` origins) is honoured only when the tenant setting
  `ulams_interactive.allow_network` is on (default off). The admin sees the list before the upload is
  accepted.
- **Uploads.** Uploads go through the upload guard (ADR 0017), with policy kind `interactive`
  (`UPLOADS_INTERACTIVE_MAX_MB`, default 50), zip limits `package`, and an extension allow-list. HTML,
  JS and CSS packages are allowed; `.php`, `.phar`, `.htaccess`, `.svgz` and executables are not.
- **Availability.** The topic type is on by default, like SCORM. A tenant can switch it off with
  `ulams_interactive.enabled` (AdministrableConfig). It is independent of `simulations_enabled`:
  model-written code stays off by default (#56), and the course builder never generates or edits an
  interactive package.
- **Relation to `simulation`.** The `simulation` component (ADR 0053) will reuse this player, CSP
  family and bridge protocol, with its stricter limits (single file, 200 KB, no `'self'` scripts, no
  network ever, critic and solvability gates, author approval). It stays a separate element kind
  behind its own flag.

## Consequences

- Good: any static web app (Three.js, maps, canvas toys) becomes a course item with progress, steps,
  a text alternative and a background mode, without SCORM.
- Good: the opaque origin removes the same-site risks accepted in the ADR 0014 amendment for this
  type: no cookie tossing, no storage, no same-origin `fetch` to the app.
- Bad: packages cannot use `localStorage`, IndexedDB or cookies. Apps that assume them must guard the
  calls (the gravity adapter does). State that must persist goes through the bridge.
- Bad: `'unsafe-inline'` scripts are allowed inside the sandbox. This is acceptable because the frame
  is opaque, has no credentials and cannot reach the network. Hashes per file are a later hardening.
- Bad: one more topic type to keep in the admin, the CLI, export/import and the docs.
