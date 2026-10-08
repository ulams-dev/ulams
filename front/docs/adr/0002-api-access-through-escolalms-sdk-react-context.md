# 0002. API access and app state through `@escolalms/sdk` and its React context

- Status: Accepted (retroactive)
- Date: 2021-10-12

## Context and Problem Statement

After the restart ([0001](0001-restart-front-as-rest-client-cra-typescript-app.md)) the API
calls and the React context lived inside `front/src`. The same calls were needed by other
Wellms clients (admin panel, mobile, bespoke fronts).

## Considered Options

Not recorded.

## Decision Outcome

The API services and the React context were extracted into a separate npm package, first
published as `@escolalms/connector` (sdk repo, 2021-09-15) and renamed `@escolalms/sdk` on
2021-10-12. The front wraps the app in `EscolaLMSContextProvider apiUrl=...` (`src/index.tsx`)
and pages read data/actions with `useContext(EscolaLMSContext)` (`fetchSettings`,
`fetchConfig`, `fetchNotifications`, courses, cart, ...).

SDK milestones visible in its history: React scope (`975a0368`, 2021-10-12), refresh token
(`4d000a4b`, 2022-03-08), typed with ts-models (`f5731038`, 2022-03-09, see [0003](0003-generated-ts-models-for-api-types.md)),
"fetch refactor" of the context (`436f13a9`, 2022-06-23), types refactor and 1.0.0 beta
(`027d5030` 2025-01-14, `dc9fbfa7` 2025-05-07). The front moved to it in `27f75425`
"new sdk integrations" (2025-02-25); current range `^1.0.0`.

### Consequences

- Good: front pages are thin; API contract changes are absorbed in one package.
- Good: one global context offers caching/loading state for every resource.
- Bad: the context (`src/react/context/index.tsx`, ~57 KB) is a single very large provider;
  every new API feature needs an SDK release before the front can use it.
- Bad: front and SDK versions must be bumped in lockstep (dozens of "Update sdk" commits).

## Evidence

- `332e16f2` 2021-09-22 "migrate to escolams package (#46)" - 49 files, -2 278 lines, adds `@escolalms/connector`
- `63c846ab` 2021-09-22 "migrate to escolams package"
- `2fcce3ac` 2021-10-12 "change connector to sdk" - `front/package.json`
- `27f75425` 2025-02-25 "new sdk integrations"
- sdk repo: `3e1fd71a` 2021-09-15 "initial push" (name `@escolalms/connector`), `79774df3` 2021-10-12 "change name", `436f13a9` "fetch refactor first vol (#147)", `dc9fbfa7` "Merge pull request #346 from EscolaLMS/1.0.0-beta"
