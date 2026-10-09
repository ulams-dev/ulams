# 0024. Fake LLM driver: normalised cassettes and synthetic answers

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

ADR 0009 says tests use a fake driver with cassettes keyed by task, prompt version, schema and the
normalised input. Builder requests contain generated ids (fragment ids derived from the source id,
ULIDs of blueprint elements), so a recording would never match a fresh database. The end-to-end test
and local demos without an API key also need answers for any uploaded document.

## Decision

- Normalisation replaces every `frg_…` id and ULID in the request with ordinal placeholders
  (`{{frg_1}}`, `{{id_3}}`) in order of first appearance. The cassette stores the response with the
  same placeholders; replay maps them back to the ids of the current request. The hash covers the
  task, prompt version, canonical output schema and the normalised content; the path is
  `<task>/v<prompt version>/<hash>.json`. Repair turns are part of the key, so a recorded repair
  replays too.
- `AI_FAKE_MODE=cassette` replays only (missing cassette = failure with the expected path);
  `synthetic` (the default outside tests) falls back to deterministic responders registered by the
  owning package (`FakeResponders`), which build schema-valid answers from the request's own
  fragments. The course builder's responders never repeat markup from the source.
- `course-builder:eval --live --record` records cassettes from real runs; the replay test runs the
  recorded coffee run in strict mode.

## Consequences

- Good: real-model outputs replay on any database; a prompt or schema change without new cassettes
  fails loudly.
- Good: demos and the e2e test run with no key and no network.
- Bad: synthetic answers are not a quality signal; only the eval with `--live` measures quality.
