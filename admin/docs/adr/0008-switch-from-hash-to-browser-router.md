# 0008. Switch from hash routing to browser (history API) routing

- Status: Accepted (retroactive)
- Date: 2025-02-05

## Context and Problem Statement

From its first deploys (GitHub Pages and Heroku, which serve static files without rewrite rules) the
panel used umi's hash history (`/#/courses/…`). In 2025 the SCORM preview was rebuilt to run packages
client-side through a service worker that intercepts requests under `/courses/scorms/preview/__scorm__/`
(see 0014). Service-worker scopes and intercepted URLs are path-based, which does not work with hash routes.

## Considered Options

- `history: { type: 'hash' }` (used 2021–2025).
- `history: { type: 'browser' }` (chosen).

## Decision Outcome

`config/config.ts` was switched to `history: { type: 'browser' }` in the same commit that introduced the
SCORM service worker. Because deep links now hit the server, the Docker entrypoint writes an `.htaccess`
that rewrites every non-file, non-directory request to `index.php` (June 2025).

Inferred: the switch was driven by the SCORM service worker; the commit message only says "working on
preview".

### Consequences

- Good: clean URLs; path-scoped service workers work.
- Bad: every host must rewrite unknown paths to the SPA entry point; old `/#/…` bookmarks no longer resolve.
- Bad: the GitHub Pages deploy (`pages.yml`) has no server rewrites, so deep links there need a fallback.

## Evidence

- `f679e850` 2021-03-01 "heroku" — first `history: { type: 'hash' }` in `admin/config/config.ts`.
- `d32e3eba` 2021-06-11 "Feature/14 course form edit (#22)" — hash history re-enabled.
- `6e2453ca` 2025-02-05 "working on preview, problem with icons i specific scorm not resolved yet" —
  `admin/config/config.ts` (`hash` → `browser`), `admin/public/service-worker-scorm.js`.
- `bd3156cc` 2025-06-12 "spa broweser router" — `admin/Dockerfile`, `admin/entrypoint.sh`.
