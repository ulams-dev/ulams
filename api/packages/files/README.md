# Files 

Files browser package

[![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://ulams.github.io/Files/)

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
