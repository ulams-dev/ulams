<?php

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
        'code' => env('PAYMENTS_DEFAULT_CURRENCY', 'USD'),
        /*
         * Usually cents
         * Used when spelling out the amount and if your currency has decimals.
         *
         * Example: Amount in words: Eight hundred fifty thousand sixty-eight EUR and fifteen ct.
         */
        'fraction' => env('INVOICES_CURRENCY_FRACTION', 'ct.'),
        'symbol'   => env('INVOICES_CURRENCY_SYMBOL', '$'),
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
     * Logo printed at the top of the invoice: an absolute path or a path relative to the
     * public directory. Leave empty for no logo; a missing file is skipped.
     */
    'logo' => env('INVOICES_LOGO'),

    'seller' => [
        /*
         * Seller printed on every invoice.
         */
        'attributes' => [
            'name'          => 'Ulams',
            'address'       => 'Chłodna 22A, 00-891 Warszawa',
            'code'          => '41-1985581',
            'vat'           => '123456789',
            'phone'         => '760-355-3930',
            'custom_fields' => [
                /*
                 * Extra lines in the seller section: label => value
                 */
                'SWIFT' => 'BANK101',
            ],
        ],
    ],
];
