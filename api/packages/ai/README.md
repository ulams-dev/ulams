# ai: the LLM layer

Provider-agnostic access to large language models for every ulams package (ADR 0009). It knows
nothing about courses: callers build an `LlmRequest` (task, prompt, content blocks, output JSON
Schema, subject) and get back validated JSON, with every call logged and priced.

```php
use Ulams\Ai\Contracts\LlmClient;
use Ulams\Ai\Dto\{ContentBlock, LlmRequest};

$result = app(LlmClient::class)->generate(new LlmRequest(
    task: 'outline',                                         // → profile, effort, max tokens in config
    prompt: app(PromptRegistry::class)->get('course-builder', 'outline'),
    blocks: [ContentBlock::text($sourceDocument, cache: true), ContentBlock::text($instruction)],
    schema: $outlineSchema,                                  // JSON Schema of the answer
    subject: ['type' => 'course_builder_session', 'id' => $session->id],
    validator: fn (array $data) => $semanticErrors,          // optional, e.g. citations resolve
));
$result->data;          // validated array
$result->costMicroUsd;  // integer micro-USD
```

## What it does

| Concern | Where |
|---|---|
| Models and profiles (`default`, `light`, opt-in `premium`), task → profile/effort/max tokens, prices, limits | [`config/ai.php`](config/ai.php), all from env |
| Driver selection: `anthropic`, `fake`, `disabled` (no key ⇒ disabled) | `UlamsAiServiceProvider::driverName()` |
| Anthropic driver: official `anthropic-ai/sdk`, streamed, structured outputs (`output_config.format`), effort, no tools, cache breakpoints, server-side refusal fallback | `src/Drivers/AnthropicDriver.php` |
| Validation: JSON Schema (opis, draft 2020-12) + semantic validator, one repair attempt, then a failed step | `src/Services/AiClient.php` |
| `refusal` and `max_tokens` stop reasons are failures, never partial content | same |
| Usage log `ai_calls` (tenant database): tokens, cache tokens, cost (micro-USD), latency, prompt id and version, model requested and served, subject | `src/Models/AiCall.php`, `php artisan ai:usage` |
| Budgets per subject (tokens, USD) and per tenant per month, checked before every call | `src/Services/BudgetGuard.php` |
| Versioned prompt files | `src/Prompts/PromptRegistry.php` |
| Fake driver with cassettes; synthetic stand-ins for local demos | `src/Drivers/FakeDriver.php`, `src/Fake/` |

No model id appears outside `config/ai.php` and `.env.example`; `ModelNamesGuardTest` fails on a
`claude-…` string in any package's `src/` or `resources/prompts/`. The UI shows the profile's label
(`AI_MODEL_DEFAULT_LABEL`), not the id.

## Prompt caching

Requests are laid out stable-first: the frozen system prompt (cached), then the content blocks the
caller marks cacheable (for the course builder: the source document, then brief and outline), then
the per-element instruction (uncached). Each marked block gets a breakpoint with `AI_CACHE_TTL`
(default `1h`, because authors pause between steps); at most four breakpoints per request. Cache
writes are priced at 1.25× (5 min) or 2× (1 h) the input price, reads at the `cache_read` price.

## Cost

`cost_micro_usd = input × p_in + output × p_out + cache_read × p_read + write_5m × p_in × 1.25 +
write_1h × p_in × 2`, with prices in USD per million tokens from `ai.prices` (= micro-USD per token)
and the `long_context` tier when the input side of the call exceeds its threshold. The model that
actually answered (`model_served`, which differs from the requested one after a refusal fallback) is
the one priced. Costs are stored at call time and never recomputed.

## Tests and cassettes

Unit and feature tests never reach the network: resolving the Anthropic driver in the `testing`
environment throws. Tests use the fake driver:

- `FakeDriver::queueJson($task, $data)` for direct unit tests;
- **cassettes** at `<AI_CASSETTES_PATH>/<task>/v<prompt version>/<hash>.json`. The hash covers the task,
  the prompt version, the output schema and the request content *normalised*: fragment ids (`frg_…`)
  and ULIDs are replaced by ordinal placeholders, so a recording replays on a fresh database. A
  prompt or schema change without new cassettes fails with `missing_cassette` and the expected path;
- `AI_FAKE_MODE=synthetic` (default outside tests): when no cassette matches, a responder registered
  by the owning package (`FakeResponders::register($task, fn)`) builds a deterministic answer. This
  is what local demos without an API key and the end-to-end test run on.

Record cassettes from real runs with `AI_RECORD=true AI_RECORD_PATH=<dir>` (the course builder's
`course-builder:eval --record` sets both).

```bash
vendor/bin/phpunit --testsuite ai
```
