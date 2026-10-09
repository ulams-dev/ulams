# 0017. One upload guard and safe extractor for every upload path

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

SCORM, cmi5, course import and the file manager each handled uploads on their own. SCORM and cmi5
used `ZipArchive::extractTo` without entry checks (zip-slip), course import read files outside the
archive through paths in `content.json`, and SVG/HTML uploads were served inline from the storage
origin. Phase 1 decisions 2 and 14–19.

## Decision

- A package `api/packages/uploads` with an **upload guard** (size, MIME and extension per upload
  kind, optional virus scan) and a **safe extractor** (entry by entry: no absolute or `..` paths, no
  symlinks, limits on entry count, total size and compression ratio) used by every path that accepts
  archives: SCORM, cmi5, course import, LiaScript and the Adapt build import.
- Virus scanning through an optional clamd service (compose profile `av`), off by default, failing
  closed when enabled and unreachable.
- Active content (SVG, HTML, XML, unknown types) is stored with `Content-Disposition: attachment`
  and an extension-based `Content-Type`; the storage origin adds `script-src 'none'; sandbox` for SVG.
  No sanitiser.
- Course import resolves every path from `content.json` inside the extracted archive
  (`ImportPath`); nested archives are allowed and checked when imported themselves.

## Consequences

- One place to tighten limits; every new upload path must use the guard.
- Old course exports with absolute entry names still import (a leading `/` is stripped for course
  imports only).
