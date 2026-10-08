# 0002. Upgrade to React 18

- Status: Accepted (retroactive)
- Date: 2022-04-01

## Context and Problem Statement

The panel started on React 17 (template default). React 18 was released on 2022-03-29; libraries the
panel depends on (antd, pro-components, the Escola packages) were moving to it.

## Considered Options

Not recorded.

## Decision Outcome

`react` and `react-dom` were bumped to `^18.0.0` three days after the React 18 release, then pinned to
`^18.2.0` in the July 2022 dependency refresh, which also moved umi to 3.5.30 and TypeScript to 4.7.
React 18 has stayed the baseline since; the unit-test stack later adopted
`@cfaester/enzyme-adapter-react-18` and `@testing-library/react` 13 to match.

Rationale: not recorded in the commit (message "packages").

### Consequences

- Good: one React major across admin and front; concurrent features available.
- Bad: Enzyme has no official React 18 adapter, so tests rely on a community adapter.

## Evidence

- `f95a972a` 2022-04-01 "packages" — `admin/package.json` (`react ^17.0.0` → `^18.0.0`, `react-dom ^18`).
- `cd510633` 2022-07-18 "packages ver update (#501)" — `admin/package.json` (react ^18.2.0, umi ^3.5.30,
  antd ^4.19.0, typescript ^4.7.4) plus component fixes.
