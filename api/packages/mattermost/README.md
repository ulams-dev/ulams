# Mattermost



## What does it do

This package is used for [Mattermost](https://mattermost.com/) integration.

It listens for Events then add/remove user for mattermost and particular channels either as standard user or admin. 


## Installing
- `composer require ulams/mattermost`

## Example
You can configure the package using Facade
```
Config::set('mattermost.package_status', PackageStatusEnum::ENABLED);
Config::set('mattermost.servers.default.host', 'localhost');
Config::set('mattermost.servers.default.login', 'login');
Config::set('mattermost.servers.default.password', 'password');
```

or `/api/admin/config` endpoint

```php 
$this->actingAs($this->user, 'api')->json(
    'POST',
    '/api/admin/config',
    [
        'config' => [
            [
                'key' => 'mattermost.package_status',
                'value' => PackageStatusEnum::ENABLED,
            ],
            [
                'key' => 'mattermost.servers.default.host',
                'value' => 'localhost',
            ],
            [
                'key' => 'mattermost.servers.default.login',
                'value' => 'login',
            ],
            [
                'key' => 'mattermost.servers.default.password',
                'value' => 'password',
            ],
        ]
    ]
);
```

## Endpoints
All the endpoints are defined in [![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://ulams.github.io/Mattermost/)

## Test
Run `./vendor/bin/phpunit` to run tests. See tests folder as it's quite good staring point as documentation appendix.
Test details 

## Listeners
Handling events
- `Ulams\Auth\Events\AccountConfirmed` => add user to Mattermost
- `Ulams\Auth\Events\AccountDeleted` => remove user from Mattermost
- `Ulams\Auth\Events\AccountBlocked` => block user account
- `Ulams\Courses\Events\CourseAssigned` => add user to course channel with member role
- `Ulams\Courses\Events\CourseUnassigned` => remove user from course channel
- `Ulams\Courses\Events\CourseTutorAssigned` => add user to course channel with channel_admin role
- `Ulams\Courses\Events\CourseTutorUnassigned` => remove user from course channel
- `Ulams\Webinar\Events\WebinarUserAssigned` => add user to webinar channel with member role
- `Ulams\Webinar\Events\WebinarUserUnassigned` => remove user from webinar channel
- `Ulams\Webinar\Events\WebinarTrainerAssigned` => add user to webinar channel with channel_admin role 
- `Ulams\Webinar\Events\WebinarTrainerUnassigned` => remove user from webinar channel
