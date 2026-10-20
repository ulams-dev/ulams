# 0104. Machine-readable documentation: llms.txt, Markdown pages and public JSON Schemas

- Status: Proposed
- Date: 2026-10-20
- Plan: spec `docs/ROADMAP-PROMPT.md` 7.6

## Context and problem statement

Agents and code generators read the documentation without a browser. Starlight renders HTML only; agents
need an index they can fetch (`llms.txt`), the Markdown source of each page, and schemas with stable
addresses for the blueprint, webhook events and UI catalogue, which today are files inside the repository.

## Considered options

1. A post-build script in the docs site that writes `llms.txt`, `llms-full.txt`, `<page>/index.md` and
   `schemas/**` into `dist/`.
2. An Astro integration or a dependency (for example `starlight-llms-txt`).
3. Serve the repository's raw files from GitHub.

## Decision

Option 1: `front/docs-site/scripts/agent-files.mjs`, run after `astro build`, and in check mode in `lint`.
It reads `src/content/docs` (including the generated pages), flattens the Starlight components to Markdown,
makes internal links absolute and points them at the Markdown pages, and copies the schemas from the files that
tests validate against. No new dependency (it uses `yaml`, already a dev dependency). Generated, large trees
(reference, ADRs, component catalogue) go under "Optional" in `llms.txt` and stay out of `llms-full.txt`.
A schema is never edited in place: a breaking change gets a new file and `$id`.

## Consequences

- Good: always in step with the pages; nothing to host separately; works under the Pages base path.
- Good: the schemas have one canonical public address each, listed in `schemas/index.json`.
- Bad: a new component needs a line in the converter (the check fails on a component it leaves behind).
- Per academy, the learner web app serves `/llms.txt` and `/llms/courses/<id>.md` from the public API data (`front/web/src/lib/llms.ts`); lesson content is not exposed.
