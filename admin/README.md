# ulams admin

The admin and author panel: courses and their programs (all topic types), H5P content and libraries, quizzes, users, products, certificates (pdfme designer), templates, settings and reports. React 18 with umi/max and Ant Design.

## Run

From the repository root (with the API stack running, see the root README):

```bash
corepack yarn dev:admin     # http://localhost:8000 (platform) and http://<tenant>.admin.localhost
```

Platform login: `admin@ulams.app` / `secret`. On a tenant, `admin@<slug>.ulams.app`.

## Where things are

| Path | What |
| --- | --- |
| `config/` | umi config and routes (`routes.ts`), aliases (`@ulams/*` from `../front/src/lib`) |
| `src/pages/` | Screens; menu access in `src/access.ts` (permissions + installed packages) |
| `src/services/ulams/` | API calls |
| `src/components/H5P/` | H5P editor/player iframes served by `api/h5p` |
| `src/components/PdfEditor/` | pdfme certificate/template designer |
| `src/lib/markdown-editor` | Rich Markdown editor (BSD-3-Clause fork of Outline's editor) |
| `src/lib/gift-pegjs` | GIFT quiz parser |
| `src/tenant.ts` | Resolves the tenant API from the browser host |

## Configuration

`REACT_APP_API_URL` sets a fixed API URL; without it the API comes from the host rule `REACT_APP_TENANT_API_HOST_PATTERN` (default `{slug}.admin.localhost=>http://{slug}.localhost`).

## Tests

```bash
corepack yarn workspace admin playwright:headed   # end-to-end
```

No styled-components and no `@lumieducation/*` imports (lint rules).
