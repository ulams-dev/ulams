# Licensing

ulams combines code under several licences. The most specific `LICENSE` file wins: a file is covered
by the nearest `LICENSE` in its folder or a parent folder; the root `LICENSE` (MIT) covers everything
that has no closer licence file. This page is an engineering summary, not legal advice.

## Overview

| Path | Licence | Notes |
|---|---|---|
| `/` (root files, `docs/`) | MIT | `LICENSE` |
| `api/` (Laravel application) | Apache-2.0 | `api/LICENSE` (inherited from the upstream API repository; `api/composer.json` still says MIT, to be aligned) |
| `api/packages/*` | MIT (one package: see its `LICENSE`) | Imported from EscolaLMS / Wellms; original copyright notices are kept, ulams contributors are added |
| `api/packages/h5p` | MIT | Read-only index and HTTP client; contains no H5P code |
| `api/h5p` | **GPL-3.0-or-later** | Separate program (Lumi `h5p-nodejs-library`, H5P core and editor). See below |
| `api/pdf` | MIT | PDF renderer service (pdfme, MIT); bundled fonts under SIL OFL 1.1, see `api/pdf/fonts/README.md` |
| `front/` | MIT | `front/package.json` |
| `front/src/lib/*` | MIT | Imported libraries, see each README; `scorm-player` relicensed MIT by its owner |
| `admin/` | MIT | The copyright holder of the original admin confirmed MIT for the monorepo |
| `admin/src/lib/markdown-editor` | BSD-3-Clause, © General Outline, Inc. | The notice must be reproduced in distributed builds |
| `admin/src/lib/gift-pegjs` | MIT, © Christopher P. Fuhrman | |

## Rules for an open-core codebase

1. **No GPL or AGPL code is linked into the API or bundled into admin or front.** Copyleft
   components run as separate programs and are reached only over HTTP, CLI or an iframe with
   `postMessage` (`docs/decisions/0003-h5p-as-isolated-gpl-service.md`).
2. Never copy or port code from `api/h5p` (or any GPL project) into other folders. Implementing the
   same protocol or interface from scratch is fine.
3. Distributing `api/h5p` (Docker image, Helm chart) means conveying GPL software: ship the licence
   text and offer the corresponding source, including the pinned H5P core and editor.
   The published image `ghcr.io/ulams-dev/h5p` (`.github/workflows/publish.yml`) is such a
   distribution. It ships `LICENSE` at `/app/api/h5p/LICENSE`; its labels name the licence, the
   source repository and revision (`org.opencontainers.image.source`, `.revision`) and the pinned
   H5P core and editor commits (`dev.ulams.h5p.core-ref`, `dev.ulams.h5p.editor-ref`). That is the
   corresponding source. Offering the image from a registry relies on GPL-3.0 section 6(d): the
   source is offered from a network server (this GitHub repository and the H5P repositories)
   and must stay available for as long as the image is offered. Keep the repository public and
   never delete or rewrite a revision a published image points to; if the repository goes
   private, publish the h5p source elsewhere first.
4. New dependencies are checked for their licence before they are added (see `CLAUDE.md`).
5. Imported code keeps its original copyright notices.

## PDF templates and certificates

PDF templates are designed in admin with the pdfme designer (`@pdfme/ui`, MIT) and rendered by
`api/pdf` (`@pdfme/generator` and `@pdfme/schemas`, MIT). Fonts embedded in PDFs are SIL OFL 1.1
(Noto Sans, Plus Jakarta Sans, Playfair Display, Space Grotesk, JetBrains Mono, Baloo 2, Nunito);
their licence texts ship with the service. ReportBro (AGPL-3.0) was removed.

## Known issues (tracked in `docs/ROADMAP-TODO.md`)

- Infrastructure defaults with copyleft licences: MinIO (AGPL-3.0, upstream archived) and Soketi
  (AGPL-3.0). Fine unmodified as separate processes; to be replaced by permissive defaults
  (SeaweedFS or RustFS, Laravel Reverb after the framework upgrade). Redis was replaced by Valkey
  (BSD-3-Clause).
- Binaries in the PHP image (ffmpeg built with GPL codecs, pngquant, gifsicle, jpegoptim) run as
  separate processes; distributing the image requires offering their source.
