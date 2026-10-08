# Pencil-Spaces

[![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/Pencil-Spaces/)
[![codecov](https://codecov.io/gh/EscolaLMS/Pencil-Spaces/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/EscolaLMS/Pencil-Spaces)
[![phpunit](https://github.com/EscolaLMS/Consultation-Access/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/Pencil-Spaces/actions/workflows/test.yml)
[![downloads](https://img.shields.io/packagist/dt/escolalms/pencil-spaces)](https://packagist.org/packages/escolalms/pencil-spaces)
[![downloads](https://img.shields.io/packagist/v/escolalms/pencil-spaces)](https://packagist.org/packages/escolalms/pencil-spaces)
[![downloads](https://img.shields.io/packagist/l/escolalms/pencil-spaces)](https://packagist.org/packages/escolalms/pencil-spaces)
[![Maintainability](https://api.codeclimate.com/v1/badges/0c9e2593fb30e2048f95/maintainability)](https://codeclimate.com/github/EscolaLMS/Pencil-Spaces/maintainability)
[![phpstan](https://github.com/EscolaLMS/Pencil-Spaces/actions/workflows/phpstan.yml/badge.svg)](https://github.com/EscolaLMS/Pencil-Spaces/actions/workflows/phpstan.yml)


## What does it do

This package is used for integration with [Pencil Spaces](https://www.pencilspaces.com/).

Currently available features:
- API user creation
- Space creation
- Generating a link for the logged-in user

## Installing

- `composer require escolalms/pencil-spaces`
- `php artisan migrate`

## Configuration 

You can configure the package by adding values to your `.env` file

```
PENCIL_SPACES_API_KEY=api-key
PENCIL_SPACES_API_URL=https://api-url.com
```

or using Facade

```
use Illuminate\Support\Facades\Config;

Config::set('pencil_spaces.api_key', 'api_key');
Config::set('pencil_spaces.api_url', 'https://api-url.com');
```

or `/api/admin/config` endpoint

```php 
$this->actingAs($this->user, 'api')->json(
    'POST',
    '/api/admin/config',
    [
        'config' => [
            [
                'key' => 'pencil_spaces.api_key',
                'value' => 'api_key',
            ],
            [
                'key' => 'pencil_spaces.api_url',
                'value' => 'https://api-url.com',
            ],
        ]
    ]
);
```

## Example

Use `EscolaLms\PencilSpaces\Facades\PencilSpace` Facade for integration.

- Generate a direct login link for an API-managed user => `PencilSpace::getDirectLoginUrl(int $userId, string $redirectUrl = null)`
- Create Space => `PencilSpace::createSpace(CreatePencilSpaceResource $createSpaceResource)`

An account in Pencil Space will be created for users who don't have one. The table `pencil_space_accounts` will store the `userId` and `email` returned from Pencil Space.

## Endpoints

The endpoints are defined in [![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/Pencil-Spaces/)

## Tests

Run `./vendor/bin/phpunit` to run tests.
Test details [![codecov](https://codecov.io/gh/EscolaLMS/Pencil-Spaces/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/EscolaLMS/Pencil-Spaces)

You can use `PencilSpace::fake()` in your tests. Requests to the API will be mocked, and you will be able to test your feature.

## Listeners

This package doesn't listen for any events.
