# 0012. Edit and preview H5P content with the shared `@escolalms/h5p-react` package

- Status: Accepted (retroactive)
- Date: 2021-10-13

## Context and Problem Statement

Interactive content in Wellms is H5P. The API runs H5P "headless": the `escolalms/headless-h5p` package, built on `h5p/h5p-core` and `h5p/h5p-editor`, stores libraries and content and returns editor and player settings as JSON, so no PHP-rendered H5P editor page exists. The admin panel has to host the H5P editor and player itself, and the learner front-end needs the same player.

## Considered Options

- Git dependency on the `EscolaLMS/H5P-player` repository, pinned by commit (June–September 2021).
- The same code published to npm as `@escolalms/h5p-react` (chosen, October 2021).

## Decision Outcome

H5P UI lives in a separate React library (`EscolaLMS/H5P-player`, npm `@escolalms/h5p-react`), shared by admin and front. The admin fetches editor settings from the API (`src/services/escola-lms/h5p.ts`, `editorSettings()`), renders `ContextlessEditor` (since mid-2022, "contextless" players and editors take settings as props instead of a React context), and saves through `updateContent()`. The editor runs in an iframe (`#h5p-editor`); when the JWT is refreshed the admin posts a `TOKEN_CHANGED` message into it (see 0004).

### Consequences

- Good: one H5P integration for admin and front; the API stays headless.
- Good: the library can be versioned and released independently of the panel.
- Bad: H5P fixes need a release of a separate package; in the monorepo this library is being vendored.
- Bad: the iframe and the parent must keep the auth token in sync by `postMessage`.

## Evidence

- `f555421a` 2021-06-22 "h5p elements (#25)" — `admin/src/pages/H5P/*`, `admin/src/components/H5PContentSelect`, `admin/package.json` (`"h5p-player": "EscolaLMS/H5P-player"`).
- `9ec56587` 2021-10-13 "up h5p-react package (#189)" — `admin/package.json` (→ `@escolalms/h5p-react`).
- `75c8973c` 2022-01-21 "h5p update (#297)" — `admin/src/components/H5P/editor.tsx`, `admin/src/components/H5Player/index.tsx`.
- `4c1d9f7a` 2022-07-28 "Feature/h5p fixes (#530)" — `admin/src/components/H5P/editor.tsx`, `player.tsx` (`@escolalms/h5p-react` 0.2.5 → ^0.2.10).
- H5P-player repo: `a03b4f2` 2021-04-14 "Initialize project using Create React App"; `6053871` 2021-10-07 "new name" (package renamed to `@escolalms/h5p-react`); `20cad7d` 2022-07-25 "Feature/contextless players (#23)".
