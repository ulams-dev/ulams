# 0004. Hand-written REST service layer with JWT bearer token in localStorage

- Status: Accepted (retroactive)
- Date: 2021-06-07

## Context and Problem Statement

The panel talks only to the Wellms Laravel API (`/api/admin/*`, `/api/auth/*`, `/api/core/*`). It needs a way to call those endpoints, type their responses, and keep the admin authenticated.

## Considered Options

Not recorded. Note: the Ant Design Pro template ships an `openAPI` code-generation plugin; it is present only as a comment in `config/config.ts` and was never enabled. `@escolalms/ts-models` is listed in `package.json` (added in `b449d020`, 2022-03-09) but no file in `admin/src` imports it today, and the `@escolalms/sdk` used by `front/` is not a dependency of the admin.

## Decision Outcome

Each API area has its own module in `src/services/escola-lms/*.ts` (54 files: `course.ts`, `h5p.ts`, `scorm.ts`, `settings.ts`, …) that calls umi's `request()`; response types live in the global `API` namespace in a hand-maintained `src/services/escola-lms/typings.d.ts` (~52 KB). A request interceptor in `src/app.tsx` prefixes the API base URL (`window.REACT_APP_API_URL || REACT_APP_API_URL`, see 0006) and adds `Authorization: Bearer <token>`. The token (a Passport JWT from the API) is stored in `localStorage['TOKEN']`; since PR #550 `src/services/token_refresh.ts` decodes `exp` with `jwt-decode`, refreshes the token a minute before expiry and logs out on failure, broadcasting a `token_change` event that embedded iframes (H5P editor) listen to.

### Consequences

- Good: no build-time dependency on an API schema; adding an endpoint is a small, local change.
- Bad: types are duplicated by hand from the API (and from `ts-models`/`sdk` used by `front/`) and can drift.
- Bad: a token in `localStorage` is readable by any script on the origin (XSS exposure).

## Evidence

- `45ca816e` 2021-06-07 "courses list #8 (#9)" — first `admin/src/services/escola-lms/*` module, `localStorage.getItem('TOKEN')` and `Bearer` header.
- `90f58d04` 2022-08-04 "token refersh refactor (#550)" — `admin/src/services/token_refresh.ts`, `admin/src/app.tsx`, `admin/src/pages/User/login/index.tsx`.
- `b449d020` 2022-03-09 "Feature/sevents (#370)" — adds `@escolalms/ts-models` to `admin/package.json`.
