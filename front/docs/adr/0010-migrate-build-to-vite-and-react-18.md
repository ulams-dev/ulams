# 0010. Migrate the build from CRA (react-app-rewired) to Vite, on React 18

- Status: Accepted (retroactive)
- Date: 2024-02-12

## Context and Problem Statement

The front was built with `react-scripts` 4 customised through `react-app-rewired` +
`customize-cra` + `react-app-rewire-alias` (`config-overrides.js`, added 2021-10-12 for
TS path aliases and `.mjs` support). React 18 had been adopted in 2022-05 (`createRoot`,
`ifxing build on r18`) but `react`/`react-dom` were later loosened to `^17||^18`
(2022-06-20), and CRA was no longer maintained.

## Considered Options

Not recorded (PR title: "bundler update react-scripts -> vite, gh actions update").

## Decision Outcome

Vite 5 with `@vitejs/plugin-react`, `vite-tsconfig-paths` (aliases from
`tsconfig.paths.json`) and `vite-plugin-eslint`; `build` = `tsc && vite build`, output
`dist/`. Env prefix changed from `REACT_APP_` to `VITE_APP_`. React pinned to `^18.2`,
ESLint 8 + typescript-eslint replaces `eslint-config-react-app`. Later additions:
`@sentry/vite-plugin` (source maps), `rollup-plugin-visualizer` and `manualChunks`.

### Consequences

- Good: faster dev server and builds; config in one `vite.config.mjs`.
- Bad: leftovers remain - `config-overrides.js`, `"eject": "react-app-rewired eject"`, the
  Jest config pointing at CRA-era `config/jest/*` transforms and `react-app-polyfill`.
- Bad: env var rename forced changes in CI, Docker and runtime injection ([0009](0009-runtime-environment-injection-into-static-build.md)), which accepts both prefixes.

## Evidence

- `436e0dc4` 2021-10-12 "ts congif aliases and stuff (#60)" - adds `react-app-rewired`, `front/config-overrides.js`
- `697c35b1` 2022-05-31 "build and clearup (#183)" - React 17 -> 18; `91b26ade` 2022-06-08 "ifxing build on r18 (#201)" - `createRoot`
- `c95a92d7` 2022-06-20 "react res" - `^17||^18`
- `aec28436` 2024-02-12 "Feature/bundler update (#430)" - `front/package.json`, `front/vite.config.mjs`, workflows
