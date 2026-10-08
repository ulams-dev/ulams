# 0011. Progressive Web App service worker

- Status: Deprecated (retroactive) - registration removed 2021-10-14, files deleted 2022-05-25
- Date: 2021-09-20

## Context and Problem Statement

Right after the restart the demo was published on GitHub Pages and was meant to be
installable as a PWA.

## Considered Options

Not recorded.

## Decision Outcome

CRA's Workbox template was added (`src/serviceWorker.ts`, `src/serviceWorkerRegistration.ts`,
`serviceWorkerRegistration.register()` in `index.tsx`) together with `public/manifest.json`
and app icons. Less than a month later the registration call was removed in a TypeScript
clean-up, and the files were deleted with the Docker build work. Only the web manifest and
icons remain (refreshed in 2024 for the Capacitor app, [0013](0013-capacitor-mobile-shell.md)),
plus a commented-out registration snippet in `index.html`.

Inferred: no reason is recorded; offline caching of an LMS that depends on live API data
and authenticated content gave little benefit and risked stale bundles.

The only service worker in use today is the SCORM loader ([0007](0007-client-side-scorm-player-with-service-worker.md)), which is unrelated to PWA caching.

### Consequences

- Good: no stale-cache problems after deployments.
- Bad: no offline support; "installable" only via the manifest.

## Evidence

- `26fc2816` 2021-09-20 "pwa"; `4678cb28` 2021-09-20 "Feature/pwa (#31)" - adds `front/src/serviceWorker.ts`, `front/src/serviceWorkerRegistration.ts`
- `c4ec0595` 2021-09-21 "pwa base" - `front/package.json` homepage `/Front/`
- `c2c8d565` 2021-10-14 "ts fixes (#64)" - removes `serviceWorkerRegistration.register()` from `front/src/index.tsx`
- `95408afd` 2022-05-25 "docker build (#175)" - deletes both service worker files
