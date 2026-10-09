# ulams front

The learner front-end: course catalogue, course pages, the lesson player for every topic type,
quizzes, certificates, profile, cart and the per-tenant landing pages. React 18 + Vite, styled with
CSS Modules and the `--ulams-*` theme variables.

## Run

From the repository root (with the API stack running, see the root README):

```bash
corepack yarn dev:front     # http://localhost:3000 (platform) and http://<tenant>.app.localhost
```

## Where things are

| Path | What |
|---|---|
| `src/pages/` | Routes; `pages/landing/{coffee,oncall,nightsky}` are the experience landing pages |
| `src/components/` | App components (course player, profile, consultations, webinars, cart, …) |
| `src/lib/components` | Component library (`@ulams/components`), theme presets and CSS-variable contract (`theme/README.md`) |
| `src/lib/sdk` | API client and React context (`@ulams/sdk`) |
| `src/lib/ts-models` | API types (`@ulams/ts-models`) |
| `src/lib/tenant` | Resolves the tenant API from the browser host (`@ulams/tenant`) |
| `src/lib/scorm-player` | SCORM runtime (shared with admin) |
| `tests/` | Unit tests (`node --test`) and the visual regression harness (`tests/visual`) |
| `docs/` | Retroactive ADRs and the design briefs (`docs/design`) |

## Configuration

`.env.example` lists the variables. The API URL comes from a runtime-injected value, then the host
rule `VITE_APP_TENANT_API_HOST_PATTERN` (default `{slug}.app.localhost=>http://{slug}.localhost`),
then the build-time `VITE_APP_PUBLIC_API_URL`.

## Rules

- No styled-components and no GPL imports (`@lumieducation/*`); both are blocked by lint
  (`yarn lint`, `scripts/check-no-gpl-imports.cjs`).
- Learner-facing UI meets WCAG 2.2 AA.
- H5P is embedded as an iframe served by `api/h5p` (`players/H5Player`).
