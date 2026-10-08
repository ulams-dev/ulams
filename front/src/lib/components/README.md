# components (vendored)

UI component library (styled-components, React 18) used by the front app.

- Upstream: https://github.com/EscolaLMS/Components (npm `@escolalms/components`)
- Version: 0.0.165, commit `34b5f4ab32901ec120efe074ed5ebf44bf0f4be1`
- Copied: `src/` only. Left out: styleguidist docs and examples (`*.md`, `*.png`, `mock.json`), build/lint config.
- Import as `@lms/components/...` (was `@escolalms/components/lib/...`); path alias in `front/tsconfig.json`.
- Local changes: `@escolalms/sdk/lib/*` → `@lms/sdk/*`, and the bare `types/...` / `components/...`
  imports (upstream `baseUrl: src`) → `@lms/components/...`.
- Runtime deps now live in `front/package.json` (chroma-js, formik, rc-*, react-markdown 8, rehype/remark,
  react-pdf, react-player, photoswipe, screenfull, katex, …). It now shares front's `react-i18next` 11
  (the npm package had its own 12.x copy). `@escolalms/h5p-react` stays an npm dependency for now.
- License: MIT (`LICENSE`).
