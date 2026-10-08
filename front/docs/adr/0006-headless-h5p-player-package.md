# 0006. Render H5P content with the headless `@escolalms/h5p-react` package

- Status: Accepted (retroactive)
- Date: 2021-10-08

## Context and Problem Statement

Course topics of type H5P are stored and served by the API (headless H5P in the
`escolalms/h5p` PHP package). The standard H5P integration is a server-rendered
PHP/iframe page; the front is a separate SPA.

## Considered Options

Not recorded.

## Decision Outcome

H5P content is rendered in React by a dedicated package built in `EscolaLMS/H5P-player`
(CRA project started 2021-04-14, editor and player components, published as
`h5p-headless-player`, renamed `@escolalms/h5p-react` on 2021-10-07). The package fetches
content/library JSON from the API and boots the H5P runtime client-side; the same package
provides the editor used by the admin panel. The front used `h5p-headless-player` from the
restart and switched to `@escolalms/h5p-react` on 2021-10-08. `public/h5p_overwrite.css`
restyles H5P output.

### Consequences

- Good: H5P plays inside the SPA with its routing, theming and progress tracking.
- Good: one player/editor package shared by admin and front.
- Bad: tight coupling to the H5P core JS version bundled by the backend; regressions
  ("flashing", serialization fixes) are fixed in the package and need releases.

## Evidence

- `6cb7cc44` 2021-09-17 "copied" - `front/package.json` (`h5p-headless-player`)
- `e0b6e505` 2021-10-08 "change h5p-headless-player to @escolalms/h5p-react; up connector (#49)"
- `3177c538` 2022-08-03 "Feature/h5p update (#317)"
- H5P-player repo: `a03b4f2` 2021-04-14 "Initialize project using Create React App", `faab420` "editor", `40d6097` "player", `6053871` 2021-10-07 "new name", `6aba486` 2025-04-25 "Merge pull request #42 from EscolaLMS/feature/flashing-fix"
- sdk repo: `5ad33310` 2021-10-08 "change h5p-headless-player to @escolalms/h5p-react"
