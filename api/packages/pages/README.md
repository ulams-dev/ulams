# Pages

Static page management package

[![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/pages/)
[![codecov](https://codecov.io/gh/EscolaLMS/Files/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/EscolaLMS/pages)
[![phpunit](https://github.com/EscolaLMS/pages/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/pages/actions/workflows/test.yml)
[![downloads](https://img.shields.io/packagist/dt/escolalms/pages)](https://packagist.org/packages/escolalms/pages)
[![downloads](https://img.shields.io/packagist/v/escolalms/pages)](https://packagist.org/packages/escolalms/pages)
[![downloads](https://img.shields.io/packagist/l/escolalms/pages)](https://packagist.org/packages/escolalms/pages)
[![Maintainability](https://api.codeclimate.com/v1/badges/49c10a482155d8ab86c1/maintainability)](https://codeclimate.com/github/EscolaLMS/pages/maintainability)

## What does it do

This package allows you to create static pages in Laravel app.

## Installing

- `composer require escolalms/pages`,
- `php artisan migrate`
- `php artisan db:seed --class="EscolaLms\Pages\Database\Seeders\PermissionTableSeeder"`

## Endpoints

All the endpoints are defined in [![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/pages/)

## Tests

Run `./vendor/bin/phpunit --filter 'EscolaLms\\Pages\\Tests'` to run tests. See [tests](tests) a quite good starting point for creating your own.

Test details [![codecov](https://codecov.io/gh/EscolaLMS/Files/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/EscolaLMS/pages) [![phpunit](https://github.com/EscolaLMS/pages/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/pages/actions/workflows/test.yml)

## Permissions

Permissions are defined in [seeder](database/seeders/PermissionTableSeeder.php)

## Database relation

1. `Author` Page is related belong to with User
```
Page 1 -> 1 Author
```
