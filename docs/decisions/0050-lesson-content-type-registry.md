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

## Implementation notes (M2.3)

- **Interactive.** A fourth type, `interactive`, reuses the Interactive topic type (ADR 0086): the builder
  chooses a package from the library, its start and end step and writes the cited text beside it. It never
  creates or edits a package.
- **Who picks the format.** The outline task is unchanged (so recorded outline cassettes stay valid). Lessons
  are rich text; the author chooses a format per lesson when approving the outline (`approve_outline.formats`).
  `COURSE_BUILDER_AUTO_FORMATS` gives lessons with two or more objectives LiaScript by rule.
- **Shape.** H5P and interactive lessons are the rich text topic followed by an activity topic
  (`interaction_topic` in the entity map); a LiaScript lesson is one LiaScript topic. A format change replaces the topic.
- **Safety.** LiaScript code macros and `import:` lines never come from model output.
- **Tasks.** `selfcheck`, `interaction_h5p`, `interaction_interactive`, in a new generation stage `interactions`.
