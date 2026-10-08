# Mattermost

[![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/Mattermost/)
[![codecov](https://codecov.io/gh/EscolaLMS/Mattermost/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/EscolaLMS/Mattermost)
[![phpunit](https://github.com/EscolaLMS/Mattermost/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/Mattermost/actions/workflows/test.yml)
[![downloads](https://img.shields.io/packagist/dt/escolalms/mattermost)](https://packagist.org/packages/escolalms/mattermost)
[![downloads](https://img.shields.io/packagist/v/escolalms/mattermost)](https://packagist.org/packages/escolalms/mattermost)
[![downloads](https://img.shields.io/packagist/l/escolalms/mattermost)](https://packagist.org/packages/escolalms/mattermost)
[![Maintainability](https://api.codeclimate.com/v1/badges/00725c6ea461fcfa2754/maintainability)](https://codeclimate.com/github/EscolaLMS/Mattermost/maintainability)
[![Test Coverage](https://api.codeclimate.com/v1/badges/00725c6ea461fcfa2754/test_coverage)](https://codeclimate.com/github/EscolaLMS/Mattermost/test_coverage)


## What does it do

This package is used for [Mattermost](https://mattermost.com/) integration.

It listens for Events then add/remove user for mattermost and particular channels either as standard user or admin. 


## Installing
- `composer require escolalms/mattermost`

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
All the endpoints are defined in [![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/Mattermost/)

## Test
Run `./vendor/bin/phpunit` to run tests. See tests folder as it's quite good staring point as documentation appendix.
Test details 
[![codecov](https://codecov.io/gh/EscolaLMS/Mattermost/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/EscolaLMS/Mattermost)
[![phpunit](https://github.com/EscolaLMS/Mattermost/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/Mattermost/actions/workflows/test.yml)

## Listeners
Handling events
- `EscolaLms\Auth\Events\AccountConfirmed` => add user to Mattermost
- `EscolaLms\Auth\Events\AccountDeleted` => remove user from Mattermost
- `EscolaLms\Auth\Events\AccountBlocked` => block user account
- `EscolaLms\Courses\Events\CourseAssigned` => add user to course channel with member role
- `EscolaLms\Courses\Events\CourseUnassigned` => remove user from course channel
- `EscolaLms\Courses\Events\CourseTutorAssigned` => add user to course channel with channel_admin role
- `EscolaLms\Courses\Events\CourseTutorUnassigned` => remove user from course channel
- `EscolaLms\Webinar\Events\WebinarUserAssigned` => add user to webinar channel with member role
- `EscolaLms\Webinar\Events\WebinarUserUnassigned` => remove user from webinar channel
- `EscolaLms\Webinar\Events\WebinarTrainerAssigned` => add user to webinar channel with channel_admin role 
- `EscolaLms\Webinar\Events\WebinarTrainerUnassigned` => remove user from webinar channel
