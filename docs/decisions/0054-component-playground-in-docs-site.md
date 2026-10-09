# 0054. Component playground in the docs site instead of Storybook

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/leftovers-0-2.md` (L2-19)

## Context and problem statement

The spec asks for a live playground of the catalogue that includes the model-facing descriptions.
Catalogue components are framework-free and each one already has a props JSON Schema, a description
and a text fallback.

## Considered options

1. Pages in the Starlight docs site that render each component from example props.
2. Storybook.

## Decision

Option 1. Each component gets one page with:

- a live render;
- a props table generated from the schema;
- the model-facing description;
- the text fallback;
- an invalid-props example;
- an axe result computed at build time.

The docs coverage check requires an example for every component.

## Consequences

- Good: no new tool chain; the playground ships with the public docs.
- Bad: fewer interactive controls than Storybook's add-ons.
- Default pending #57.
