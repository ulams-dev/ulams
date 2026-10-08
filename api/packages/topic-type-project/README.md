# Topic Type Project


## What does it do

This package is another [TopicType](https://github.com/EscolaLMS/topic-types). It allows students to upload their solutions as files.
This type is used for building headless Course.

The package by default grants students permissions to add and view their own solutions within the course.
Administrators have permissions to display the list of all student solutions, as well as to download and delete them.

## Installing

- `composer require ulams/topic-type-project`
- `php artisan migrate`
- `php artisan db:seed --class="Ulams\TopicTypeProject\Database\Seeders\TopicTypeProjectPermissionSeeder"`

## Endpoints

The endpoints are defined in [![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://ulams.github.io/Topic-Type-Project/)

## Tests

Run `./vendor/bin/phpunit` to run tests.
Test details [![codecov](https://codecov.io/gh/Ulams/Topic-Type-Project/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/Ulams/Topic-Type-Project)

## Events

- `ProjectSolutionCreatedEvent` - This event is dispatched when the user has uploaded a solution

You can use the [ulams/templates-email](https://github.com/EscolaLMS/Templates-Email/tree/main/src/TopicTypeProject) package, which listens to this event and sends an email.

## Listeners

This package does not listen for any events.

## Permissions

Permissions are defined in [seeder](https://github.com/EscolaLMS/Topic-Type-Project/blob/main/database/seeders/TopicTypeProjectPermissionSeeder.php).
