<?php

return [

    'table_names' =>  [
        'user_table'   =>  'users',
        'scorm_table'   =>  'scorm',
        'scorm_sco_table'   =>  'scorm_sco',
        'scorm_sco_tracking_table'   =>  'scorm_sco_tracking',
    ],
    // Scorm directory. You may create a custom path in file system
    'disk'  =>   env('SCORM_DISK', 'local'),

    // Per-tenant content origin that serves packages and the SCORM player, e.g.
    // http://coffee.content.localhost (see api/docs/content-origin.md). Empty: the legacy player.
    'content_origin' => env('CONTENT_ORIGIN'),
    // Lifetime of the SCO-scoped tracking token handed to the content-origin player, seconds.
    'tracking_token_ttl' => (int) env('SCORM_TRACKING_TOKEN_TTL', 14400),
];
