# 0006. One Docker image, configured at container start by rewriting `index.html`

- Status: Accepted (retroactive)
- Date: 2022-04-21

## Context and Problem Statement

The panel is a static SPA, so `REACT_APP_API_URL` and similar values are normally baked in at build time (umi `define`). Self-hosters of Wellms need to point one published image at their own API URL without rebuilding the front-end.

## Considered Options

- Build-time variables only (used by the GitHub Pages build in `.github/workflows/pages.yml`).
- Placeholders in `index.html` replaced when the container starts (chosen for Docker).

## Decision Outcome

`config/config.ts` emits a head script `window.REACT_APP_API_URL = null; window.REACT_APP_SENTRYDSN = null; window.REACT_APP_YBUG = null;`. The multi-stage `Dockerfile` builds once (`yarn build`, then copies `dist/index.html` to `tpl.html`), and `entrypoint.sh` copies `tpl.html` back to `index.html` and `sed`s in `$API_URL`, `$SENTRYDSN` and `$YBUG` on every start. Code reads `window.REACT_APP_API_URL || REACT_APP_API_URL`, so the build-time value is the fallback. `.github/workflows/docker_build.yml` builds the image on each GitHub release and pushes `escolalms/admin:<tag>` and `:latest` to Docker Hub (multi-arch is commented out: "with QEMU it takes more then 3 hours"), notifies Mattermost and calls a webhook that rolls out the dev environments on Kubernetes; the tag is written to `/version`. In 2025 the entrypoint also writes an `.htaccess` that routes unknown paths to `index.php`, needed by the browser router (see 0008).

### Consequences

- Good: one image per release; self-hosters only set env vars (`docker run --env API_URL=…`).
- Good: the same mechanism was reused for Sentry and Ybug keys (2023) and for multi-domain setups (0007).
- Bad: string-replacement on HTML is brittle (the `sed` pattern `API_URL = null` must match the generated head script exactly); a new runtime variable needs edits in three places.

## Evidence

- `a1edee79` 2022-04-21 "Feature/docker build (#455)" — `admin/Dockerfile`, `admin/entrypoint.sh`, `admin/.github/workflows/docker_build.yml`, `admin/.dockerignore`, `admin/src/app.tsx`.
- `f0b1628f` / `2189b143` 2023-01-09 "sentry & ybug image buyild" — `admin/entrypoint.sh`, `admin/Dockerfile`.
- `1490eabd` 2025-01-02 "Feature/variables update (#1107)" — `admin/Dockerfile`, `docker_build.yml`.
- `bd3156cc` 2025-06-12 "spa broweser router" — `admin/Dockerfile`, `admin/entrypoint.sh` (.htaccess).
