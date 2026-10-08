# 0000. Record architecture decisions

- Status: Accepted
- Date: 2026-10-08

## Context and Problem Statement

The admin panel (formerly the `EscolaLMS/Admin` repository, now `admin/` in the monorepo) has five years of history and roughly a thousand commits, but no written record of why its architecture looks the way it does. Knowledge lives in commit messages, PR titles and the heads of past contributors. The repository is now being merged into a monorepo with `api/` and `front/`, which is a good point to write that knowledge down.

## Considered Options

- Keep relying on commit history and README files.
- Record decisions as Architecture Decision Records in Markdown, using the MADR template, next to the code.

## Decision Outcome

Chosen option: MADR records in `admin/docs/adr/`, one file per decision, numbered `NNNN-kebab-title.md`.

- Records 0001–0099 are **retroactive**: they were written in 2026 from git history. They describe decisions that had already been made and implemented, they cite the monorepo commit hashes that show them, and they label as "Inferred:" any motivation the history does not state.
- Records 0100 and above are reserved for decisions taken in the monorepo era.
- A record is never deleted. When a decision is replaced, its status becomes "Superseded by NNNN".

### Consequences

- Good: new contributors can find why umi/antd, the runtime env injection, the SCORM service worker etc. exist.
- Good: superseded decisions stay visible, with links to what replaced them.
- Bad: retroactive records can only be as good as the commit messages; many motivations are inferred.

## Evidence

- Monorepo import of the admin history under `admin/` (first commit `1c53acd3` "initial push", 2021-02-26).
