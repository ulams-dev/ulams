# MailerLite

[![codecov](https://codecov.io/gh/EscolaLMS/MailerLite/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/EscolaLMS/MailerLite)
[![phpunit](https://github.com/EscolaLMS/MailerLite/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/MailerLite/actions/workflows/test.yml)
[![downloads](https://img.shields.io/packagist/dt/escolalms/mailerlite)](https://packagist.org/packages/escolalms/mailerlite)
[![downloads](https://img.shields.io/packagist/v/escolalms/mailerlite)](https://packagist.org/packages/escolalms/mailerlite)
[![downloads](https://img.shields.io/packagist/l/escolalms/mailerlite)](https://packagist.org/packages/escolalms/mailerlite)
[![Maintainability](https://api.codeclimate.com/v1/badges/00725c6ea461fcfa2754/maintainability)](https://codeclimate.com/github/EscolaLMS/MailerLite/maintainability)
[![Test Coverage](https://api.codeclimate.com/v1/badges/00725c6ea461fcfa2754/test_coverage)](https://codeclimate.com/github/EscolaLMS/MailerLite/test_coverage)

## What does it do

This package is used for integration with [MailerLite](https://www.mailerlite.com/) after dispatching events.

## Installing

- `composer require escolalms/mailerlite`

## Example

You can set the package status and api key using the Facade
```php        
Config::set('escolalms_mailer_lite.package_status', PackageStatusEnum::ENABLED);
Config::set('escolalms_mailer_lite.api_key', '1234);
```
or [Settings package](https://github.com/EscolaLMS/settings)
```php 
$this->actingAs($this->user, 'api')->postJson(
    '/api/admin/config',
    [
        'config' => [
            [
                'key' => 'escolalms_mailer_lite.package_status',
                'value' => PackageStatusEnum::ENABLED,
            ],
            [
                'key' => 'escolalms_mailer_lite.api_key',
                'value' => '1234',
            ],
        ]
    ]
);
```

Group names are also configurable.

```php
$this->actingAs($this->user, 'api')->postJson(
    '/api/admin/config',
    [
        'config' => [
            [
                'key' => 'escolalms_mailer_lite.group_registered_group',
                'value' => 'registered users',
            ],
            [
                'key' => 'escolalms_mailer_lite.group_order_paid',
                'value' => 'order paid',
            ],
            [
                'key' => 'escolalms_mailer_lite.group_left_cart',
                'value' => 'left cart',
            ],
        ]
    ]
);
```

## Tests

Run `./vendor/bin/phpunit` to run tests.

Test details
[![codecov](https://codecov.io/gh/EscolaLMS/MailerLite/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/EscolaLMS/MailerLite)
[![phpunit](https://github.com/EscolaLMS/MailerLite/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/MailerLite/actions/workflows/test.yml)


## Listeners

Handling events
- `EscolaLms\Auth\Events\AccountConfirmed` => add to group of registered users (`escolalms_mailer_lite.group_registered_group`)
- `EscolaLms\Cart\Events\ProductBought` => add to group of users with paid orders (`escolalms_mailer_lite.group_order_paid`)
- `EscolaLms\Auth\Events\AccountBlocked` => remove from all groups
- `EscolaLms\Cart\Events\AbandonedCartEvent` => add to the group of users with abandoned carts (`escolalms_mailer_lite.group_left_cart`)
- `EscolaLms\Cart\Events\OrderCreated` => remove from the group of users with abandoned carts (`escolalms_mailer_lite.group_left_cart`)
