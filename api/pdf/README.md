# api-pdf

PDF renderer for Ulams certificates and PDF templates, built on
[pdfme](https://pdfme.com) (`@pdfme/generator`, `@pdfme/schemas`, MIT). Laravel
(`api/packages/templates-pdf`) is its only client; browsers never call it.

Licence: MIT (`LICENSE`). Bundled fonts: SIL OFL 1.1 (`fonts/README.md`).

## API

All routes except `/health` require `X-Internal-Token: $PDF_INTERNAL_TOKEN`.

| Route | Answer |
|---|---|
| `GET /health` | `{"status":"ok","active":0,"fonts":9}` |
| `POST /render` | body `{"template": <pdfme template>, "inputs": [{"<field name>": "<value>"}, ...]}` → `application/pdf` (one copy of the template per input record) |
| `GET /fonts` | `{"data":[{"name":"NotoSans-Regular","file":"NotoSans-Regular.ttf","fallback":true}, ...]}` |
| `GET /fonts/:file` | TTF bytes |

`/render` behaviour:

- `inputs` defaults to `[{}]`. Every editable field gets a string; missing values render empty
  (header `X-Render-Warnings: <n>`), barcode/QR fields with an empty value are left out. Fields
  marked `required` without a value → `422 missing_required_fields` with the field names.
- Only the bundled fonts are used; an unknown `fontName` falls back to Noto Sans.
- No outbound requests: `basePdf` must be a blank page (`{width, height, padding}`) or a
  `data:application/pdf;base64,` URI, images must be `data:` URIs.
- Errors are JSON `{"error": "<code>", "message": "...", "details": ...}`: 400 `invalid_template` /
  `invalid_inputs` / `invalid_json`, 401 `unauthorized`, 413 `payload_too_large` / `too_many_inputs` /
  `too_many_pages` / `too_many_fields`, 422 `unsupported_field_type` / `remote_image` /
  `missing_required_fields` / `render_failed`, 503 `busy` (with `Retry-After`), 504 `render_timeout`.

Field types: text, multi-variable text, image, SVG, line, rectangle, ellipse, table, QR code,
Code128, Code39, EAN-13, EAN-8, PDF417, DataMatrix (`src/plugins.ts`, mirrored by the admin
designer in `admin/src/components/PdfEditor/plugins.ts`).

## Limits and configuration

pdfme runs in worker threads; a render over the time limit terminates its worker, which is replaced.

| Env | Default | |
|---|---|---|
| `PORT` | `3000` | |
| `PDF_INTERNAL_TOKEN` | — | required; the service refuses to start without it |
| `PDF_MAX_BODY_SIZE` | `5mb` | JSON body limit |
| `PDF_MAX_INPUTS` | `100` | input records per request |
| `PDF_MAX_PAGES` / `PDF_MAX_FIELDS_PER_PAGE` | `20` / `200` | |
| `PDF_RENDER_TIMEOUT_MS` | `20000` | |
| `PDF_MAX_CONCURRENCY` / `PDF_MAX_QUEUE` | `2` / `16` | worker threads / waiting renders before 503 |
| `PDF_FONTS_DIR` | `./fonts` | |
| `LOG_LEVEL` | `info` | pino |

## Development

```bash
corepack yarn workspace api-pdf test          # vitest (Node 22)
corepack yarn workspace api-pdf dev           # PDF_INTERNAL_TOKEN=... required
docker compose -f api/docker-compose.yml up -d --build pdf
node api/pdf/scripts/certificate-templates.mjs  # rebuild templates-pdf/resources/pdfme/certificate-*.json
```

The image is built from the repository root (`api/pdf/Dockerfile`, allow-list in
`Dockerfile.dockerignore`), like `api/h5p`.
