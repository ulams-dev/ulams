# 0003. H5P as an isolated GPL service (Lumi)

- Status: Accepted (2026-10-08)
- Date: 2026-10-08

## Context and problem statement

H5P was served by `escolalms/headless-h5p`, which loads `h5p/h5p-core` and `h5p/h5p-editor`
(GPL-3.0) into the Laravel process. ulams follows an open-core model with closed paid modules, so GPL
code must not be linked into the API or bundled into the admin and front applications. The PHP
package also had functional gaps (no resume state, a hard-coded editor user).

Every H5P runtime is GPL: the H5P core JavaScript, the PHP libraries and Lumi's Node.js library
(`@lumieducation/*`, GPL-3.0-or-later) alike. The language does not change the licence; the
architecture decides what the GPL applies to.

## Decision

- H5P runs only in `api/h5p`, a separate Node.js program built on Lumi `h5p-nodejs-library`
  (Express 5, PostgreSQL schema `h5p`, MinIO/S3 for files, Redis cache and locks). It is distributed
  under GPL-3.0-or-later with its source.
- The rest of ulams talks to it only over HTTP: Caddy routes `/h5p/*` on the API host to it, and
  Laravel calls it with an internal token. The `api/packages/h5p` package keeps a read-only index over
  `h5p.contents` for validation, listings and course import/export.
- Admin and front never import H5P or Lumi code. The service serves its own player and editor pages;
  the apps embed them in an iframe and exchange xAPI statements, tokens, resize and save events
  through a typed, origin-checked `postMessage` protocol. A lint rule forbids `@lumieducation/*`
  imports in admin and front.

## Consequences

- Good: no GPL code linked into the API or bundled into the frontends; the MIT/proprietary code only
  communicates with the GPL program at arm's length.
- Good: resume state, xAPI and content-type management work; the service has its own tests.
- Bad: one more service to run; content created with the PHP package must be re-imported (there was
  none in the current installs).
- Risk: the iframe/HTTP boundary is the standard isolation pattern but not a legal opinion; confirm
  with counsel before shipping paid modules.
