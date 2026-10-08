# Categories

[![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/Categories/)
[![codecov](https://codecov.io/gh/EscolaLMS/Categories/branch/main/graph/badge.svg?token=ci4VPQbrOI)](https://codecov.io/gh/EscolaLMS/Categories)
[![phpunit](https://github.com/EscolaLMS/Categories/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/Categories/actions/workflows/test.yml)
[![downloads](https://img.shields.io/packagist/dt/escolalms/categories)](https://packagist.org/packages/escolalms/categories)
[![downloads](https://img.shields.io/packagist/v/escolalms/categories)](https://packagist.org/packages/escolalms/categories)
[![downloads](https://img.shields.io/packagist/l/escolalms/categories)](https://packagist.org/packages/escolalms/categories)
[![Maintainability](https://api.codeclimate.com/v1/badges/f0a1f2b4df55306155eb/maintainability)](https://codeclimate.com/github/EscolaLMS/Categories/maintainability)

## What does it do

This package is used for creating Categories for EscolaLms.

## Installing

- `composer require escolalms/categories`
- `php artisan migrate`
- `php artisan db:seed --class="EscolaLms\Consultations\Database\Seeders\ConsultationsPermissionSeeder"`

## Endpoints

All the endpoints are defined in [![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/Categories/)

## Tests

Run `./vendor/bin/phpunit --filter 'EscolaLms\\Categories\\Tests'` to run tests. See [tests](tests) folder as it's quite good staring point as documentation appendix.

### Admin panel

**Left menu**

![Menu](docs/menu.png "Menu")

**List of consultations**

![List of categories](docs/list.png "List of categories")

**Creating/editing consultation**

![Creating/editing category](docs/create.png "Creating or editing category")

## Permissions

Permissions are defined in [seeder](database/seeders/CategoriesPermissionSeeder.php)

## Features

The lib allows categories

- adding a category
- generate slug for category
- edit category
- delete category
- show list categories
- if you want to remove category which has sub categories, earlier delete sub categories

To play the content you can use [EscolaLMS Categories](https://github.com/EscolaLMS/Categories)

### Seeder

You can seed library and content with build-in seeders that are accessible with

- `php artisan category-permissions:seed` to add permissions
- `php artisan db:seed --class="\EscolaLms\Categories\Database\Seeders\CategoriesSeeder"`

1. `Parent` Category is related belong to with other category
2. `Children` Category is related has many with other categories
3. `Courses` Category belongs to many with models Course
4. `Users` Category is related belongs to many with User
```
Category 1 -> 1 Category (Parent)
Category 1 -> n Category (Children)
Category 1 -> n Course
Category 1 -> n Users
```
