# 0151. Search: full text and model query rewrite first, optional pgvector embeddings

- Status: Proposed
- Date: 2026-10-21
- Plan: `docs/plans/phase-5-remaining.md` 4.5 (milestones P5R-6 to P5R-8; open questions Q5 to Q8, Q17)
- TODO: Phase 5 "Semantic search with cited AI answers"

## Context and problem statement

Learners should search every course they can open and get an AI answer that cites course content, reusing the
tutor's grounding (ADR 0060, 0105). The tutor already indexes course content in `tutor_chunks` (quiz content never
indexed) and retrieves with PostgreSQL full-text search; ADR 0060 noted that lexical retrieval misses paraphrases and
deferred embeddings to this item. Constraints:

- The LLM layer has no embeddings API (the Anthropic provider offers none).
- Dev and CI run PostgreSQL 12; production references run 17.11 and ADR 0040 moves everything to 17.
- pgvector 0.8 supports PostgreSQL 13 and later only, and the official images do not include it.
- Shared hosting (ADR 0091) and air-gapped installs (principle 8) cannot be assumed to have extensions or an
  embeddings service.
- PostgreSQL has no Chinese text-search parser in the official image, and no Polish configuration.

## Considered options

1. Embeddings with pgvector as the only retriever.
2. A separate search engine (Meilisearch, OpenSearch).
3. Full-text search over the tutor index as the default, a light-model query rewrite when AI is on, and embeddings as
   an optional second retriever merged by reciprocal rank fusion.

## Decision

Option 3.

- A new `search` package reuses `tutor_chunks` and adds `search_course_docs` for course-level results. Indexing is
  queued on publish, update, builder apply and Living Course apply.
- CJK text is split into character bigrams before `to_tsvector('simple', ...)`, for search and for the tutor.
  Polish keeps `simple`.
- Access is filtered in SQL by a new `AccessibleCourses` domain service that mirrors `CoursesPolicy::attend`.
- Answers go through the tutor pipeline unchanged:
  - citations are a subset of the provided chunks;
  - an in-scope answer needs a citation;
  - uploaded content is treated as untrusted;
  - the attempt guard covers every course with an active attempt;
  - the quota is shared with the tutor, and every call is logged with model, tokens and cost.
- With AI off, search returns results without an answer.
- Embeddings use an `EmbeddingClient` with an OpenAI-compatible driver (Ollama, vLLM, LiteLLM, hosted). They are
  stored in `search_embeddings` with an HNSW index only when the `vector` extension can be created. They are off by
  default, and the `search:eval` numbers decide whether operators are advised to turn them on.

## Consequences

- Good: works on every supported host with no extension, service or provider; no new runtime dependency.
- Good: one index and one set of guard rails for the tutor and search.
- Bad: paraphrase recall without embeddings depends on the query rewrite, which costs one light-model call per
  question when AI is on.
- Bad: the hybrid path is untestable on the PostgreSQL 12 dev stack; it gets its own CI job on a pgvector image until
  ADR 0040 lands.
