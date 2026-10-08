# Notifications

Notifications package

[![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/Notifications/)
[![codecov](https://codecov.io/gh/EscolaLMS/notifications/branch/main/graph/badge.svg?token=gBzpyNK8DQ)](https://codecov.io/gh/EscolaLMS/notifications)
[![phpunit](https://github.com/EscolaLMS/notifications/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/notifications/actions/workflows/test.yml)
[![downloads](https://img.shields.io/packagist/dt/escolalms/notifications)](https://packagist.org/packages/escolalms/notifications)
[![downloads](https://img.shields.io/packagist/v/escolalms/notifications)](https://packagist.org/packages/escolalms/notifications)
[![downloads](https://img.shields.io/packagist/l/escolalms/notifications)](https://packagist.org/packages/escolalms/notifications)

## What does it do

This package is used for logging and broadcasting notifications for all `EscolaLms` packages events.

## Installation

- `composer require escolalms/notifications`
- `php artisan migrate`
- `php artisan db:seed --class="EscolaLms\Notifications\Database\Seeders\NotificationsPermissionsSeeder"`

## Usage

All events emitted by EscolaLms packages will be logged in database and can be listed through API (and Admin Panel).
There is a configuration file in which you can define events which should be excluded from being stored.

## Endpoints

All the endpoints are defined in [![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/Notifications/)

## Tests

Run `./vendor/bin/phpunit --filter 'EscolaLms\\Notifications\\Tests'` to run tests. See [tests](tests) folder as it's quite good staring point as documentation appendix.

Test details:
[![codecov](https://codecov.io/gh/EscolaLMS/notifications/branch/main/graph/badge.svg?token=gBzpyNK8DQ)](https://codecov.io/gh/EscolaLMS/notifications)
[![phpunit](https://github.com/EscolaLMS/notifications/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/notifications/actions/workflows/test.yml)

### Admin panel

#### **Left menu**

![Menu](docs/menu.png "Menu")

#### **List of notifications**

![List of notifications](docs/list.png "List of notifications")

## Permissions

Permissions are defined in [seeder](database/seeders/NotificationsPermissionsSeeder.php)

## Events

No Events are defined in this package.

## Listeners

- `EscolaLms\Notifications\Listeners\NotifiableEventListener` - this listener listens to all events in `EscolaLms` namespace

## Roadmap. Todo. Troubleshooting

- ???
