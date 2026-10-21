# Templates-Email

Package for email notifications with editable templates (for important user-related events)


## Purpose

This package allows you to create email notifications for events emitted in Laravel app.

Each notification is created as a class describing available variables that can be used in Template (which will be created in database and editable through admin panel). This class must be registered using Template facade from Template package, where you specify which Event it is associated with and which Channel it is sent through (e.g. Email channel when defined in this package).

## Installing

- `composer require ulams/templates-email`
- `php artisan db:migrate`
- `php artisan db:seed --class="Ulams\TemplatesEmail\Database\Seeders\TemplatesEmailSeeder"`

## Dependencies

- [MJML](https://mjml.io/) - either as npm package ([Download](https://mjml.io/download)) or using API ([Register](https://mjml.io/api))

## Usage

### Defining Templates

1. Create event which triggers sending email using specified template. This event must implement method getUser() returning User model from LMS Core package.
2. Create Class defining template variables, which you will use in email notification,
3. Associate your class describing template variables with event and channel through which notifications should be sent. Use `Ulams\Templates\Facades\Template::register(Event class, Ulams\TemplatesEmail\Core\EmailChannel::class, Variable class);`
4. Register template in db for admin panel or used `/api/admin/templates`, better described in [Template package](https://github.com/EscolaLMS/Templates)

### Mjml (pre)rendering

1. Emails are written using MJML, which then is prerendered into HTML when saving a Template (so that sending an email does not call renderer for each message).
2. To render MJML you need to either:
    - install `mjml` npm package and configure path to executable binary in this package config `binary_path` key
    - register to MJML API and configure api id & secret in this package config file
    - calls to the MJML HTTP service (`MJML_API_URL`) use a connect timeout of `MJML_CONNECT_TIMEOUT` (5 s) and a total timeout of `MJML_TIMEOUT` (20 s), do not follow redirects, and log or raise the error list the service returns

### Draft preview and test send

`POST /api/admin/templates/email/render` renders an unsaved draft (`event`, `title`, `content`, optional
`sample_overrides`) with the `mockedVariables()` of the event's variable class and returns
`{title, html, errors, missing_variables, warnings}`. `POST /api/admin/templates/email/test-send` does the same and
sends the e-mail (subject prefixed `[Test]`) only to the signed-in user. Both need `template_update`, are throttled
(30 and 5 per minute per user) and store nothing. `errors` are the MJML errors of the render service; `warnings` come
from `Ulams\TemplatesEmail\Accessibility\EmailA11yChecker` (missing image alt, no `lang`, no `<title>`, links without
text, layout tables without `role="presentation"`, low contrast of inline colours) and never fail the request.
Content is treated as MJML when it contains a `<mjml>` element, with or without attributes (`<mjml lang="en">`).

### Variable values are escaped

Variable values are HTML-escaped when they are put into the `contentHtml` section, so a user named
`<script>` cannot inject markup. The subject (a text section) receives the raw value. A variable that
is intentionally HTML (a link or button block, a table) must be returned from
`variablesFromEvent()` as an `Illuminate\Support\HtmlString` (any `Htmlable`); it is inserted as is,
so build it from escaped parts (Blade `{{ }}` or `e()`). Markdown global settings are marked this way
by `SettingsVariables::getHtmlSettingsValues()`.

## Tests

Run `./vendor/bin/phpunit --filter 'Ulams\\TemplatesEmail\\Tests'` to run tests. See [tests](https://github.com/EscolaLMS/Templates-Email/tree/main/tests) folder as it contains a basic implementation of Variable (or Template description) class with minimal customisation - a quite good starting point for creating your own.

Test details [![codecov](https://codecov.io/gh/Ulams/Templates-Email/branch/main/graph/badge.svg?token=O91FHNKI6R)](https://codecov.io/gh/Ulams/Templates-Email) [![Tests PHPUnit in environments](https://github.com/EscolaLMS/Templates-Email/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/Templates-Email/actions/workflows/test.yml)

## Permissions

No Permissions defined for this package.
