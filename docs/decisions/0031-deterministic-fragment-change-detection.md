# 0031. Fragment-level change detection is deterministic

- Status: Accepted (2026-10-09)
- Date: 2026-10-09

## Context and problem statement

Living Course needs a fragment-level diff (added / changed / removed) between two revisions of a
source, and from it the course elements to update. Phase 2 fragment IDs depend on the heading path
and the chunk ordinal inside it (ADR 0010), so a typo fix keeps the ID, but inserting a paragraph can
shift later chunks of the same section, and renaming a heading changes every ID below it. The diff
must be cheap (it runs on every check), explainable and testable, and must work without AI.

## Considered options

1. Deterministic alignment: IDs and hashes first, then content similarity (word 3-shingle Jaccard)
   for moves and shifted chunks, plus rule-based magnitude classes.
2. Compare by fragment ID only.
3. Ask a model to compare the two documents.

## Decision

Option 1, in `FragmentDiff`:

1. Identical normalised documents → unchanged revision.
2. Same ID and same hash → unchanged; same normalised hash → trivial change.
3. Identical text under a different ID → moved (citations are remapped without AI).
4. Same ID, different text, similarity ≥ 0.5 → changed; otherwise both sides stay unmatched.
5. Unmatched pairs with similarity ≥ 0.6 (candidates from a shingle index, preferring the same file
   and heading prefix) → moved with change.
6. The rest → removed / added.

Magnitude: `trivial` (normalised equal), `substantive` (a changed number, code token, identifier,
negation/modality word, or more than 15 % of tokens changed), else `minor`. Signals are stored and
shown. Impact analysis walks the blueprint citations: trivial changes create no work, moves create
citation remaps, minor/substantive changes and removals mark citing elements as impacted, and
questions citing substantive or removed fragments are flagged "answer may be wrong".

## Consequences

- Good: no cost and no network for detection; same input, same output; fixtures cover each case.
- Good: works with AI disabled; the author still sees what changed and which elements are affected.
- Bad: thresholds are heuristics; wrong pairings show up as a removed + added pair instead of a
  change, which is still safe (the element is impacted either way).
- Bad: semantic changes with no lexical signal (a sentence that now means the opposite through
  rephrasing) are classified by token ratio only; the model still sees every impacted element.
