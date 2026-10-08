# 0003. API model types generated from Eloquent (`@escolalms/ts-models`)

- Status: Accepted (retroactive)
- Date: 2022-03-18

## Context and Problem Statement

The front and the SDK describe objects (courses, topics, users, consultations, ...) that are
defined as Eloquent models across ~50 `escolalms/*` PHP packages. Hand-written TypeScript
types drift from the backend.

## Considered Options

Not recorded.

## Decision Outcome

A dedicated repository `EscolaLMS/ts-models` is a Laravel app that requires the
`escolalms/*` packages plus `based/laravel-typescript`, runs `php artisan typescript:generate`
in a GitHub Action, commits the resulting `models.d.ts` and publishes it to npm. Types are
global namespaces mirroring PHP namespaces, e.g. `EscolaLms.StationaryEvents.Models.StationaryEvent`.
The SDK consumes them (sdk `f5731038`, 2022-03-09) and the front added the package on
2022-03-18; front code references `EscolaLms.*` types directly from 2022-06.

### Consequences

- Good: types track the backend models without manual edits.
- Bad: generation requires booting the full API with a database (`generate.sh`:
  migrate, passport keys) - a heavy pipeline for a type file.
- Bad: the npm package stalled at `0.0.35` (ts-models `35e9e41`, 2023-04-25) while the API
  kept evolving; the SDK later split its own types (sdk `8acd517a` "splited types", 2024-12-20).
  Inferred: newer API fields are typed in the SDK rather than in ts-models.

## Evidence

- `f526f019` 2022-03-18 "ts models connection fix" - `front/package.json` adds `@escolalms/ts-models`
- `b41c0e47` 2022-06-09 "link to modal" - first `EscolaLms.` namespace usage in `front/src`
- `11bc9675` 2025-03-04 "sdk integration new version"
- ts-models repo: `45b7920` 2022-03-07 "ts-models-action-generate", `.github/workflows/generate.yaml`, `generate.sh`, `composer.json` (`based/laravel-typescript`), `35e9e41` 2023-04-25 "0.0.35"
- sdk repo: `f5731038` 2022-03-09 "connected ts-models"
