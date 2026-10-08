# 0015. GIFT quizzes edited through a PEG.js parser, maintained as `@escolalms/gift-pegjs`

- Status: Accepted (retroactive)
- Date: 2023-04-17

## Context and Problem Statement

The API's quiz package stores questions in Moodle's GIFT text format. Authors should not have to write
GIFT by hand, so the admin needed a WYSIWYG question editor that can parse existing GIFT and
generate it again.

## Considered Options

- Upstream `gift-pegjs` (fuhrmanator/GIFT-grammar-PEG.js) from npm (April 2023, PR #743).
- An own fork published as `@escolalms/gift-pegjs` (chosen two weeks later, PR #780).

## Decision Outcome

`src/components/GiftQuizQuestions/editor/` has one form per GIFT question type
(`question/MultipleChoice.tsx`, `TrueFalse.tsx`, `ShortAnswers.tsx`, `Matching.tsx`, `Numerical.tsx`,
`Essay.tsx`); `editor/utils.ts` converts GIFT to form data (`parse()` → `parseToFormData`) and back
(`parseToGIFT`). The parser is the EscolaLMS fork of GIFT-grammar-PEG.js. The fork's only
grammar change stops trimming text (with an opt-in `noTrim` option on the trim call) so whitespace and
newlines in question text survive a parse/edit cycle; the rest of the fork adds a GitHub Actions
generate/release workflow and npm publishing.

Inferred: the fork was needed because trimming in upstream broke round-tripping in the new editor; the
commit messages ("main", "release script") do not say.

### Consequences

- Good: editors work on structured data while the API keeps a portable, Moodle-compatible format.
- Bad: one more fork to maintain, behind upstream; it is being vendored into the monorepo.

## Evidence

- `0f067f56` 2023-04-03 "Feature/gift wysiwyg (#743)" — `admin/src/components/GiftQuizQuestions/editor/*`,
  `table.tsx`, `admin/package.json` (`gift-pegjs ^0.2.1`).
- `eefd547a` 2023-04-17 "quiz fixes (#780)" — `admin/package.json` (→ `@escolalms/gift-pegjs ^0.2.8`),
  `GiftQuizQuestions/editor/edit_question.tsx`.
- `f3922368` 2023-08-16 "ExportQuizQuestionsModal" — GIFT export uses the same parser.
- GIFT-grammar-PEG.js repo: `4c754f1` 2017-01-25 "Initial commit" (upstream, C. Fuhrman); `ba865a8`
  2023-04-17 "main" (`GIFT.pegjs`: drop `.trim()`, package renamed); `f374749` 2023-04-17 "main"
  (`.github/workflows/generate.yaml`); `b6808df` 2023-04-17 "0.2.8".
