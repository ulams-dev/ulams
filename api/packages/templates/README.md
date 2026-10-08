# Templates

[![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/Templates/)
[![codecov](https://codecov.io/gh/EscolaLMS/Templates/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/EscolaLMS/Templates)
[![phpunit](https://github.com/EscolaLMS/Templates/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/Templates/actions/workflows/test.yml)
[![downloads](https://img.shields.io/packagist/dt/escolalms/templates)](https://packagist.org/packages/escolalms/templates)
[![downloads](https://img.shields.io/packagist/v/escolalms/templates)](https://packagist.org/packages/escolalms/templates)
[![downloads](https://img.shields.io/packagist/l/escolalms/templates)](https://packagist.org/packages/escolalms/templates)

## Purpose

General purpose of this package is to store various templates in database and assigning them to Events so that content based on these templates is automatically generated and/or sent to users.

## Usage

Each template is defined by

- `channel`: class defining how the template is handled
- `event`: event to which the template is assigned

For every channel & event pair a single Variables definition is registered, which contains tokens that can be used in the template and replaced with values based on the data from the Event.

Analysing these three example files in [tests](tests/Mock):

- TestChannel.php
- TestVariables.php
- TestEventWithGetters.php

and looking at the `Template` facade is simplest way to understand how this package works.

### Facade

There is a `Template` facade declared, which is used to register Event-Channel-Variable sets and can be used in testing (as it can be replaced with a fake using `Template::fake()`).

To register Event-Channel-Variable set, `Template::register($eventClass, $channelClass, $variableClass)` must be called, where:

- `$eventClass` can be any class that is dispatched as an event in any EscolaLms package
- `$channelClass` must be a class implementing `TemplateChannelContract` interface declared in this package
- `$variableClass` must be a class implementing `TemplateVariableContract` interface declared in this package

### Channels & variables

This package has no Channels or Variables defined, everything should be created in separate packages.

- [Templates-Email](https://github.com/EscolaLMS/Templates-Email)
- [Templates-Certificates](https://github.com/EscolaLMS/Templates-Certificates)
- [Templates-Pdf](https://github.com/EscolaLMS/Templates-Pdf)

### Event Wrapper

`EscolaLms\Templates\Events\EventWrapper` is a class that wraps any event before trying to generate notification for it (and feed data from it to Template). This class contains methods for extracting necessary data from Event if that data is not available directly (for example User to which notification is being sent, and any other data that is required by registered Template).

## Endpoints

All the endpoints are defined in [![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/templates/)

## Tests

Run `./vendor/bin/phpunit --filter 'EscolaLms\\Templates\\Tests'` to run tests. See [tests](tests) folder as it's quite good staring point as documentation appendix.

Test details:
[![codecov](https://codecov.io/gh/EscolaLMS/Templates/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/EscolaLMS/Templates)
[![phpunit](https://github.com/EscolaLMS/Templates/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/Templates/actions/workflows/test.yml)

## Listeners

- `EscolaLms\Templates\Listeners\TemplateEventListener` this listeners listens to all `EscolaLms` namespace events, checks if event is registered for any Template, and passes the event wrapped in `EventWrapper` to processing (so if event is registered and has corresponding Template, notification using that Template will be sent)

## Usage on front end

### Admin panel

These screens will only be available if at least one Template is registered (for example any package implementing Channel and Variables is installed).

#### **Left menu**

![Menu](docs/menu.png "Menu")

#### **List of templates**

![List of templates](docs/list.png "List of templates")

#### **Creating/editing template**

![Creating/editing template](docs/edit.png "Creating or editing template")

## Permissions

Permissions are defined in [Enum](src/Enums/TemplatesPermissionsEnum.php) and seeded in [Seeder](database/seeders/PermissionTableSeeder.php).

## Roadmap. Todo. Troubleshooting

- ???
