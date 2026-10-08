# 0001. Restart the front-end as a REST-consuming CRA + TypeScript app

- Status: Accepted (retroactive); the build tool part is superseded by [0010](0010-migrate-build-to-vite-and-react-18.md)
- Date: 2021-09-17

## Context and Problem Statement

The first version of the repository (2021-03-09, `f02447f1 initial commit`) was a webpack /
Create React App setup with `@apollo/client`, `apollo-client`, `connected-react-router`,
redux, bootstrap, enzyme and Storybook. On 2021-09-17 the whole tree was deleted and
replaced by a different codebase in two consecutive commits.

## Considered Options

Not recorded.

## Decision Outcome

The app was restarted as a Create React App (`react-scripts`) TypeScript project that talks to
the Laravel REST API with `axios`, uses `styled-components`, `i18next`, `h5p-headless-player`
and a React context for state, and drops Apollo/GraphQL, redux and Storybook.

Inferred: the API side (Laravel headless LMS) exposed REST endpoints, so a GraphQL client
had no backend to talk to; the commit messages give no reason.

### Consequences

- Good: one data-access style (REST + context) that later moved into the SDK ([0002](0002-api-access-through-escolalms-sdk-react-context.md)).
- Good: removed a large legacy dependency set (312 files, -18 648 lines).
- Bad: the history before 2021-09-17 is effectively unrelated to the current code.

## Evidence

- `f02447f1` 2021-03-09 "initial commit" - `front/package.json` (apollo, redux, storybook, enzyme)
- `4dcb98af` 2021-06-24 "front refeactor A"
- `a49c6691` 2021-09-17 "deleted" - 312 files, 18 648 deletions
- `6cb7cc44` 2021-09-17 "copied" - 865 files added; `front/package.json` (`react-scripts`, `axios`, `i18next`, `h5p-headless-player`, `styled-components`)
