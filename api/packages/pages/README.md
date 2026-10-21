# Pages

Static page management package

## What does it do

This package allows you to create static pages in Laravel app.

## Installing

- `composer require ulams/pages`,
- `php artisan migrate`
- `php artisan db:seed --class="Ulams\Pages\Database\Seeders\PermissionTableSeeder"`

## Endpoints

All the endpoints are defined in [![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://ulams.github.io/pages/)

## Tests

Run `./vendor/bin/phpunit --filter 'Ulams\\Pages\\Tests'` to run tests. See [tests](tests) a quite good starting point for creating your own.

## Permissions

Permissions are defined in [seeder](database/seeders/PermissionTableSeeder.php)

## Database relation

1. `Author` Page is related belong to with User
```
Page 1 -> 1 Author
```
