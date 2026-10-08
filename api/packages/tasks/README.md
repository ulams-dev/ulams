# Tasks
[![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/Tasks/)
[![codecov](https://codecov.io/gh/EscolaLMS/Tasks/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/EscolaLMS/Tasks)
[![phpunit](https://github.com/EscolaLMS/Tasks/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/Tasks/actions/workflows/test.yml)
[![downloads](https://img.shields.io/packagist/dt/escolalms/Tasks)](https://packagist.org/packages/escolalms/Tasks)
[![downloads](https://img.shields.io/packagist/v/escolalms/Tasks)](https://packagist.org/packages/escolalms/Tasks)
[![downloads](https://img.shields.io/packagist/l/escolalms/Tasks)](https://packagist.org/packages/escolalms/Tasks)

## What does it do
This package is used to manage user tasks. Users can create their own tasks or assign tasks to other system users.

Tasks can be marked as ready/not ready only by users who created the task.
If the task is assigned to another user, a request is sent to checking the correctness of the task.
The user sending the request then receives a notification of the task verification status.

Notes can be added to an assignment. Notes can be added by the task author or assigned user. Each note contains information about who created the note.

The packet also has a scheduled task that reminds you of overdue tasks.
By default, the reminder period is set to 30 days.
No notifications will be sent for tasks older than 30 days.

## Installing
- `composer require escolalms/tasks`
- `php artisan migrate`
- `php artisan db:seed --class="EscolaLms\Tasks\Database\Seeders\TaskPermissionSeeder"`

## Configuration
In the configuration, you can specify the period until when notifications about overdue tasks should be sent.
Parametr możesz określić z poziomu pliku config.php lub ustawień administracyjnych systemu.
You can specify the parameter from the [config.php file](src/config.php) or [system administration settings](src/Providers/SettingsServiceProvider.php).

#### Example config.php
```php
[
    'notifications' => [
        'overdue_period' => 14,
    ],
]
```

## Endpoints
All the endpoints are defined in swagger
[![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/Tasks/)

Test details
[![codecov](https://codecov.io/gh/EscolaLMS/Tasks/branch/main/graph/badge.svg?token=O91FHNKI6R)](https://codecov.io/gh/EscolaLMS/Tasks)
![Tests PHPUnit in environments](https://github.com/EscolaLMS/Tasks/actions/workflows/test.yml/badge.svg)

## Events
- `TaskAssignedEvent` - The task has been assigned to the user.
- `TaskCompleteRequestEvent` - A request has been sent to check the correctness of the task.
- `TaskCompleteUserConfirmationEvent` - The task has been marked as correct.
- `TaskIncompleteEvent` - The task has not been completed.
- `TaskOverdueEvent` - Task overdue, time to complete task has run out.
- `TaskDeletedEvent` - The task has been removed.
- `TaskUpdatedEvent` - The task has been updated.
- `TaskNoteCreatedEvent` - A note for the task has been created.

These events are listened to by the escolalms/templates-email package, which sends emails. Additionally, notifications are stored in database.

## Permissions
Permissions are defined in [seeder](database/seeders/TaskPermissionSeeder.php)
