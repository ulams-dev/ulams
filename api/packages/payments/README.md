# Payments

[![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/payments/)
[![codecov](https://codecov.io/gh/EscolaLMS/payments/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/EscolaLMS/payments)
[![phpunit](https://github.com/EscolaLMS/payments/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/payments/actions/workflows/test.yml)
[![downloads](https://img.shields.io/packagist/dt/escolalms/payments)](https://packagist.org/packages/escolalms/payments)
[![downloads](https://img.shields.io/packagist/v/escolalms/payments)](https://packagist.org/packages/escolalms/payments)
[![downloads](https://img.shields.io/packagist/l/escolalms/payments)](https://packagist.org/packages/escolalms/payments)
[![Maintainability](https://api.codeclimate.com/v1/badges/e42a94f20c76b719fc38/maintainability)](https://codeclimate.com/github/EscolaLMS/payments/maintainability)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FEscolaLMS%2Fpayments%2Fmain)](https://dashboard.stryker-mutator.io/reports/github.com/EscolaLMS/payments/main)

## Purpose

This package lets you create Payments and process them using integrations with external payment providers (gateways).

## Dependencies

- Stripe integration is based on `league/omnipay` and `omnipay/stripe` packages.
- Przelewy24 integration is based on `mnastalski/przelewy24-php` package.
- Optional integration with `escolalms/settings` package enables changing payment gateway api keys & secrets using Settings API (and Admin Panel).

## Installation

- `composer require escolalms/payments`
- `php artisan migrate`
- `php artisan db:seed --class="EscolaLms\Cart\Database\Seeders\CartPermissionSeeder"`

## Usage

### Facades

#### Payments Facade

Use `EscolaLms\Payments\Facades\Payments` for starting payment processing.
You can create PaymentProcessor` either from a model using Payable trait or from precreated Payment object.

```php
use EscolaLms\Cart\Models\Cart;
use EscolaLms\Payments\Dtos\PaymentMethodDto;
use EscolaLms\Payments\Facades\Payments;

$payable = Cart::find($id); // Cart must implement Payable interface and use Payable trait
$paymentMethodDto = PaymentMethodDto::instantiateFromRequest($request);
$processor = Payments::processPayment($payable);
$processor->purchase($paymentMethodDto); // will emit PaymentPaid event on success
if($payment->status->is(PaymentStatus::PAID)){
    // ...
}
```

#### PaymentGateway Facade

With `EscolaLms\Payments\Facades\PaymentGateway` you can call payment provider gateways directly.

For existing payment you can for example do:

```php
use EscolaLms\Payments\Dtos\PaymentMethodDto;
use EscolaLms\Payments\Facades\PaymentGateway;
use EscolaLms\Payments\Models\Payment;

$payment = Payment::find($id);
$paymentMethodDto = PaymentMethodDto::instantiateFromRequest($request);
$paymentDto = PaymentDto::instantiateFromPayment($payment); // or you can create it manually
PaymentGateway::purchase($paymentDto, $paymentMethodDto); // will use default payment driver
```

**Important**: This will not save `Payment` object.

To use specific driver, you can call

```php
PaymentGateway::driver('stripe')->purchase($paymentDto, $paymentMethodDto);
```

#### Available payment drivers

- **stripe** (using `Stripe Payment Intent`)
- **free**
- **przelewy24**
- TODO: _stripe-checkout_

### Payable Trait & Interface

`Payable` trait and interface are the core of this package, enabling simplified calling of `PaymentsService` and `GatewayManager`.
When you include it in your model that represents a `Payable` (for example `Cart` or `Order` or `Product`) you can begin payment processing for that `Payable` by calling `$payable->process()`
which calls `Payments::processPayable($this)` and automatically creates a `Payment` and returns a `PaymentProcessor` instance for that Payment.

`EscolaLms\Cart` package uses this trait and interface in `EscolaLms\Cart\Models\Order`.

### Payment Processor

`EscolaLms\Payments\Entities\PaymentProcessor` is a special class which wraps around `Payment`
and contains functionality related to processing that payment, for example generating links to payment gateways, automatically setting payment status after purchase, emiting events related to payment status, etc.

```php
use EscolaLms\Payments\Dtos\PaymentMethodDto;
use EscolaLms\Payments\Entities\PaymentProcessor;
use EscolaLms\Payments\Models\Payment;

$payment = Payment::find($id);
$paymentMethodDto = PaymentMethodDto::instantiateFromRequest($request);
$processor = new PaymentProcessor($payment); // instead of using Payments facade
$processor->purchase($paymentMethodDto);
```

`PaymentProcessor` automatically selects `free` driver when payment amount equals 0.

### Payment Model

This package defines a `EscolaLms\Payments\Models\Payment` which contains all data abount given payment required for payment gateways to work.

## Endpoints

All the endpoints are defined in [![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/payments/).

## Tests

Run `./vendor/bin/phpunit` to run tests. See [tests/Mocks/Payable](tests/Mocks/Payable.php) as an example how a Payable is defined.

Test details: [![codecov](https://codecov.io/gh/EscolaLMS/Files/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/EscolaLMS/payments)
[![phpunit](https://github.com/EscolaLMS/payments/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/payments/actions/workflows/test.yml)

## Events

- `EscolaLms\Payments\Events\PaymentCancelled` - - emited after payment processing is cancelled (by user action or possibly by timeout sent from payment gateway)
- `EscolaLms\Payments\Events\PaymentFailed` - emited after payment has failed (payment gateway returns error)
- `EscolaLms\Payments\Events\PaymentRegistered` - emited when new Payment is created
- `EscolaLms\Payments\Events\PaymentSuccess` - emited when payment gateway returns success

## Listeners

No Listeners are defined in this package.

## How to use this package on Frontend

### Admin Panel

#### **Left Menu**

![Admin panel menu](docs/menu.png "Admin panel menu")

#### **List of Payments**

![List of Payments](docs/list.png "List of Payments")

## Permissions

Permissions are defined in [Enum](src/Enums/CartPermissionsEnum.php) and seeded in [Seeder](database/seeders/CartPermissionSeeder.php).

## Roadmap. Todo. Troubleshooting

- ???
