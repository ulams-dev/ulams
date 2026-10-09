# Course Builder: developer notes

Records: ADR 0009 (LLM layer), 0010 (Course Blueprint), 0011 (AG-UI over SSE), 0022–0029.
Plan: `docs/plans/phase-2.md`.

```
front/web /studio (Astro SSR + vanilla islands)          api (Laravel, tenant host)
  @ulams/ui builder catalogue + A2UI renderer  ── BFF ──▶  course-builder package
  @ulams/sdk courseBuilder client + SSE reader  /studio/api   sessions, sources, runs, versions, SSE
                                                               jobs: RunJob, StepJob
                                                               applier ──▶ courses, topics, GIFT, pages services
                                                             ai package
                                                               LlmClient ─▶ anthropic | fake | disabled
```

## The `ai` package (`api/packages/ai`)

- `LlmClient::generate(LlmRequest)`: task → profile/effort/max tokens from `config/ai.php`, budget
  check, driver call, JSON Schema validation (opis, 2020-12) plus the caller's semantic validator,
  one repair attempt, an `ai_calls` row per attempt with cost. `refusal` and `max_tokens` are
  failures, never partial content. Errors are `LlmException` with a machine `reason`.
- `AnthropicDriver`: `anthropic-ai/sdk`, streamed (`beta.messages.createStream` + accumulator),
  `output_config` with `effort` and the JSON Schema (unsupported keywords stripped by
  `JsonSchemaValidator::forProvider`), cache breakpoints on the system prompt and every block marked
  `cache: true` (max 4), server-side refusal fallback on profiles that enable it. It refuses to be
  constructed in the `testing` environment.
- `FakeDriver`: queued responses (unit tests), cassettes, synthetic responders (ADR 0024).
- `PromptRegistry`: `<dir>/<task>/v<N>.md` with front matter; packages register their directory.
- `CostCalculator`: config prices (USD per million tokens), cache write ×1.25/×2, long-context tier.
- Guard: `ModelNamesGuardTest` fails on `claude-…` in any package `src/` or `resources/prompts/`.

## The `course-builder` package (`api/packages/course-builder`)

| Area | Where |
|---|---|
| Ingestion: MD, PDF, DOCX → fragments with ids from the heading position | `src/Ingestion` |
| Course Brief and Blueprint schemas, LLM output schemas | `resources/schemas` |
| Prompts (README explains versioning) | `resources/prompts` |
| Blueprint helpers, element-aware diff, deterministic checks | `src/Blueprint` |
| Interview, outline, generation (stages and steps), element patches | `src/Pipeline` |
| Versions, runs and UI actions, state snapshot | `src/Services` |
| AG-UI event log, A2UI surfaces, catalogue validation | `src/Events`, `src/Ui` |
| Applier, GIFT renderer, lesson Markdown, landing document | `src/Apply` |
| REST + SSE | `src/Http`, `src/routes.php` |

Rules enforced by tests: no direct writes to LMS tables (`GuardsTest`), every streamed component
exists in the catalogue manifest, source text only inside `<source_document untrusted="true">` in the
user turn (`PromptInjectionTest`), every endpoint 401/403/404-isolated (`IsolationTest`; cross-tenant
HTTP checks in `packages/tenancy/tests/Integration/TenantIsolationTest`, opt-in).

### Adding a pipeline step

1. Add a prompt `resources/prompts/<task>/v1.md` and an output schema `resources/schemas/outputs/<task>.json`
   (closed objects, every property required: provider structured outputs need that).
2. Add the task to `packages/ai/config/ai.php` (`tasks`) with a profile, effort and output limit.
3. Call `Llm::generate($session, $run, '<task>', $blocks, $validator)` with blocks built by
   `PromptContext` (source block first, then context, then the instruction).
4. Register a synthetic responder in `src/Fake/SyntheticResponders.php` so demos and the e2e keep
   working without a key, and record cassettes with the eval command.

## AG-UI stream

`GET /api/admin/course-builder/sessions/{id}/events` (`text/event-stream`):

1. `retry: 1000`, then `data: {"type":"STATE_SNAPSHOT","snapshot":{…}}` (no id; sent on every
   connect);
2. every stored event after `Last-Event-ID` (or `?after=`), each `id: <row id>` + `data: <event>`;
3. new events as they are appended, until 25 s; the client reconnects with the last id.

Events: `RUN_STARTED/FINISHED/ERROR`, `STEP_STARTED/FINISHED` (stage names), `TEXT_MESSAGE_*`
(assistant and the author's own messages), `STATE_DELTA` (JSON Patch on `/cost`, `/brief`,
`/session`, `/sources`, `/budgetReached`), `ACTIVITY_SNAPSHOT` with activity type `a2ui-surface`
(ADR 0023), `CUSTOM` (`applied`, `published`).

UI actions: `POST /sessions/{id}/runs` with a `RunAgentInput`:
`forwardedProps.action = {name, surfaceId, sourceComponentId, context}`. Actions:
`answer {key, value}`, `decide_for_me {key?}`, `approve_outline {versionId, edits:[{objectiveId,text}]}`,
`reject_outline {versionId, comment}`, `retry_step {stepId}`, `approve_apply {versionId}`,
`approve_patch|reject_patch {versionId}`, `retry`. Typed text goes in `messages`, scoped by
`forwardedProps.selection.elementId`.

In TypeScript: `createCourseBuilderClient()` and `connectEventStream()` from `@ulams/sdk`,
`renderSurface()` from `@ulams/ui/builder/renderer.ts`.

## UI catalogue

`front/ui/src/builder/catalogue.ts` is the source of truth for the builder components (schema,
model-facing description, fallback). `yarn workspace @ulams/ui builder-manifest` writes
`front/ui/catalogue/manifest.json` and the API copy
`api/packages/course-builder/resources/catalogue/manifest.json`; `yarn workspace @ulams/ui test`
fails when they drift.

## Eval command

```bash
# fake driver (free): checks the whole pipeline on the golden fixtures
php artisan course-builder:eval --fixtures=all --author=<user id>
# real model (costs money; capped by COURSE_BUILDER_EVAL_MONTHLY_USD)
php artisan course-builder:eval --fixtures=coffee,injection --live --author=<user id>
# record cassettes for the replay test
php artisan course-builder:eval --fixtures=coffee --live --record --author=<user id>
```

Fixtures: `coffee` (Markdown), `injection` (Markdown with injected instructions), `git` (Markdown with
code), `pdf` (generated handbook), `docx` (generated). Checks: blueprint schema, citation coverage,
quiz support, duration within ±15 %, interview component choice, no raw markup, injected
instructions not followed, cache reads after the first lesson call, the chat edit as a DiffView,
cost. Reports go to `storage/app/evals/<date>-<fixture>.md` and `.json`.

## Tests

```bash
vendor/bin/phpunit --testsuite ai,course-builder          # API, fake driver only
yarn workspace @ulams/sdk test && yarn workspace @ulams/ui test && yarn workspace @ulams/web test
STUDIO_E2E=1 yarn workspace @ulams/web test:e2e tests/e2e/studio.spec.ts   # see tests/e2e/README-studio.md
```
