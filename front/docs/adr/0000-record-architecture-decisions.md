# 0000. Record architecture decisions

- Status: Accepted
- Date: 2026-10-08

## Context and Problem Statement

`front/` (the Wellms / Escola LMS demo learner front-end) was developed for about five years
(2021-03 to 2026) in the standalone `EscolaLMS/Front` repository, together with a set of
companion npm packages (`@escolalms/sdk`, `@escolalms/components`, `@escolalms/ts-models`,
`@escolalms/h5p-react`, `@escolalms/scorm-player`). None of the architectural decisions were
written down; they exist only in commit history, PR titles and `package.json` diffs. The
repository has now been imported into the `ulams` monorepo, and the companion packages are
being vendored into it, so the reasons behind the current shape have to be recoverable
without archaeology.

## Considered Options

- Keep relying on git history and tribal knowledge.
- Write Architecture Decision Records in MADR format, starting with retroactive ones.

## Decision Outcome

Chosen option: MADR records in `front/docs/adr/`.

- ADRs 0001-0099 are **retroactive**: reconstructed from the imported git history (monorepo
  commit hashes) and from the histories of the vendored libraries (their own hashes, labelled
  with the repository name). Motivations that are not written in a commit or PR are marked
  "Inferred:".
- ADRs 0100+ are reserved for decisions made in the monorepo era.

### Consequences

- Good: new contributors can see why the front looks the way it does and which decisions
  were later reversed.
- Bad: retroactive records can only cite what the history shows; the original trade-off
  discussions (Jira/Slack) are lost.
