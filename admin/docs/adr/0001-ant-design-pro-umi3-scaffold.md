# 0001. Build the admin panel on Ant Design Pro (umi 3 + antd 4)

- Status: Superseded by [0003](0003-migrate-to-ant-design-pro-6-umijs-max-antd-5.md)
- Date: 2021-02-26

## Context and Problem Statement

Wellms (Escola LMS) needed a back-office UI for its headless Laravel API: CRUD screens for courses, users, files, settings and so on. The team needed a React starter that already had layout, menu, routing, forms, tables, i18n and auth scaffolding.

## Considered Options

Not recorded.

## Decision Outcome

The repository was started from the Ant Design Pro template (`"name": "ant-design-pro"` in `package.json`): umi 3 (`"umi": "^3.2.14"`), antd 4 (`^4.12.0`), `@ant-design/pro-layout` 6, React 17, TypeScript 4. Routes are declared centrally in `config/routes.ts`; `config/config.ts` holds umi configuration. The first deploy targets were GitHub Pages (`gh-pages`) and Heroku.

Inferred: Ant Design Pro was chosen because it gives enterprise CRUD building blocks (ProTable, ProForm) out of the box, which matches an admin panel with dozens of resource screens.

### Consequences

- Good: fast start; every resource screen follows the same ProTable/ProForm pattern.
- Bad: the project inherits a large Chinese-first template (comments, `zh-CN` default locale, unused locales such as `bn-BD`, `fa-IR`) that is still visible today.
- Bad: tight coupling to umi's plugin system made the later major upgrade a 414-file change (see 0003).

## Evidence

- `1c53acd3` 2021-02-26 "initial push" — `admin/package.json` (umi ^3.2.14, antd ^4.12.0, react ^17.0.0, typescript ^4.0.3), `admin/config/config.ts`, `admin/config/routes.ts`, `admin/src/locales/*`.
- `744a8e8e` 2021-02-26 "clean up && gh pages prefix"; `f679e850` 2021-03-01 "heroku".
- `7eaa8f17` 2021-06-07 "gh-pages from admin" — `admin/.github/workflows/pages.yml`.
