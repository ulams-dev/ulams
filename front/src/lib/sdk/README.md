# sdk (vendored)

API client + React context/hooks for the Wellms API.

- Upstream: https://github.com/EscolaLMS/sdk (npm `@escolalms/sdk`)
- Version: 1.0.0 (tag `1.0.0`), commit `0a06ea54fc99853570d78ef72eca53257ccd7ff6`
- Copied: `src/` only (no tests, no build config).
- Import as `@lms/sdk` / `@lms/sdk/react`, `@lms/sdk/types`, `@lms/sdk/services/...`
  (was `@escolalms/sdk/lib/...`); path alias in `front/tsconfig.json`.
- Runtime deps moved to `front/package.json`: `umi-request`, `jssha`. Model types come from `../ts-models`.
- License: MIT (per upstream `package.json`).
