# 0012. Upgrade to Laravel 9 and a PHP 8.1+ baseline

- Status: Accepted (retroactive)
- Date: 2024-02-14

## Context and Problem Statement

The API started on Laravel 8 / PHP 7.4 (`05ceebba`). By 2024 Laravel 8 was out of support,
PHP 7.4 was EOL, and dependencies (Passport, spatie/permission, Sentry SDK) required newer
versions. The ~50 packages had to move with the API.

## Considered Options

Not recorded. (No move beyond Laravel 9 is present in history; `composer.json` still pins
`laravel/framework ^9`.)

## Decision Outcome

- PHP 8 runtime arrived first via Docker (`devilbox/php-fpm:8.0`, 2021-09); PHP 8.1 tests were
  added in 2022-03 (`63b38bb3`).
- 2024-02-14/15 the "update laravel to l9" change set `php >=8.1`, `laravel/framework ^9`,
  `laravel/passport ^11`, `spatie/laravel-permission ^6.3`, `sentry/sentry-laravel ^4.2`,
  replaced `facade/ignition` with `spatie/laravel-ignition`, dropped `fruitcake/laravel-cors`,
  `laravel/ui` and Behat/Mink leftovers, and removed `composer.lock` for PHP 8.1 tests.
- The production image followed: PHP 8.2 (`cbe9cfd7`, 2024-02-16), PHP 8.3 (`51d08ce8`,
  2025-01-08); CI tests PHP 8.2, 8.3 and 8.4.

### Consequences

- Good: supported framework and PHP versions; modern dependencies.
- Bad: Laravel 9 itself reached end of security support in 2024; another upgrade is due.
  Inferred: the package-per-domain layout (ADR-0002) makes framework upgrades expensive,
  which is why the step stopped at 9.
- Bad: `composer.lock` handling differs between PHP versions in CI.

## Evidence

- `63b38bb3` 2022-03-17 "Add php 8.1 test".
- `86b9e226` 2024-02-14 "update laravel to l9" — `api/composer.json`, `api/app/Http/Kernel.php`.
- `b7bef42f` / `b410dda7` 2024-02-15 "update laravel to l9. composer fix" / "remove composer
  lock for php 8.1 tests and other fixes required for update from l8 to 9".
- `cbe9cfd7` 2024-02-16 "docker build php8.2"; `51d08ce8` 2025-01-08 "docker update. php 8.3".
