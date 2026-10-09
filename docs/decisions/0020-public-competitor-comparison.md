# 0020. Public comparison with other learning platforms: sourced data, neutral values

- Status: Accepted (2026-10-09)
- Date: 2026-10-09

## Context and problem statement

The product owner wants the platform landing (`app.localhost`) to compare ulams with the systems
buyers weigh it against. A comparison table is a public claim about other companies' products: it
must be accurate, checkable and fair, and must not misuse trademarks.

## Decision

- The table compares ulams with Moodle, Canvas LMS (open source), Open edX, TalentLMS (hosted SaaS
  LMS), Thinkific (course-creator platform) and LearnDash (WordPress plugin) on 15 product rows.
- The data lives in `front/web/src/data/comparison.json`; every cell is
  `{value, note, source, checkedAt}`. Competitor cells are researched only from official sources:
  vendor documentation, pricing pages, licence files and official plugin directories.
- Values are neutral: Yes, No, Partial, Via plugin, Paid add-on, Not documented. "No" only when an
  official source confirms the absence; otherwise "Not documented". Licence and pricing are short
  factual phrases. Notes are short and descriptive, no marketing wording about competitors.
- ulams cells are honest: built features say Yes or Partial, roadmap items say Coming (Living Course,
  AI course generation, cited diffs, adaptive paths, generative UI), with sources in our repository.
- The page shows "As of <month year>" and a Sources disclosure listing every URL with its check date.
  A unit test fails when a cell has no https source or date, or when a competitor cell says Coming.
- Plain product names only, no logos; a line says the names are trademarks of their owners.
- Rendered by a catalogue component, `ComparisonTable` (no JavaScript).

## Consequences

- Good: every claim can be traced and re-checked; corrections are a data change.
- Bad: the facts age. Re-check the sources before each release or at least quarterly and update
  `checkedAt` and `asOf`.
