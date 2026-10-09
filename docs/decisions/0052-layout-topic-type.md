# 0052. Learner layouts as a Layout topic type rendered from the catalogue

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/leftovers-0-2.md` (L2-21)

## Context and problem statement

The spec allows AI-composed lesson layouts, built from approved learning components and stored in
the blueprint (behind a flag). LMS topics are RichText Markdown today. The legacy front and exports
need a readable fallback.

## Considered options

1. A new topic type `topic-type-layout` storing the catalogue document plus a Markdown fallback.
2. Embed the layout JSON inside RichText.
3. Render layouts only in `front/web` from the blueprint.

## Decision

Option 1:

- **Model.** `LayoutTopic` has `document` (learner catalogue format), `schema_version` and
  `markdown_fallback`, with morph alias `topic.layout`.
- **Rendering.** `front/web` renders the document; other clients use the fallback.
- **Generation.** Task `layout`, behind the tenant setting `COURSE_BUILDER_LAYOUTS_ENABLED` (default
  off), uses only approved components. Every leaf cites fragments and objectives.
- **Practice activities.** These must use `PracticeActivity`, whose schema enforces the scaffolding
  template.

## Consequences

- Good: layouts are LMS content (progress, export, Living Course updates) and never markup.
- Bad: one more topic type, and two renderings to keep consistent (document and fallback).
