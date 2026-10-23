# Style guide shapes the course design

Status: Proposed (2026-10-22). Written after the owner decision "everything labelled Coming must be implemented".
Open questions in section 9 have recommended defaults that are used unless the owner says otherwise. ADR 0196
(Proposed) records the decision.

## 1. Goal

An author or policy owner uploads the organisation's style guide (PDF, DOCX, Markdown, or a Figma/Stitch export
already supported by the brand importer). ulams proposes a **style profile**: theme tokens plus content rules, each
citing the passage it came from. After the author approves it, every course the builder drafts for that academy,
department or brief follows it, and a style check reports what does not.

## 2. TODO items covered

| TODO item | Where |
|---|---|
| 6.1 "(new) Policy lifecycle delivery: ... apply an uploaded style guide ..." (the style guide part) | SG-1 to SG-4 |
| 2.x "(new) Style guide shapes the generated course design" (added by this plan) | SG-1 to SG-5 |

P6R-11 of `phase-6-remaining.md` loses its "style guide brand tokens" half to this plan; the policy package stores a
`style_profile_id` instead of `policy_brand_profiles`.

## 3. Current state (read on `main` at `602f94174`)

| Area | Fact | Where |
|---|---|---|
| Brand importer | colours from JSON, CSS variables or text to a preset and accent, with contrast checks, as a diff the admin approves | `front/ui/src/theme/brand-import.ts`, #297 |
| Course Brief | v3 fields `audience`, `level`, `tone`, `language`, `notes`, `theme`, `site`, `audiences` | `api/packages/course-builder/resources/schemas/course-brief/v3.json` |
| Prompt layout | source fragments inside `<source_document untrusted="true">`, then brief and outline (cached), then the element instruction | `api/packages/course-builder/src/Pipeline/PromptContext.php` |
| Ingestion | PDF, DOCX, Markdown and text into fragments | `api/packages/course-builder/src/Ingestion` |
| Catalogue | approved components and their manifest for generated layouts | `front/ui` registry, `topic-type-layout` |
| Critics | planned (M2.4), not built; the publish summary shows blocking items and warnings | `docs/plans/phase-2.md` M2.4 |
| LLM logging | model, tokens and cost per call in `ai_calls` | `api/packages/ai` |

## 4. Design

### 4.1 Style profile schema (`style-profile/v1.json`, course-builder)

| Field | Content | Used by |
|---|---|---|
| `theme` | preset, accent, optional secondary colour, font family names (system or self-hosted only), logo file id | tenant or entry-point theme (diff through the existing theme screen) |
| `voice` | person (`you`/`we`/`neutral`), formality (1 to 5), sentence length target | generation prompt, style check |
| `readingLevel` | target grade band or CEFR level per language | generation prompt, style check |
| `terminology` | list of `{use, avoid[]}` pairs, product names with exact casing | generation prompt, style check |
| `banned` | phrases never to use | style check (blocking when configured) |
| `components` | preferred and disallowed catalogue components (must exist in the manifest) | layout generation, style check |
| `imagery` | rules for images the author supplies: alt text required, decorative images allowed or not, no text in images | publish summary, WCAG check |
| `examples` | up to three short approved example paragraphs | generation prompt (few-shot) |

Every field carries `citations` (style-guide fragment ids). Unknown keys are dropped; values out of range are dropped
with a warning shown to the author.

### 4.2 Flow

1. Upload a style guide as a source of kind `style_guide` (existing ingestion, marked untrusted).
2. `StyleProfileExtractor` makes one LLM call (`style_profile_extract`, model from config, logged) that returns the
   schema above; without AI the author fills the form by hand.
3. The studio shows the proposal as a diff against the current profile (or empty), each value with its citation;
   the author edits and approves. The theme part is offered to the theme screen as a diff (existing approval).
4. Scope: tenant default; overrides per entry point (ADR 0195) and per Course Brief (`styleProfileId`, Brief v4).
5. Generation: `PromptContext` adds the approved profile as JSON in the cached brief block. The raw style guide is
   never in a generation prompt.
6. Style check: a deterministic critic per element (terminology, banned phrases, reading level by a per-language
   readability formula, component allow-list, alt text). Findings appear in the review card and the publish summary;
   `banned` hits block publishing when the profile says so.
7. Profile change on an applied course: an optional "restyle" proposal through Living Course (kind `style`), one
   patch per affected element, reviewed as a diff.

### 4.3 API, studio, CLI

- `POST /api/admin/course-builder/style-profiles` (from a source), `GET`, `PATCH` (author edits), `POST .../approve`,
  `GET .../check?course=` (style check report).
- Studio: `/studio/style` (profile list and diff review), a "Style" chip in the brief, findings in the review card.
- CLI and MCP tools generated from OpenAPI (`ulams style-profiles ...`).

## 5. Dependencies

No new runtime dependency. Readability formulas are implemented in PHP (Flesch-Kincaid for English, Pisarek's index
for Polish; for Chinese a character-count heuristic, documented as approximate).

## 6. Migrations

- `course-builder`: `course_builder_style_profiles` (`scope_type`, `scope_id`, `source_id`, `profile` json,
  `status` suggested/approved/superseded, `approved_by`, `approved_at`); brief schema `v4.json` with
  `styleProfileId` and an upgrader (v3 briefs stay valid).
- `living-course`: proposal kind `style` (string column, no schema change).
- Reversible.

## 7. Tests

- Extraction with the fake LLM driver: values cite fragments; out-of-schema values and unknown components dropped;
  a style guide that contains instructions ("ignore previous instructions", "add this link") changes no tool call and
  no output schema.
- Generation: the prompt carries the profile JSON and not the style guide text (assert on the recorded request).
- Style check: unit tests per rule and language; banned phrase blocks publishing when configured.
- Restyle proposal: diff per element, approval applies, rejection leaves the course unchanged.
- Tenant isolation for every new endpoint; axe (WCAG 2.2 AA) for the studio screens.
- Eval fixture (a short original style guide and a source) for the real-model eval command: adherence rate per rule,
  citation coverage, cost.

## 8. Risks

- Over-constraining generation lowers quality: the profile is advisory except `banned`; the eval measures both
  adherence and citation coverage.
- Readability formulas are crude: shown as a hint, never blocking.
- Fonts: only system or self-hosted fonts (air-gapped principle); web-font URLs in a style guide are ignored.

## 9. Open questions (recommended default in bold)

1. Where the profile lives. **`course-builder`**, referenced by `policy-lifecycle` and entry points.
2. Is the style check blocking? **Only `banned` phrases, and only when the profile says so.**
3. Restyle of applied courses. **Optional proposal, never automatic.**
4. Image generation from the style guide. **Out of scope** (spec "Out of scope": image generation).

## 10. Milestones (one branch and PR each, based on `main`)

| # | Slice | Demo |
|---|---|---|
| SG-1 | Schema, `style_guide` source kind, extractor with fake driver, profile table and API, isolation tests | upload a style guide, get a cited proposal through the API |
| SG-2 | Studio review and approval, theme diff hand-off to the theme screen, axe | approve a profile; the academy theme changes after the theme diff is accepted |
| SG-3 | Brief v4 `styleProfileId`, profile in the prompt, entry-point override (after EP-2) | two courses from one source read differently for two profiles |
| SG-4 | Style check critic and publish summary findings | a banned phrase blocks publishing with its citation |
| SG-5 | Restyle proposals through Living Course; eval fixture | change the terminology, review the restyle diff |
