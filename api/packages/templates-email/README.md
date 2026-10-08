# Templates-Email

Package for email notifications with editable templates (for important user-related events)

[![codecov](https://codecov.io/gh/EscolaLMS/Templates-Email/branch/main/graph/badge.svg?token=O91FHNKI6R)](https://codecov.io/gh/EscolaLMS/Templates-Email)
[![Tests PHPUnit in environments](https://github.com/EscolaLMS/Templates-Email/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/Templates-Email/actions/workflows/test.yml)
[![Maintainability](https://api.codeclimate.com/v1/badges/7d61484f7610611183ff/maintainability)](https://codeclimate.com/github/EscolaLMS/Templates-Email/maintainability)
[![Test Coverage](https://api.codeclimate.com/v1/badges/7d61484f7610611183ff/test_coverage)](https://codeclimate.com/github/EscolaLMS/Templates-Email/test_coverage)
[![downloads](https://img.shields.io/packagist/dt/escolalms/templates-email)](https://packagist.org/packages/escolalms/templates-email)
[![downloads](https://img.shields.io/packagist/v/escolalms/templates-email)](https://packagist.org/packages/escolalms/templates-email)
[![downloads](https://img.shields.io/packagist/l/escolalms/templates-email)](https://packagist.org/packages/escolalms/templates-email)

## Purpose

This package allows you to create email notifications for events emitted in Laravel app.

Each notification is created as a class describing available variables that can be used in Template (which will be created in database and editable through admin panel). This class must be registered using Template facade from Template package, where you specify which Event it is associated with and which Channel it is sent through (e.g. Email channel when defined in this package).

## Installing

- `composer require escolalms/templates-email`
- `php artisan db:migrate`
- `php artisan db:seed --class="EscolaLms\TemplatesEmail\Database\Seeders\TemplatesEmailSeeder"`

## Dependencies

- [MJML](https://mjml.io/) - either as npm package ([Download](https://mjml.io/download)) or using API ([Register](https://mjml.io/api))

## Usage

### Defining Templates

1. Create event which triggers sending email using specified template. This event must implement method getUser() returning User model from LMS Core package.
2. Create Class defining template variables, which you will use in email notification,
3. Associate your class describing template variables with event and channel through which notifications should be sent. Use `EscolaLms\Templates\Facades\Template::register(Event class, EscolaLms\TemplatesEmail\Core\EmailChannel::class, Variable class);`
4. Register template in db for admin panel or used `/api/admin/templates`, better described in [Template package](https://github.com/EscolaLMS/Templates)

### Mjml (pre)rendering

1. Emails are written using MJML, which then is prerendered into HTML when saving a Template (so that sending an email does not call renderer for each message).
2. To render MJML you need to either:
    - install `mjml` npm package and configure path to executable binary in this package config `binary_path` key
    - register to MJML API and configure api id & secret in this package config file

## Tests

Run `./vendor/bin/phpunit --filter 'EscolaLms\\TemplatesEmail\\Tests'` to run tests. See [tests](https://github.com/EscolaLMS/Templates-Email/tree/main/tests) folder as it contains a basic implementation of Variable (or Template description) class with minimal customisation - a quite good starting point for creating your own.

Test details [![codecov](https://codecov.io/gh/EscolaLMS/Templates-Email/branch/main/graph/badge.svg?token=O91FHNKI6R)](https://codecov.io/gh/EscolaLMS/Templates-Email) [![Tests PHPUnit in environments](https://github.com/EscolaLMS/Templates-Email/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/Templates-Email/actions/workflows/test.yml)

## Permissions

No Permissions defined for this package.
