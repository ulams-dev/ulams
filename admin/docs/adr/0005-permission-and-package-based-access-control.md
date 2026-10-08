# 0005. Access control from backend permissions and installed backend packages

- Status: Accepted (retroactive)
- Date: 2021-12-15

## Context and Problem Statement

The API is modular: an installation may or may not include a given `escolalms/*` package (webinars,
consultations, recommender, …), and admin users have fine-grained Spatie permissions rather than just a
role. Menus and routes had been gated by role, which did not match what the API actually allowed.

## Considered Options

- Gate routes by role (the original approach, replaced in `98e27280`).
- Gate routes by backend permission names (chosen).

## Decision Outcome

Permission names are mirrored from the backend in `src/consts/permissions.ts`; `src/access.ts` (umi
`access` plugin) exposes one access key per route, computed with `isUserHavePermissions()` against the
current user's permission list, and `config/routes.ts` references those keys. Later the same file was
extended with `createHavePackageInstalled()` (`src/utils/access.ts`): at startup `src/app.tsx` calls
`GET /api/core/packages` and stores the map of installed backend packages in `initialState`, so features
for packages that are not installed are hidden (first used for the recommender package).

### Consequences

- Good: menus mirror what the API will allow, and one admin build serves differently configured backends.
- Bad: permission names and package names are duplicated as string constants and must be kept in sync
  with the API. A TODO (`#1046`) for minimum package versions is still open in `src/utils/access.ts`.

## Evidence

- `4c1bae62` 2021-12-15 "add permissions from backend" — `admin/src/consts/permissions.ts`.
- `98e27280` 2021-12-15 "change url's access from roles to permissions" — `admin/config/routes.ts`,
  `admin/src/access.ts`, `admin/src/services/escola-lms/permissions.ts`.
- `99d7320d` 2023-04-06 "Feature/general settings init (#757)" — adds `admin/src/services/escola-lms/packages.ts` (`GET /api/core/packages`).
- `221fd863` 2023-08-09 "approveForm refactor" — adds `admin/src/consts/packages.ts`.
- `422db30e` 2023-11-13 "feat: recommender package check" — `admin/src/utils/access.ts`, `admin/src/access.ts`.
