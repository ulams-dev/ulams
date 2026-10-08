# CLAUDE.md

## Project

ULAMS: an AI-native, headless LMS built on Wellms (Escola LMS): a Laravel REST API composed of
`escolalms/*` composer packages, with separate frontends. Differentiator: courses that stay in
sync with their sources ("Living Course") and adapt to each learner, with every generated
element cited. Commerce runs as a separate Sylius 2.x headless service behind one frontend.

GitHub organisation: `ulams-dev` (the `ulams` handle is taken). Product, CLI (`ulams`) and
package names stay "ulams".

## Source of truth

| File | Role | Who edits |
|---|---|---|
| `docs/ROADMAP-PROMPT.md` | Full spec: principles, phases, requirements, quality bar | Only with my approval |
| `docs/ROADMAP-TODO.md` | Progress tracker: checkboxes, decisions, open decisions | You, as work progresses |
| `docs/decisions/NNNN-title.md` | Architecture decision records (ADRs) | You propose, I approve |
| `docs/plans/phase-N.md` | Plan for the phase or milestone in progress | You |

Read both roadmap files at the start of every session. If they disagree, the spec wins for
requirements and the TODO wins for status; point out the conflict.

## How to start a session

When I say "continue" or "start Phase N":
1. Read `docs/ROADMAP-PROMPT.md` (the relevant phase plus principles and quality bar) and
   `docs/ROADMAP-TODO.md`.
2. Report in 5–10 lines: current phase, items done, the next unchecked items, open decisions
   that block them.
3. Propose the next milestone: a small, demoable slice (usually 1–5 TODO items).
4. Wait for my go-ahead before writing a plan.

## Workflow per milestone

1. **Explore**: read the relevant code, packages and tests. No edits.
2. **Plan**: write `docs/plans/phase-N.md` (or a section in it): goal, scope, TODO items
   covered, design, new dependencies with justification, migrations, tests, risks, open
   questions. **Stop and wait for my approval.**
3. **Implement** in small commits, one concern per commit, tests in the same commit.
4. **Verify**: full test suite, linters, relevant evals, accessibility checks for UI.
5. **Update the TODO** (see rules below) and summarise: what was built, how to demo it,
   what's left, new open questions.

Never skip the stop after a plan. If the plan changes significantly during implementation,
stop and say so instead of improvising.

## Rules for ROADMAP-TODO.md

- Tick `[x]` only when the item is implemented, tested and merged into the working branch.
  Partial work stays `[ ]` with a short note: `- [ ] Item (partial: API done, UI pending)`.
- Do not delete or reword items. If an item turns out wrong or obsolete, add a note and raise
  it with me; I decide whether the spec changes.
- New work discovered during implementation goes under the relevant phase as a new item
  marked `(new)`.
- Decisions I make in chat: add them to "Decisions made" and, for architectural ones, write an
  ADR in `docs/decisions/`.
- Open decisions that block your next step: ask me, don't guess.

## Non-negotiables (from the spec)

- Do not use, extend or depend on `escolalms/recommender`.
- AI proposes, the author approves: every AI change is a reviewable diff.
- Every generated element cites source fragments; no uncited claims in course content.
- Uploaded content is untrusted input (prompt injection).
- LMS entities are changed through domain services, never by writing tables directly.
- Log model, tokens and cost for every LLM call.
- Generative UI is declarative (A2UI + our catalogue); model-written code only inside the
  sandboxed `simulation` component.
- The LMS owns entitlements; Sylius never decides who has access. Access is granted only from
  verified, idempotent order events, never from a frontend redirect.
- Tenant isolation tests for every new endpoint; WCAG 2.2 AA for learner-facing UI.
- Existing tests and linters stay green; H5P and SCORM behaviour unchanged.

## Engineering conventions

- Follow existing `escolalms/*` package patterns; new modules as separate composer packages.
- Justify every new dependency in the plan (licence, maintenance, size, self-hosting impact).
- Mock the LLM in unit and feature tests; real-model runs only through the eval command.
- No model names hardcoded outside config.
- Conventional Commits (`feat:`, `fix:`, `refactor:`, `test:`, `docs:`, `chore:`).
- No AI attribution anywhere: no `Co-Authored-By: Claude` trailers, no "Generated with Claude
  Code" footers in commits or PRs, no `claude/` branch prefixes, no generated-by headers in files.
- Branches: `phase-N/short-description`.

## Communication

- Chat with me in Polish or English; all code, comments, docs, commits and PRs in English.
- Be concise. Lead with what changed and what needs my decision.
- If you are unsure about scope, state the options with trade-offs and recommend one.
