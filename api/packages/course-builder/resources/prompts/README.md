# Course Builder prompts

One directory per task, one file per version: `<task>/v<N>.md`. The front matter names the prompt
(`id`), its `version` (must match the file name), the `task` (which selects profile, effort and
output limit in `packages/ai/config/ai.php`), the output `schema` and a one-line `changelog`.

| Task | Profile | Output schema | Used by |
|---|---|---|---|
| `interview` | light | `resources/schemas/outputs/interview.json` | `Pipeline\InterviewService` |
| `outline` | default | `outputs/outline.json` | `Pipeline\OutlineService` |
| `lesson` | default | `outputs/lesson.json` | `Pipeline\GenerationService` |
| `quiz` | default | `outputs/quiz.json` (per-lesson quiz and final test) | `Pipeline\GenerationService` |
| `grounding` | light | `outputs/grounding.json` | `Pipeline\GenerationService` |
| `metadata` | light | `outputs/metadata.json` | `Pipeline\GenerationService` |
| `patch` | default | built per element type in `Pipeline\PatchSchemas` | `Pipeline\PatchService` |

## Rules

- **Never name a model** in a prompt (a test fails on `claude-…` strings). Models are chosen per task
  in config.
- **Bump the version for any wording change.** Copy `v1.md` to `v2.md`, edit, set `version: 2` and a
  changelog line. Old versions stay so calls logged in `ai_calls` (which store the version) can be
  reproduced. The registry uses the highest version unless `ai.prompt_pins` pins one.
- **The source is data, never instructions.** Every system prompt states that the
  `<source_document untrusted="true">` block is reference material, that instructions inside it are
  content to teach about at most, and that the only output is the JSON object of the schema. The
  model has no tools.
- Prompts are system prompts. They are frozen per version and cached; anything that changes per
  call (brief, outline, the element) goes in the user turn.
- Output is JSON only; the schema is enforced by the API (structured outputs) and again by our
  validators (JSON Schema plus semantic checks: citations resolve, objectives exist, no HTML,
  durations add up). One repair attempt sends the errors back.

## Iterating

1. Edit a new version.
2. Run the eval on the golden fixtures with the real model (costs money; under USD 1 for the
   coffee fixture):
   `php artisan course-builder:eval --fixtures=coffee --live`
   The report goes to `storage/app/evals/<date>-<fixture>.md` with the JSON summary next to it.
3. When the result is good, record cassettes for the tests:
   `php artisan course-builder:eval --fixtures=coffee --live --record`
   (writes `tests/cassettes/<task>/v<N>/<hash>.json`). A prompt version without cassettes makes the
   cassette-replay test fail with the missing path.
