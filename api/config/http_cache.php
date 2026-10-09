<?php

/*
 * Shared-cache headers for anonymous catalogue reads (App\Http\Middleware\PublicCatalogueCache).
 * A CDN or a caching proxy in front of the API may keep these responses for `s_maxage` seconds
 * per host (Vary: Host); browsers revalidate (max-age=0). Requests with credentials, and any
 * response that sets a cookie or is not a 200, stay `no-cache, private`.
 */
return [
    'enabled' => filter_var(env('HTTP_CACHE_PUBLIC', true), FILTER_VALIDATE_BOOLEAN),
    's_maxage' => (int) env('HTTP_CACHE_S_MAXAGE', 60),
    'stale_while_revalidate' => (int) env('HTTP_CACHE_STALE_WHILE_REVALIDATE', 300),

    // Request path patterns (Request::is) of the public catalogue.
    'paths' => [
        'api/config',
        'api/settings',
        'api/settings/*',
        'api/pages',
        'api/pages/*',
        'api/courses',
        'api/courses/*',
        'api/tutors',
        'api/tutors/*',
        'api/categories',
        'api/categories/*',
        'api/tags',
        'api/tags/*',
        'api/webinars',
        'api/webinars/*',
        'api/events',
        'api/stationary-events',
        'api/stationary-events/*',
        'api/products',
        'api/products/*',
        'api/consultations',
        'api/consultations/*',
    ],

    // Never public, even without credentials.
    'except' => [
        'api/courses/progress',
        'api/courses/progress/*',
        'api/courses/*/scorm',
        'api/consultations/me',
        'api/consultations/my-schedule',
        'api/consultations/*/schedule',
    ],
];
