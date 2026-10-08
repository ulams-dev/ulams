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
| `front/` | MIT | `front/package.json` |
| `front/src/lib/*` | MIT, except `scorm-player` (no licence upstream, see open items) | Imported libraries, see each README |
| `admin/` | not declared upstream (see open items) | |
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
4. New dependencies are checked for their licence before they are added (see `CLAUDE.md`).
5. Imported code keeps its original copyright notices.

## Known issues (tracked in `docs/ROADMAP-TODO.md`)

- `reportbro-designer` (AGPL-3.0) is bundled into admin (PDF certificate designer); its server image
  uses `reportbro-lib` (AGPL-3.0). To be replaced or licensed commercially.
- Infrastructure defaults with copyleft licences: MinIO (AGPL-3.0, upstream archived) and Soketi
  (AGPL-3.0). Fine unmodified as separate processes; to be replaced by permissive defaults
  (SeaweedFS or RustFS, Laravel Reverb after the framework upgrade). Redis was replaced by Valkey
  (BSD-3-Clause).
- Binaries in the PHP image (ffmpeg built with GPL codecs, pngquant, gifsicle, jpegoptim) run as
  separate processes; distributing the image requires offering their source.
