# CSV-Users

[![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/CSV-Users/)
[![codecov](https://codecov.io/gh/EscolaLMS/CSV-Users/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/EscolaLMS/CSV-Users)
[![phpunit](https://github.com/EscolaLMS/CSV-Users/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/CSV-Users/actions/workflows/test.yml)
[![downloads](https://img.shields.io/packagist/dt/escolalms/CSV-Users)](https://packagist.org/packages/escolalms/CSV-Users)
[![downloads](https://img.shields.io/packagist/v/escolalms/CSV-Users)](https://packagist.org/packages/escolalms/CSV-Users)
[![downloads](https://img.shields.io/packagist/l/escolalms/CSV-Users)](https://packagist.org/packages/escolalms/CSV-Users)
[![Maintainability](https://api.codeclimate.com/v1/badges/04a88ff03ede597fd18b/maintainability)](https://codeclimate.com/github/EscolaLMS/CSV-Users/maintainability)
[![Test Coverage](https://api.codeclimate.com/v1/badges/04a88ff03ede597fd18b/test_coverage)](https://codeclimate.com/github/EscolaLMS/CSV-Users/test_coverage)

## What does it do

This package is used to export and import users in the `.csv` format. 

## Installation

- `composer require escolalms/csv-users`
- `php artisan db:seed --class="EscolaLms\CsvUsers\Database\Seeders\CsvUsersPermissionSeeder"`

## Example

|id |name             |first_name|last_name|email             |country|is_active|created_at                 |onboarding_completed|email_verified|interests|avatar                                                         |roles    |permissions         |path_avatar           |contact |bio |
|---|-----------------|----------|---------|------------------|-------|---------|---------------------------|--------------------|--------------|---------|---------------------------------------------------------------|---------|--------------------|----------------------|--------|----|
|16 |Valentine Wehnner|Valentine |Wehnner  |jhyatt@example.net|Poland |         |2021-10-14T15:50:28.000000Z|TRUE                |TRUE          |LMS      |localhost/storage/avatars/16/logo.png                          |["tutor"]|["access dashboard"]|avatars/16/logo.png   |1234567 |bio |

- Export uses fields from `EscolaLms\Auth\Http\Resources\UserFullResource`

- Import uses the `update` or `create` method from `EscolaLms\Auth\Repositories\Contracts\UserRepositoryContract`.
If the email exists in the database, the user's data is updated. Otherwise, a new user is created.

## Endpoints

All the endpoints are defined in [![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/CSV-Users/)

## Tests

Run `./vendor/bin/phpunit` to run tests. Test details
[![codecov](https://codecov.io/gh/EscolaLMS/CSV-Users/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/EscolaLMS/CSV-Users)
[![phpunit](https://github.com/EscolaLMS/CSV-Users/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/CSV-Users/actions/workflows/test.yml)

## Events 

- `EscolaLms\CsvUsers\Events\EscolaLmsImportedNewUserTemplateEvent` => Event is dispatched after importing a new user.

## How to use this on frontend

### Admin panel

**Import and export button**
![Import / export button](docs/buttons.png "Import / export button")

## Permissions

Permissions are defined in [seeder](database/seeders/CsvUsersPermissionSeeder.php)
