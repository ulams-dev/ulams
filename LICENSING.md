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
| `api/adapt-builder` | **GPL-3.0-or-later** | Separate program: builds Adapt course JSON with `adapt_framework` and its plugins (GPL-3.0), reached over HTTP (ADR 0013). The image carries the framework and plugin sources and their versions; rule 3 applies if it is distributed |
| `api/pdf` | MIT | PDF renderer service (pdfme, MIT); bundled fonts under SIL OFL 1.1, see `api/pdf/fonts/README.md` |
| `front/` | MIT | `front/package.json` |
| `front/src/lib/*` | MIT | Imported libraries, see each README; `scorm-player` relicensed MIT by its owner |
| `admin/` | MIT | The copyright holder of the original admin confirmed MIT for the monorepo |
| `admin/src/lib/markdown-editor` | BSD-3-Clause, © General Outline, Inc. | The notice must be reproduced in distributed builds |
| `admin/src/lib/gift-pegjs` | MIT, © Christopher P. Fuhrman | |
| `front/interactive-bridge` | MIT | The `ulams-ix` bridge (ADR 0087); packages under any licence may include it |
| `demo-content/*` | MIT for code, CC BY 4.0 for course text and data; exceptions are named in each package's `NOTICE` | Content packages for the demo academies, played only as sandboxed content (ADR 0088); nothing outside the folder imports them (lint) |

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

## AI Course Builder dependencies

| Package | Licence | Where | Notes |
|---|---|---|---|
| `anthropic-ai/sdk` | MIT | `api/packages/ai` | Official Claude SDK; only used with `AI_DRIVER=anthropic` |
| `opis/json-schema` | Apache-2.0 | `api/packages/ai` | JSON Schema validation of model outputs, briefs and blueprints |
| `smalot/pdfparser` | LGPL-3.0 | `api/packages/course-builder` | Already in the lock file; used unmodified as a library to read PDF text (LGPL permits linking; changes to the library itself would have to be shared) |
| `league/html-to-markdown` | MIT | `api/packages/living-course` | Converts web pages of the URL connector to Markdown before fragmenting; used unmodified |
| `@ag-ui/core` | MIT | `front/sdk` | AG-UI event types; its schemas subpath (zod) is only used in tests |
| `@ulams/interactive-bridge` | MIT | `front/interactive-bridge` | The `ulams-ix` bridge (own code, no dependencies) |
| `diff` (jsdiff) | BSD-3-Clause | `front/ui` | Word-level diffs in the builder's DiffView; keep its notice in distributed bundles |
| A2UI v0.9 JSON Schemas | Apache-2.0 | `front/ui/vendor/a2ui/v0.9` | Unmodified copies of the A2UI message schemas, pinned at a commit (URL and SHA in the `NOTICE` there, with the `LICENSE` text and a README). Used only for dev-mode and test validation of `a2ui-surface` envelopes; not part of production bundles |

DOCX is read by a first-party converter (no PhpWord, which is LGPL-3.0-only; ADR 0026). No A2UI renderer or
CopilotKit code is bundled: the studio renders its own catalogue (only the A2UI message schemas are
vendored, see above).

## Demo content packages (`demo-content/`)

Interactive packages for the demo academies (ADR 0088, amended 2026-10-09). Each package folder has its own
`LICENSE` and `NOTICE`. They run only as sandboxed content in a frame and are never linked into the API,
admin or front (`demo-content/scripts/check-demo-content-boundary.mjs`, part of `yarn lint`).

| Package | Code | Provenance and third-party material |
|---|---|---|
| `poland` | MIT, © 2026 Mateusz Wojczal; text and data CC BY 4.0 | The owner's own project (`github.com/qunabu/poland-october-2026`, no licence file there), rebuilt for ulams (#148, #149). Not used: the saved third-party article and its files, the `world.topo.json` copied from that article, the mp4. Map: Natural Earth (public domain) through `world-atlas` (ISC), regenerated. Fonts: Barlow, Barlow Semi Condensed, JetBrains Mono, SIL OFL 1.1 (`fonts/OFL.txt`). Figures link to their publishers |
| `ulam/*` | MIT, © 2026 Mateusz Wojczal; text and data CC BY 4.0 | Original work: the five interactives of the Ulam course (spiral, monte-carlo, automaton, scottish-book, lwow-map) and their fact and source lists (`facts.json`, `sources.json`). Map: Natural Earth (public domain) through `world-atlas` (ISC); place coordinates: Wikidata (CC0). Third-party material is listed in `demo-content/ulam/CREDITS.md` and per package |
| `gravity` | MIT, © 2026 Mateusz Wojczal | The owner's own simulator (`github.com/qunabu/Gravity`, originally GPL-3.0), relicensed by its copyright holder (#147). Left out: commits bc9d770 and 9db0edc (David Frankel, Docker files), commit 4adaa1b (jin, the Chinese translation), the music track and the Moon photograph (no stated licence). Earth day map: Solar System Scope, CC BY 4.0. Three.js: MIT. Inter and Roboto Mono: SIL OFL 1.1 (via `@fontsource`), licence texts ship in the package |

### The Ulam course: photographs

The Ulam course (`api/database/seeds/Demo/content/ulam/`, text CC BY 4.0) shows four photographs from Wikimedia Commons, kept in
`api/database/seeds/Demo/assets/ulam/images/` and listed with their licences in `demo-content/ulam/CREDITS.md`. They are not under
the licence of the course text:

| Photograph | Licence | Obligation |
|---|---|---|
| Ulam's Los Alamos badge photo (1940s) and his portrait (about 1945) | Los Alamos National Laboratory allows any use (Commons: PD-LosAlamos) | Credit "Los Alamos National Laboratory" and reproduce the laboratory's notice (in the last lesson and in `CREDITS.md`) |
| The FERMIAC in the Bradbury Science Museum (Mark Pellegrini, 2009) | CC BY-SA 1.0 | Credit, link the licence, say it was resized, keep the share-alike licence |
| The building at 27 Shevchenko Avenue, Lviv (Rbrechko, 2015) | CC BY-SA 4.0 | The same |

Photographs of Polish-law public domain, "Ulam holding the FERMIAC" and photographs of Scottish Book pages are not used (ADR 0089, fact sheet
section 10).

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
