# MailerLite


## What does it do

This package is used for integration with [MailerLite](https://www.mailerlite.com/) after dispatching events.

## Installing

- `composer require ulams/mailerlite`

## Example

You can set the package status and api key using the Facade
```php        
Config::set('ulams_mailer_lite.package_status', PackageStatusEnum::ENABLED);
Config::set('ulams_mailer_lite.api_key', '1234);
```
or [Settings package](https://github.com/EscolaLMS/settings)
```php 
$this->actingAs($this->user, 'api')->postJson(
    '/api/admin/config',
    [
        'config' => [
            [
                'key' => 'ulams_mailer_lite.package_status',
                'value' => PackageStatusEnum::ENABLED,
            ],
            [
                'key' => 'ulams_mailer_lite.api_key',
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
                'key' => 'ulams_mailer_lite.group_registered_group',
                'value' => 'registered users',
            ],
            [
                'key' => 'ulams_mailer_lite.group_order_paid',
                'value' => 'order paid',
            ],
            [
                'key' => 'ulams_mailer_lite.group_left_cart',
                'value' => 'left cart',
            ],
        ]
    ]
);
```

## Tests

Run `./vendor/bin/phpunit` to run tests.

Test details


## Listeners

Handling events
- `Ulams\Auth\Events\AccountConfirmed` => add to group of registered users (`ulams_mailer_lite.group_registered_group`)
- `Ulams\Cart\Events\ProductBought` => add to group of users with paid orders (`ulams_mailer_lite.group_order_paid`)
- `Ulams\Auth\Events\AccountBlocked` => remove from all groups
- `Ulams\Cart\Events\AbandonedCartEvent` => add to the group of users with abandoned carts (`ulams_mailer_lite.group_left_cart`)
- `Ulams\Cart\Events\OrderCreated` => remove from the group of users with abandoned carts (`ulams_mailer_lite.group_left_cart`)
