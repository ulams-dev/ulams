<?php

return [
    /*
     * Default greeting. Registered as an administrable setting (Providers/SettingsServiceProvider),
     * so an admin can change it per tenant with POST /api/admin/config.
     */
    'greeting' => env('EXAMPLE_PLUGIN_GREETING', 'Hello from the example plugin'),
];
