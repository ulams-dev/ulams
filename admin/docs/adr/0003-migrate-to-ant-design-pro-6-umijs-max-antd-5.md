# 0003. Migrate to Ant Design Pro 6 / @umijs/max 4 / antd 5

- Status: Accepted (retroactive)
- Date: 2024-02-14

## Context and Problem Statement

By 2024 the panel was still on umi 3.5, antd 4.24 and `@ant-design/pro-layout` 6, while upstream Ant Design Pro had moved to `@umijs/max` 4, antd 5 (CSS-in-JS tokens) and `@ant-design/pro-components` 2. Staying on umi 3 blocked dependency updates.

## Considered Options

Not recorded.

## Decision Outcome

A single large PR (#1002, "Migratin to new version of Ant Design Pro. From 3 to 4") moved the project to: `@umijs/max ^4.1.1` (scripts `max dev` / `max build`), `antd ^5.13`, `antd-style`, `@ant-design/pro-components ^2.6`, TypeScript 5.3, `umi-presets-pro`, `@umijs/lint`, husky/lint-staged, and Node 20 (`engines.node >=20`, Docker base `node:16-buster` → `node:20-buster`). `config/config.ts` was rewritten in the umi 4 format (`model`, `initialState`, `layout`, `locale`, `access`, `request` plugins, `moment2dayjs`). The same PR added the PHP `index.php` front controller and switched the runtime image from `httpd` to `php:apache` for multi-domain env injection (see 0007) and switched CI from npm to yarn.

### Consequences

- Good: current antd 5 / pro-components; theming via design tokens; dayjs instead of moment.
- Good: unblocked later work (SCORM dev-server plugin `config/plugin-scorm.ts` uses the umi 4 plugin API).
- Bad: 414 files changed in one PR; the PR body notes "fixed types and added issues to be fixed", i.e. some follow-up issues were deferred; it also lists "missing e2e tests".

## Evidence

- `a73e752e` 2024-02-14 "Migratin to new version of Ant Design Pro. From 3 to 4 (#1002)" — 414 files; `admin/package.json`, `admin/config/config.ts`, `admin/tsconfig.json`, `admin/Dockerfile`, `admin/config/php/index.php` (new).
- Before: `60f26804` 2023-07-25 state — umi ^3.5.41, antd ^4.24.0, pro-layout ^6.
