# Files 

Files browser package

[![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://ulams.github.io/Files/)
[![codecov](https://codecov.io/gh/Ulams/Files/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/Ulams/Files)
[![phpunit](https://github.com/EscolaLMS/Files/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/Files/actions/workflows/test.yml)
[![Maintainability](https://api.codeclimate.com/v1/badges/99e3f317974d77113a6a/maintainability)](https://codeclimate.com/github/Ulams/Files/maintainability)

## What does it do

This package is used to upload, delete and reuse files.

## Installing

- `composer require ulams/files`
- `php artisan migrate`
- `php artisan db:seed --class="Ulams\Files\Database\Seeders\PermissionTableSeeder"`

## Database

This package adds `access_to_directories` column to the users table. 

## Endpoints

All the endpoints are defined in [![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://ulams.github.io/Files/)

## Tests

Run `./vendor/bin/phpunit` to run tests.
[![phpunit](https://github.com/EscolaLMS/Files/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/Files/actions/workflows/test.yml)
[![codecov](https://codecov.io/gh/Ulams/Files/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/Ulams/Files)

## Events

This package does not dispatch any events.

## Listeners

This package listens for events and adds or removes user access to directories.

- `Ulams\Auth\Events\AccountConfirmed` - add user access to directory `avatars/{user_id}`

- `Ulams\Courses\Events\CourseTutorAssigned` - add user access to directory `course/{course_id}`
- `Ulams\Courses\Events\CourseTutorUnassigned` - remove user access to directory `course/{course_id}`

- `Ulams\Webinar\Events\WebinarTrainerAssigned` - add user access to directory `webinar/{webinar_id}`
- `Ulams\Webinar\Events\WebinarTrainerUnassigned` - remove user access to directory `webinar/{webinar_id}`

- `Ulams\StationaryEvents\Events\StationaryEventAuthorAssigned` - add user access to directory `stationary-events/{stationary_evet_id}`
- `Ulams\StationaryEvents\Events\StationaryEventAuthorUnassigned` - remove user access to directory `stationary-events/{stationary_evet_id}`

## How to use this on frontend

### Admin panel

**Left menu**
![Menu](docs/menu.png "Menu")

**Files browser**
![List](docs/list.png "List")

**File finder**
![Finder](docs/finder.png "File finder")

**Upload the file to the selected directory**
![Upload](docs/upload.png "Upload")

## Permissions

Permissions are defined in [seeder](database/seeders/PermissionTableSeeder.php)
