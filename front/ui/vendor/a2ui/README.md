# Vendored A2UI schemas

The A2UI v0.9 message schemas (Apache-2.0) from <https://github.com/a2ui-project/a2ui>, pinned at the
commit recorded in `v0.9/NOTICE`. They are unmodified; `LICENSE` and `NOTICE` stay next to them.

What uses them: `src/builder/a2ui-validate.ts` validates every `a2ui-surface` envelope (ADR 0023)
against `server_to_client.json` in development (`import.meta.env.DEV`) and in tests. Production
builds skip the check and never load these files.

`server_to_client.json` refers to `catalog.json` for the component and theme definitions. That
catalogue is ours (`src/builder/catalogue.ts`, `catalogue/manifest.json`), so the validator checks
those references loosely (an object with string `id` and `component`) and the renderer validates
each component's props against its own schema.

To update: fetch the files at a new commit, replace them, update `v0.9/NOTICE` (URL and SHA) and
run `yarn workspace @ulams/ui test`. Licence summary: `LICENSING.md`.
