# 0050. Lesson content-type registry: deterministic LiaScript rendering and allow-listed H5P libraries

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/leftovers-0-2.md` (L2-11, L2-12)

## Context and problem statement

Builder lessons are hard-coded to `richtext` (blueprint enum, outline service, applier). The spec
asks for lessons as rich text, LiaScript or H5P from a component registry with JSON schemas.

## Considered options

1. A `ContentTypeRegistry`. LiaScript lessons are rendered by our code from structured blocks and
   self-checks. H5P interactions use a small schema of our own per allow-listed library, mapped
   deterministically to H5P params.
2. Let the model write LiaScript Markdown and raw H5P `params` directly.

## Decision

Option 1:

- **Blueprint v2.** Allows `contentType: richtext|liascript|h5p`, plus optional `selfChecks` and
  `interaction`. v1 documents are read as v2 without changes.
- **LiaScript.** Rendered like GIFT, by our code, and created through `LiaScriptServiceContract`.
- **H5P libraries.** The allow-list is `H5P.Blanks`, `H5P.DragText` and `H5P.Dialogcards`, checked
  against the platform's installed libraries.
- **H5P creation.** Content is created through `H5PServiceClient::create()` with the internal token.
- **Grounding.** Every interaction cites fragments and passes the quiz support check.

## Consequences

- Good: no model-written markup, and output can be validated and diffed.
- Good: adding a library means adding one schema and one mapper.
- Bad: only a few H5P types at first; complex libraries need their own mapper.
