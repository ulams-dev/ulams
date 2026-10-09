# 0045. H5P learner state through the BFF; no API token in the browser

- Status: Proposed
- Date: 2026-10-09
- Plan: `docs/plans/leftovers-0-2.md` (L1-06)

## Context and problem statement

The Astro front keeps the learner's API token in an httpOnly session cookie (ADR 0008). The H5P
frame receives `token: null`, so the H5P service cannot load or save the learner's state.

## Considered options

1. The BFF's `/h5p/*` proxy adds `Authorization` on the server side, for an allow-list of H5P paths.
2. Mint a short-lived scoped token and post it into the H5P frame.

## Decision

Option 1. The proxy injects the session's bearer token only for the H5P content-user-data,
finished-data and play/embed routes of the H5P host, never for other hosts. The frame protocol is
unchanged.

## Consequences

- Good: no bearer token is ever exposed to third-party H5P content scripts.
- Good: token refresh stays in one place.
- Bad: H5P traffic for logged-in learners passes through the Astro server, adding a small latency.
- Default pending #51.
