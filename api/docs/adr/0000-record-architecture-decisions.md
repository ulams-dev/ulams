# 0000. Record architecture decisions

- Status: Accepted
- Date: 2026-10-08

## Context and Problem Statement

The Wellms / Escola LMS API was developed for about five years (2021-03 to 2026) in the
standalone `EscolaLMS/API` repository without written architecture decision records. Its
history was imported into this monorepo under `api/`. Contributors joining in the monorepo era
need to know *why* the API looks the way it does (packages per domain, multi-domain mode,
Postgres, stateless containers, and so on) without digging through ~850 commits.

## Considered Options

- Keep relying on commit history, README and `api/docs/*.md` only.
- Write Architecture Decision Records in MADR format, including retroactive ones.

## Decision Outcome

Chosen option: write ADRs in [MADR](https://adr.github.io/madr/) format under `api/docs/adr/`.

- ADRs `0001`–`0099` are **retroactive**: reconstructed from git history of the imported
  repository. They cite the rewritten monorepo commit hashes as evidence. Anything not
  stated in a commit, diff or doc is marked `Inferred:`.
- ADRs `0100`+ are reserved for decisions made in the monorepo era and are written at the
  time the decision is made.

### Consequences

- Good: the reasoning behind existing structure is discoverable next to the code.
- Good: future changes can explicitly supersede an older ADR instead of silently diverging.
- Bad: retroactive ADRs can only describe what history shows; original motivations are often
  unrecorded and are labelled as inferences.

## Evidence

- Monorepo import of `EscolaLMS/API` history under `api/` (first imported commit
  `05ceebba` "initical commit", 2021-03-03).
