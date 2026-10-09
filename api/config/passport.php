<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Passport Guard
    |--------------------------------------------------------------------------
    |
    | The guard Passport uses when authenticating users for its own
    | authorization routes. Tokens are issued by the auth package
    | (personal access tokens), so this is the framework default.
    |
    */

    'guard' => 'web',

    /*
    |--------------------------------------------------------------------------
    | Encryption Keys
    |--------------------------------------------------------------------------
    |
    | Passport uses encryption keys while generating secure access tokens for
    | your application. By default, the keys are stored as local files but
    | can be set via environment variables when that is more convenient.
    | Key files must be mode 600/640/660 (Passport 13 validates them).
    |
    */

    'private_key' => env('PASSPORT_PRIVATE_KEY'),

    'public_key' => env('PASSPORT_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Passport Database Connection
    |--------------------------------------------------------------------------
    |
    | By default, Passport's models use the application's default database
    | connection. Client ids are UUIDs (Passport 13 default). Personal access
    | tokens are issued by the newest client with the `personal_access` grant
    | (`php artisan passport:client --personal`).
    |
    */

    'connection' => env('PASSPORT_CONNECTION'),

];
