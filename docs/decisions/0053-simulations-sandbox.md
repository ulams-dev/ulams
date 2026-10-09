# 0053. Simulations: single-file HTML on the content origin, sandboxed, typed postMessage, off by default

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/leftovers-0-2.md` (L2-22)

## Context and problem statement

The `simulation` component is the only place where the model may write HTML/JS. The spec's rules:

- sandboxed iframe on an isolated origin;
- strict CSP;
- no network;
- typed postMessage API;
- must pass the solvability loop;
- author approval;
- feature flag, off in self-hosted installs.

## Considered options

1. A single-file HTML asset per version, served from the content origin with a no-network CSP,
   iframe `sandbox="allow-scripts"`, and messages validated by schema.
2. Run simulations through a third-party sandbox service.

## Decision

Option 1:

- **Asset.** A single file of at most 200 KB with no external URLs (checked by a parser), stored
  under `simulations/<element>/<version>/`.
- **CSP.** `default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; img-src data:;
  connect-src 'none'; frame-ancestors <app origins>`.
- **Sandbox.** No `allow-same-origin`.
- **Messages.** Only `ulams-sim:ready|progress|score|event` messages that match the schema are
  accepted.
- **Publishing gate.** A simulation must pass the critic loop and the solvability runner, and the
  author must approve it explicitly (`approved_by`).
- **Flag.** Tenant flag `simulations_enabled`, default false everywhere.

## Consequences

- Good: model-written code cannot reach the network, cookies or the parent page.
- Bad: no external libraries inside simulations (everything inline).
- Default pending #56.
