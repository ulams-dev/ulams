# 0009. LLM layer: an `ai` package on the official Anthropic SDK

- Status: Accepted (2026-10-09)
- Date: 2026-10-08

## Context and problem statement

Phase 2 (AI Course Builder) and later phases (tutor, remediations, update proposals) call a large
language model. The spec requires: models configured per task with no names in code, structured
outputs validated against JSON Schema with a retry, prompt caching for source material, per-call
logging of model, tokens, cost and latency, hard limits, versioned prompt files, a mocked model in
tests and real-model runs only through an eval command. The API has no LLM code today. The provider
for Phases 2–7 is Anthropic Claude; Phase 8.2 adds OpenAI-compatible endpoints and local models.

## Considered options

1. A first-party `ai` package with a small `LlmClient` contract, an Anthropic driver on the official
   `anthropic-ai/sdk`, and a fake driver that replays recorded responses.
2. A generic multi-provider PHP library (e.g. Prism) from the start.
3. Raw HTTP calls to the Messages API from the course-builder package.

## Decision

Option 1. `api/packages/ai` (`Ulams\Ai`):

- `LlmClient::generate(LlmRequest): LlmResult`; drivers `anthropic`, `fake`, `disabled`.
- Profiles in `config/ai.php` from env: `default` = `claude-sonnet-5-5`, `light` = `claude-haiku-5-5`,
  `premium` = `claude-opus-5-5` (opt-in). Each task maps to a profile, an effort level and an output
  limit. Prices live in the same config. A CI guard rejects model names elsewhere.
- API key from `ANTHROPIC_API_KEY`, falling back to the legacy `ANTROPHIC_API_KEY`.
- Every generation step uses structured outputs (`output_config.format`); the model gets no tools.
  Results are validated with `opis/json-schema` plus task-specific semantic checks; one repair attempt
  with the errors, then a failed step the author can retry. `refusal` and `max_tokens` stop reasons are
  failures, never partial content. Server-side refusal fallbacks are enabled on the default profile.
- Requests are streamed; adaptive thinking with effort per task.
- Prompt caching: frozen system prompt, then the source block with a 1-hour cache breakpoint, then the
  brief and outline, then the per-element instruction.
- Every call is written to `ai_calls` (tenant database) with model requested and served, input,
  output, cache-write and cache-read tokens, cost in micro-USD, latency, stop reason, prompt id and
  version, and subject. Budgets per session and per tenant are checked before each call.
- Prompts are versioned Markdown files owned by the package that uses them; the version is logged.
- Tests use the fake driver with cassettes keyed by task, prompt version, schema and normalised input;
  resolving the Anthropic driver in the testing environment throws. `ai:eval` (and package-specific
  eval commands) run the real model and can record cassettes.

## Consequences

- Good: one place for logging, cost, limits and validation; the official SDK tracks API changes
  (structured outputs, cache usage fields, fallbacks, typed errors).
- Good: deterministic tests with no network; prompt changes without new cassettes fail loudly.
- Good: Claude's native citations are not used because they cannot be combined with structured
  outputs; citations are our own fragment IDs, validated server-side, which also serve Phase 3.
- Bad: a second provider (Phase 8.2) needs its own driver and must map structured outputs and caching;
  the contract is kept provider-neutral to make that possible.
- Bad: model prices are maintained by hand in config.
