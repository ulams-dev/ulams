# 0008. Router type (HashRouter / BrowserRouter) chosen by configuration

- Status: Accepted (retroactive)
- Date: 2021-11-08

## Context and Problem Statement

The demo is deployed both to GitHub Pages (static hosting, no rewrite of deep links to
`index.html`, sub-path `/Front/`) and to servers/containers that can rewrite all paths to
the SPA.

## Considered Options

- `BrowserRouter` only - breaks deep links on GitHub Pages.
- `HashRouter` only - ugly URLs, breaks OAuth return URLs.
- Choose per deployment (chosen).

## Decision Outcome

`REACT_APP_ROUTING_TYPE` (now `VITE_APP_ROUTING_TYPE`, or `window.VITE_APP_ROUTING_TYPE` at
runtime, see [0009](0009-runtime-environment-injection-into-static-build.md)) selects
`HashRouter` or `BrowserRouter` in `components/Routes/index.tsx` via `utils/router.ts`.
GitHub Pages builds set `HashRouter`; the Docker image defaults to `ROUTING_TYPE=HashRouter`.
Code that builds URLs (social login return URLs in `SocialButtons.tsx`) adds `/#` or `%23`
when hash routing is active. Apache `mod_rewrite` was enabled later for BrowserRouter
deployments.

### Consequences

- Good: one build works on static hosting and on real servers.
- Bad: every absolute URL handed to the API (OAuth, payment return, e-mail links) must be
  router-aware; several later bug fixes concern return URLs.

## Evidence

- `8dc08824` 2021-11-08 "env for build and test (#110)" - `front/.env`, `front/.github/workflows/pages.yml`, `front/src/components/Routes/{BrowserRouter,HashRouter,index}.tsx`
- `5ece05f9` 2022-01-26 "return url fix (#156)"
- `79b88b78` 2022-02-09 "google login and mattermost fix refresh data (#167)"
- `d4f4a06f` 2024-11-20 "rewrite all (#511)" - `front/Dockerfile` (`a2enmod rewrite`)
- `32003f17` 2024-11-27 "Feature/mod rewrite (#515)" - `front/config/php/index.php`, `front/index.html`
- current: `front/src/utils/router.ts`, `front/Dockerfile` (`ENV ROUTING_TYPE="HashRouter"`)
