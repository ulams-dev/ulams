# Architecture Decision Records - front

Architecture decisions for `front/`, the Wellms (Escola LMS) demo learner front-end
(React + TypeScript + Vite, built on `@escolalms/sdk` and `@escolalms/components`).

## Process

We use [MADR](https://adr.github.io/madr/) (Markdown Architectural Decision Records). Each
record is one file `NNNN-kebab-case-title.md` with: Title, Status, Date, Context and Problem
Statement, Considered Options, Decision Outcome, Consequences (good/bad) and Evidence. To add
one, copy an existing record, take the next free number, set Status to `Proposed`, open a PR,
and change it to `Accepted` when merged; a replaced decision gets `Superseded by NNNN` and is
never deleted. Records **0001-0099 are retroactive**: they were reconstructed in 2026 from the
git history imported into the `ulams` monorepo (hashes are monorepo commit hashes) and from
the histories of the vendored libraries (cited with the library repo name). They document what
the history shows; motivations not written down at the time are marked "Inferred:". Numbers
**0100 and above are reserved for monorepo-era decisions** - new ADRs start at 0100.

## Index

| No. | Title | Status | Date |
| --- | --- | --- | --- |
| [0000](0000-record-architecture-decisions.md) | Record architecture decisions | Accepted | 2026-10-08 |
| [0001](0001-restart-front-as-rest-client-cra-typescript-app.md) | Restart the front-end as a REST-consuming CRA + TypeScript app | Accepted (retroactive); build tool superseded by 0010 | 2021-09-17 |
| [0002](0002-api-access-through-escolalms-sdk-react-context.md) | API access and app state through `@escolalms/sdk` and its React context | Accepted (retroactive) | 2021-10-12 |
| [0003](0003-generated-ts-models-for-api-types.md) | API model types generated from Eloquent (`@escolalms/ts-models`) | Accepted (retroactive) | 2022-03-18 |
| [0004](0004-shared-component-library-with-styled-components-theming.md) | Shared UI library `@escolalms/components` with styled-components theming | Accepted (retroactive) | 2022-05-25 |
| [0005](0005-switchable-local-or-published-components-source.md) | Switchable local vs published source for `@escolalms/components` | Accepted (retroactive) | 2024-02-13 |
| [0006](0006-headless-h5p-player-package.md) | Render H5P content with the headless `@escolalms/h5p-react` package | Accepted (retroactive) | 2021-10-08 |
| [0007](0007-client-side-scorm-player-with-service-worker.md) | Play SCORM packages client-side with `@escolalms/scorm-player` | Accepted (retroactive) | 2025-02-18 |
| [0008](0008-configurable-hash-or-browser-router.md) | Router type (HashRouter / BrowserRouter) chosen by configuration | Accepted (retroactive) | 2021-11-08 |
| [0009](0009-runtime-environment-injection-into-static-build.md) | Runtime configuration injected into the static build (`window.VITE_APP_*`) | Accepted (retroactive) | 2022-05-24 |
| [0010](0010-migrate-build-to-vite-and-react-18.md) | Migrate the build from CRA (react-app-rewired) to Vite, on React 18 | Accepted (retroactive) | 2024-02-12 |
| [0011](0011-pwa-service-worker.md) | Progressive Web App service worker | Deprecated (retroactive) | 2021-09-20 |
| [0012](0012-sentry-error-monitoring.md) | Sentry for error monitoring, with releases and source maps from CI | Accepted (retroactive) | 2021-11-18 |
| [0013](0013-capacitor-mobile-shell.md) | Ship the same SPA as iOS/Android apps with Capacitor | Accepted (retroactive) | 2024-05-15 |
| [0014](0014-i18next-with-shared-component-translations.md) | i18next for UI strings, merged with translations shipped by Components | Accepted (retroactive) | 2021-09-17 |
| [0015](0015-ci-github-actions-pages-and-docker-images.md) | CI on GitHub Actions: static analysis, GitHub Pages demo, Docker Hub images | Accepted (retroactive) | 2021-09-20 |
