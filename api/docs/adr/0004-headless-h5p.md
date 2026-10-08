# 0004. Headless H5P via the escolalms/headless-h5p package

- Status: Accepted (retroactive)
- Date: 2021-04-26

## Context and Problem Statement

Interactive content is authored in H5P. Existing H5P integrations (Moodle, Drupal, WordPress
and the Laravel fork `escolasoft/laravel-h5p` used initially) render the player and editor
server-side with an `H5PIntegration` global JS variable. That conflicts with a headless API
whose UIs are separate React apps (ADR-0001).

## Considered Options

- Keep the forked `escolasoft/laravel-h5p` (`dev-dev-qunabu-patch-3`) with server-side
  rendering and in-app controllers/migrations (state at `05ceebba`).
- Write a headless H5P package exposing everything over REST.

## Decision Outcome

Chosen: a dedicated package `escolalms/headless-h5p` (GitHub `EscolaLMS/H5P`). PR #15 removed
the in-app H5P controller, repository, service, config and `h5p_*` migrations and the
`escolasoft/laravel-h5p` + forked `h5p-php-library` repositories, and required
`escolalms/headless-h5p`. The package provides REST endpoints for libraries and content CRUD,
`.h5p` upload/export and the data the player/editor need; rendering is done client-side by the
separately maintained `@escolalms/h5p-react` player used by `admin/` and `front/`.

### Consequences

- Good: H5P works in any front-end and fits the multi-domain/stateless model.
- Good: H5P code is testable in isolation (its own test suite in `phpunit.xml`).
- Bad: Escola maintains its own H5P integration and player, tracking upstream H5P changes alone.
- Bad: H5P assets served from object storage need CORS handling (`a66ea765`, `63572f6a`
  "cors issues for h5p images", 2023-01-10).

## Evidence

- `1c0059ba` 2021-04-26 "Attaching h5p lib to API (#15)" — deletes
  `api/app/Http/Controllers/API/H5PAPIController.php`, `api/config/laravel-h5p.php`,
  `h5p_*` migrations; `api/composer.json` `-escolasoft/laravel-h5p`, `+escolalms/headless-h5p`.
- `7fb4743c` 2021-04-28 "Feature/h5p (#17)".
- `api/vendor/escolalms/headless-h5p/README.md` — "no blade templates … different approach
  than moodle, drupal and wordpress h5p plugins".
