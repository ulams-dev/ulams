# 0060. AI tutor: course-scoped full-text retrieval, citations and an attempt guard

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/phase-4.md` (section 8)

## Context and problem statement

The spec asks for a per-course tutor that:

- answers only from course content and its sources, with citations;
- says when a question is out of scope;
- never gives quiz answers during an active attempt;
- has rate and cost limits;
- appears in analytics anonymised by default.

## Considered options

1. A `tutor` package with PostgreSQL full-text search over course chunks (quiz content never
   indexed), a structured answer with chunk citations, an attempt guard and a deterministic
   answer-leak check.
2. Embeddings with `pgvector` and an embedding provider.
3. Send the whole course in the prompt.

## Decision

Option 1:

- **Index.** Chunks come from blueprint blocks and fragments (builder courses) or from RichText and
  LiaScript (other courses). They are rebuilt on publish and apply.
- **Retrieval.** Top 8 chunks plus the current topic.
- **Profile.** The light model.
- **Output.** `{answer, citations, in_scope, refusal}`.
- **Attempt guard.** During an attempt, answers that overlap a correct option are replaced with a
  refusal.
- **Conversations.** Owned by the learner, deletable, kept 90 days.
- **Analytics.** Aggregates only, with `ai_calls.user_id` null.
- **Streaming.** No streaming in Phase 4.

## Consequences

- Good: no extension or embedding provider, which suits self-hosters and air-gapped installs.
- Good: the tutor cannot leak quiz answers from its index.
- Bad: lexical retrieval misses paraphrases; to revisit with Phase 5.3 semantic search.
- Defaults pending #61 and #63.
