# 0196. A cited, author-approved style profile shapes the theme and the generated course design

- Status: Proposed
- Date: 2026-10-22
- Plan: `docs/plans/style-guide-course-design.md`; related ADR 0140 (policy lifecycle, `policy_brand_profiles`),
  ADR 0195 (entry points)

## Context and problem statement

The landing promises that an organisation's style guide shapes the course design. Today the brand importer
(`front/ui/src/theme/brand-import.ts`) turns pasted colours into a tenant theme proposal, and the Course Brief has a
free-text `tone` and a `theme` field. Nothing reads a style guide document, and nothing makes generated lessons follow
its terminology, voice, reading level, component preferences or image rules. A style guide is an uploaded document,
so it is untrusted input (prompt injection).

## Considered options

1. **Style profile**: one LLM call extracts a typed profile (theme tokens, voice, terminology, banned phrases, reading
   level, component preferences from the approved catalogue, image and accessibility rules) from the style guide;
   every field cites the style-guide fragment it comes from; the author approves it as a diff; generation receives
   the approved profile as structured data, never the raw style guide text.
2. Pass the style guide as a second source document to every generation call.
3. Theme tokens only (the brand importer), no influence on content.

## Decision

Option 1. The profile lives in `course-builder` (`course_builder_style_profiles`), at tenant level with optional
overrides per entry point and per Course Brief. The prompt carries the approved profile as JSON inside the cached
brief block; the raw style guide never reaches a generation prompt. A deterministic style check (terminology,
banned phrases, reading level, component allow-list) runs as a critic on every generated element and reports
findings in the publish summary; it does not rewrite content by itself. Changing an approved profile can propose a
"restyle" update through Living Course, reviewed as a diff like any other change. The policy package's
`policy_brand_profiles` (ADR 0140) is replaced by a reference to a style profile.

## Consequences

- Good: the style guide influences every course through reviewable, cited, typed data; injection in the style guide
  can only produce out-of-schema values, which validation drops.
- Good: one profile drives the theme (tenant or entry point) and the content, so the two do not drift.
- Bad: one more LLM call per style guide (logged with model, tokens and cost) and one more critic per element.
- Bad: style rules that do not fit the schema (for example layout grids) are out of scope; the schema grows only by
  ADR amendment.

## Alternatives considered

- Option 2 puts untrusted text next to the instructions on every call, costs tokens on every element and makes
  adherence unverifiable.
- Option 3 does not deliver what the landing promises.
