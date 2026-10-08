# 0005. Switchable local vs published source for `@escolalms/components`

- Status: Accepted (retroactive)
- Date: 2024-02-13

## Context and Problem Statement

Because UI lives in `@escolalms/components` ([0004](0004-shared-component-library-with-styled-components-theming.md)),
developing a front feature often meant: change Components, publish to npm, bump the front.
After the Vite migration ([0010](0010-migrate-build-to-vite-and-react-18.md)) path aliases
became resolvable through `vite-tsconfig-paths`.

## Considered Options

Not recorded.

## Decision Outcome

Two scripts toggle the source of the library:

- `switch_to_local_components.sh` removes the npm package, adds its peer dependencies,
  `git clone`s `EscolaLMS/components` into `front/components/`, copies
  `tsconfig.paths.json.local` (maps `@escolalms/components/lib/*` to `components/src/*`) over
  `tsconfig.paths.json` and starts Vite.
- `switch_to_prod_components.sh` restores the npm package and `tsconfig.paths.json.prod`.

### Consequences

- Good: front and component changes can be developed together with hot reload.
- Bad: the script mutates `package.json`, `yarn.lock` and `tsconfig.paths.json`; forgetting
  to switch back before committing breaks CI.
- Inferred: vendoring Components into the monorepo makes these scripts obsolete; a
  monorepo-era ADR (0100+) should record their replacement.

## Evidence

- `9a075c59` 2024-02-13 "local package"
- `00ca63fc` 2024-02-13 "local package" - `front/switch_to_local_components.sh`
- `472d97ca` 2024-02-13 "run local and prod components" - `front/tsconfig.paths.json.local`, `front/tsconfig.paths.json.prod`
