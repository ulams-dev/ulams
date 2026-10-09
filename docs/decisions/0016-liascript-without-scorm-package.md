# 0016. LiaScript: versioned Markdown documents played without a SCORM package

- Status: Accepted (2026-10-09)
- Date: 2026-10-09

## Context and problem statement

The spec (1.1) wants LiaScript courses as Markdown sources the AI phases can read and diff, with no
dependency on liascript.github.io. The plan (decision 6) proposed packaging each version as a SCORM
zip with the LiaScript exporter and playing it with the SCORM runtime. The spike (plan 5.5) showed
the LiaScript SCORM 1.2 build can load any Markdown URL, so a package per version is unnecessary.
Phase 1 decisions 33–35 and 39, plus the editor preview and course export added in M1.9.

## Decision

- **Documents are standalone and versioned** (`liascript_documents`, `liascript_versions`): every
  edit, upload or restore adds an immutable version; assets are carried over by text-only versions.
  The topic type `LiaScriptTopic` stores the document id and always plays the current version;
  documents used by topics cannot be deleted.
- **No SCORM package**: the LiaScript SCORM 1.2 build (BSD-3-Clause, fetched at image build time at a
  pinned version and SHA-256) and our player page are published to the tenant content origin
  (ADR 0014); the version's Markdown and assets are published next to each other. The player page
  provides `window.API` and reports position and status with a topic-scoped token; the topic
  completes at the last section or on `completed`/`passed`.
- **Live preview**: unsaved text is written as `preview-<128-bit random>.md` next to the current
  version (its assets resolve), played in preview mode without a token; drafts expire after an hour
  and only the newest few are kept.
- **Course export/import**: the export carries each LiaScript topic's current version
  (`topic/<id>/liascript/`); the import creates a new document through the normal upload checks.
  The version history stays with the source. Packages register import strategies with
  `ExportImportService::registerTopicStrategy()`.

## Consequences

- No exporter container or per-version build; a new version is playable at once.
- The published Markdown of a saved version is readable by anyone with its URL on the content origin
  (like SCORM package files); drafts use unguessable names.
- Remote `import:` macros cannot load (content-origin CSP) and are reported as warnings.
