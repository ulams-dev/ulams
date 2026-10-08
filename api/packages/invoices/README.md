# Invoices

Package for generate pdf invoice from order


## What does it do

Renders a PDF invoice for a cart order (`GET /api/order-invoices/{id}`). The invoice model
(`Ulams\Invoices\Invoice\Invoice`, with `InvoiceParty` and `InvoiceItem`) is part of this package;
the Blade view `invoices::templates.invoice` is rendered to PDF with
[barryvdh/laravel-dompdf](https://github.com/barryvdh/laravel-dompdf).

Line totals: price × quantity, minus the line discount (an amount), plus tax (a percentage of the
discounted amount), each rounded to `currency.decimals`. The invoice shows per-line discount and tax
columns only when some line has them, and the total discount, total taxes and total amount.

## Installing

- `composer require ulams/invoices`

## Config

``` php
return [
    'date' => [
        /*
         * Carbon date format
         */
        'format' => 'd-m-Y',
        /*
         * Due date for payment since invoice's date.
         */
        'pay_until_days' => 7,
    ],

    'serial_number' => [
        'series'   => 'AA',
        'sequence' => 1,
        /*
         * Sequence will be padded accordingly, for ex. 00001
         */
        'sequence_padding' => 5,
        'delimiter'        => '.',
        /*
         * Supported tags {SERIES}, {DELIMITER}, {SEQUENCE}
         * Example: AA.00001
         */
        'format' => '{SERIES}{DELIMITER}{SEQUENCE}',
    ],

    'currency' => [
        'code' => 'PLN',
        /*
         * Usually cents
         * Used when spelling out the amount and if your currency has decimals.
         *
         * Example: Amount in words: Eight hundred fifty thousand sixty-eight EUR and fifteen ct.
         */
        'fraction' => 'gr',
        'symbol'   => 'zł',
        /*
         * Example: 19.00
         */
        'decimals' => 2,
        /*
         * Example: 1.99
         */
        'decimal_point' => ',',
        /*
         * By default empty.
         * Example: 1,999.00
         */
        'thousands_separator' => ' ',
        /*
         * Supported tags {VALUE}, {SYMBOL}, {CODE}
         * Example: 1.99 €
         */
        'format' => '{VALUE} {SYMBOL}',
    ],

    'paper' => [
        // A4 = 210 mm x 297 mm = 595 pt x 842 pt
        'size'        => 'a4',
        'orientation' => 'portrait',
    ],

    'disk' => 'local',

    /*
     * Logo: an absolute path or a path relative to public/. Empty or missing file: no logo.
     */
    'logo' => env('INVOICES_LOGO'),

    'seller' => [
        'attributes' => [
            'name'          => 'Ulams',
            'address'       => 'Chłodna 22A, 00-891 Warszawa',
            'code'          => '00-891',
            'vat'           => '123456789',
            'phone'         => '123456789',
            'custom_fields' => [
                /*
                 * Extra lines in the seller section: label => value
                 */
                'SWIFT' => 'BANK101',
            ],
        ],
    ],
];
```

## Example or Tutorial


## Endpoints


## Tests

Run `./vendor/bin/phpunit` to run tests. See [tests](https://github.com/EscolaLMS/Invoices/tree/main/tests) folder as it's quite good staring point as documentation appendix.

Test details [![codecov](https://codecov.io/gh/Ulams/Invoices/branch/main/graph/badge.svg?token=O91FHNKI6R)](https://codecov.io/gh/Ulams/Invoices) [![Tests PHPUnit in environments](https://github.com/EscolaLMS/Invoices/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/Invoices/actions/workflows/test.yml)

## Events

This package does not dispatch any events.

## Listeners

This package does not listen for any events.

## Permissions

This package does not define new permissions but uses the `cart_order_list` permission from the [Cart](https://github.com/EscolaLMS/Cart) package.
