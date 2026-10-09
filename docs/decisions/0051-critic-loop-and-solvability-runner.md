# 0051. Critic loop with a retry budget and an isolated Playwright solvability runner

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/leftovers-0-2.md` (L2-16, L2-17)

## Context and problem statement

The spec requires a generate → critique → fix loop with five critics: pedagogy, grounding,
mechanics, visual/UX and accessibility. It also requires an agent that solves every interactive in a
headless browser and tries adversarial actions. Generated interactives are untrusted content.

## Considered options

1. LLM and deterministic critics in Laravel, plus an optional Node/Playwright runner service. The
   LLM decision loop stays in Laravel.
2. Run the whole agent, LLM included, inside the Node service.
3. LLM critics only.

## Decision

Option 1:

- **Critics.** Mechanics and accessibility are deterministic. Pedagogy (including the four pillars)
  and UX run on the light model. Grounding is the existing grounding task.
- **Loop.** Up to 2 fix iterations, then the element is flagged. Results go to
  `course_builder_critiques`.
- **Budget.** A per-session critic budget (default USD 1). When it runs out, the remaining critics
  are skipped and shown as skipped.
- **Runner.** `api/solver` runs Chromium with Playwright and only opens allow-listed preview origins.
  It exposes snapshot and action endpoints. It ships in compose profile `quality` and is off by
  default for self-hosters.
- **Solving loop.** Laravel decides the next action with task `solver` (logged in `ai_calls`), then
  runs a deterministic adversarial script.

## Consequences

- Good: every LLM call stays logged and budgeted.
- Good: untrusted content runs outside the API.
- Good: works without the runner; interactives are marked "not checked".
- Bad: a ~400 MB optional image, and more cost per course.
- Default pending #55.
