# 0070. The admin runs umi/max on Node 24 through a small shim

- Status: Proposed
- Date: 2026-10-09

## Context and problem statement

`yarn install` failed on Node 24 in `admin`: the `postinstall` script `max setup` died with
`No such module: http_parser`. `@umijs/bundler-utils` requires `spdy` while loading, `spdy-transport` requires
`http-deceiver`, and `http-deceiver` reads `process.binding('http_parser')` at load time, a binding Node 24
removed. Every `max` command (`build`, `dev`, `setup`) was affected. The newest `@umijs/max` (4.7.23)
still depends on `spdy`, so upgrading does not help.

## Considered options

1. Upgrade umi/max: no release drops `spdy`.
2. A vendored patched `http-deceiver` through `resolutions` (`file:`): every Dockerfile that installs from the
   root lockfile would have to copy it first (five images).
3. `patch-package`: a new dependency and a postinstall step that `--ignore-scripts` Docker installs skip.
4. A shim loaded before umi: `admin/scripts/node-compat.cjs` returns an inert `http_parser` when the real
   binding is gone, and `admin/scripts/max.cjs` loads it (also for worker processes, through `NODE_OPTIONS`)
   before `@umijs/max`.

## Decision

Option 4. All `max` scripts in `admin/package.json` (`postinstall`, `build`, `start*`, `preview`, `record`,
`analyze`) call `node scripts/max.cjs`. Only the HTTP/2 dev server uses the deceiver, which the admin
does not enable. On Node 22 the real binding exists and the shim does nothing. CI stays on Node 22.

## Consequences

- Good: install and build work on Node 22 and 24 (checked locally), with no new dependency and no change to
  the Dockerfiles.
- Bad: `max` must be started through the package scripts, not the bare binary, on Node 24; remove the shim
  when umi drops `spdy`.
