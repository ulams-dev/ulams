# 0098. Present the policy lifecycle as one animated example workflow

- Status: Proposed
- Date: 2026-10-12
- Supersedes: ADR 0097 only for availability badges within the policy story

## Context and problem statement

The product owner requested one coherent story connecting a CEO's policy PDF to
departmental training, owner oversight and renewal after a policy change. Separate
source-diff demonstrations repeated the explanation. Availability badges interrupted
the narrative; the owner requested their removal and explicit roadmap tracking.

## Decision

- Use seven chapters in the Overview hero, with progressive animations within each chapter.
  The course brief is a sequential chat with typing indicators and alternating ULAMS/CEO messages.
- Show the existing extraction and cited-fragment context flow. Do not imply that a vector
  retrieval service exists. The LLM reads the source before asking the course-brief questions.
- Label the whole story as an illustrative workflow, without availability badges. Track
  departmental variants, owner reporting and policy-triggered recertification as Coming in
  the roadmap and TODO. Keep source-change approval by the owner explicit.
- Use role illustrations for example people and the owner's exact Stitch pencil portrait
  in an editorial brand story replacing the repeated principles grid. Source the Scottish Book
  account from Ulam's preface, distinguish historical context from the product's inspiration,
  and invite open-source contribution. Preserve the original asset and record its provenance.
- Keep keyboard selection, shared play/pause, replay, reduced motion, a static text equivalent
  and a stable scene size. Keyboard navigation pauses; pointer selection plays the selected scene.

## Consequences

The narrative includes planned capabilities and is an example rather than a current capability
inventory. Comparison data and other feature statuses remain governed by ADR 0097.

## Alternatives considered

- Keep both stories: duplicates the main product explanation.
- Call the source ingestion vector RAG: misrepresents the current implementation.
