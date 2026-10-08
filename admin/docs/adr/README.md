# Architecture Decision Records — admin

This folder holds the Architecture Decision Records (ADRs) for the Wellms admin panel (`admin/`,
formerly the `EscolaLMS/Admin` repository). We use the [MADR](https://adr.github.io/madr/) format:
each record states the context, the options considered, the decision and its consequences, and lists
evidence. To add a record, copy the structure of an existing file, take the next free number, name it
`NNNN-kebab-case-title.md`, set the status (Proposed / Accepted / Superseded by NNNN), and add a row to
the table below. Records are never deleted; a replaced decision is marked "Superseded by …".
Numbers **0001–0099 are retroactive**: they were reconstructed in 2026 from the git history imported
into the monorepo, after the decisions had been made. Their dates are the dates of the decisive commits,
their evidence cites monorepo commit hashes (and, for vendored libraries, hashes from those libraries'
own repositories, labelled by repo), and any motivation not written down in the history is labelled
"Inferred:". Numbers **0100 and above are reserved for monorepo-era decisions**.

| No.  | Title | Status | Date |
|------|-------|--------|------|
| [0000](0000-record-architecture-decisions.md) | Record architecture decisions | Accepted | 2026-10-08 |
| [0001](0001-ant-design-pro-umi3-scaffold.md) | Build the admin panel on Ant Design Pro (umi 3 + antd 4) | Superseded by 0003 | 2021-02-26 |
| [0002](0002-upgrade-to-react-18.md) | Upgrade to React 18 | Accepted (retroactive) | 2022-04-01 |
| [0003](0003-migrate-to-ant-design-pro-6-umijs-max-antd-5.md) | Migrate to Ant Design Pro 6 / @umijs/max 4 / antd 5 | Accepted (retroactive) | 2024-02-14 |
| [0004](0004-hand-written-rest-services-with-jwt-in-localstorage.md) | Hand-written REST service layer with JWT bearer token in localStorage | Accepted (retroactive) | 2021-06-07 |
| [0005](0005-permission-and-package-based-access-control.md) | Access control from backend permissions and installed backend packages | Accepted (retroactive) | 2021-12-15 |
| [0006](0006-runtime-env-injection-in-docker-image.md) | One Docker image, configured at container start by rewriting `index.html` | Accepted (retroactive) | 2022-04-21 |
| [0007](0007-multi-domain-runtime-config-via-php-front-controller.md) | Per-domain runtime configuration through a PHP front controller | Accepted (retroactive) | 2024-02-14 |
| [0008](0008-switch-from-hash-to-browser-router.md) | Switch from hash routing to browser routing | Accepted (retroactive) | 2025-02-05 |
| [0009](0009-i18n-with-umi-locale-plugin.md) | Internationalisation with the umi locale plugin (en-US, pl-PL, fr-FR) | Accepted (retroactive) | 2021-09-08 |
| [0010](0010-testing-jest-unit-and-playwright-e2e.md) | Jest for unit tests, Playwright (replacing Cypress) for e2e against a real API | Accepted (retroactive) | 2022-08-11 |
| [0011](0011-error-monitoring-with-sentry-and-ybug.md) | Error monitoring with Sentry and user feedback with Ybug | Accepted (retroactive) | 2021-11-19 |
| [0012](0012-headless-h5p-editor-via-h5p-react.md) | Edit and preview H5P content with `@escolalms/h5p-react` | Accepted (retroactive) | 2021-10-13 |
| [0013](0013-markdown-wysiwyg-own-fork-of-rich-markdown-editor.md) | Markdown WYSIWYG: own fork `@escolalms/markdown-editor` | Accepted (retroactive) | 2024-01-04 |
| [0014](0014-client-side-scorm-preview-with-service-worker.md) | Preview SCORM packages client-side with a service worker | Accepted (retroactive) | 2025-02-05 |
| [0015](0015-gift-quiz-editor-on-pegjs-parser-fork.md) | GIFT quizzes edited through a PEG.js parser fork `@escolalms/gift-pegjs` | Accepted (retroactive) | 2023-04-17 |
