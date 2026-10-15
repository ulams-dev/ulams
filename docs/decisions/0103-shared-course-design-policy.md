# 0103. Shared course-design policy for API and local agents

- Status: Accepted
- Date: 2026-10-15
- Plan: `docs/plans/phase-2-course-design-skill.md`

## Context and problem statement

The Course Builder and local agents need the same rules for interviewing authors, selecting engaging
course building blocks, applying white-label design and preserving normal ULAMS progress tracking.
Cursor and Claude understand Agent Skills, but the Laravel runtime does not. A Markdown-only skill
also cannot reliably express or validate installed capabilities, component schemas and tracking
support.

The existing builder already has a Course Brief, Course Blueprint, content-type registry, UI
catalogue, CLI and MCP tools. The new design knowledge should extend those contracts without creating
a second course format or allowing models to write arbitrary UI code.

## Considered options

1. Make one `SKILL.md` the source for local agents and inject the same Markdown into API prompts.
2. Keep separate API prompts and Agent Skill instructions, maintained by convention.
3. Use a versioned machine-readable course-design policy as the source of truth, with thin Laravel
   and Agent Skill adapters.

## Decision

Choose option 3:

- canonical, schema-validated JSON describes building-block capabilities, selection rules, learner
  experience shells and normalized tracking;
- Laravel filters it against installed content types, H5P libraries and feature flags before using
  it in the interview and generation pipeline;
- `.cursor/skills/ulams-course-designer/SKILL.md` reads the same policy and operates ULAMS only
  through the existing CLI/MCP/API contracts;
- generated summaries and drift tests keep both adapters aligned;
- every produced course remains a Course Blueprint applied through domain services;
- models choose only approved declarative components; generated HTML/JS remains limited to the
  future sandboxed, author-approved simulation component.

The policy inventories all known formats without claiming all are generatable. Each capability says
whether it is generated deterministically, selected from a curated catalogue, imported by the
author, sandboxed or unavailable.

## Consequences

- Good: API and agents make the same capability and pedagogy decisions.
- Good: installation-specific availability and tracking can be validated before generation.
- Good: local agents preserve citations, diffs and author approvals by using existing builder tools.
- Good: adding a new H5P mapper or learner component is an explicit, testable registry change.
- Bad: the policy, generated prompt summary and renderers add a drift surface; CI checks are required.
- Bad: an inventory of every H5P type is larger than the actually generated set and must be clearly
  labelled to avoid false promises.
- Bad: Course Brief v3 and new learner shells require migrations/upgrades across API, SDK, CLI,
  Studio and reference frontend.
