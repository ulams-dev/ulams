<?php

return [
    /*
     * Base URL of the H5P service (api/h5p) as seen from Laravel, without the
     * /h5p path prefix. In docker-compose this is the internal service name.
     */
    'service_url' => env('H5P_SERVICE_URL', 'http://h5p:8080'),

    /*
     * Shared secret sent as X-Internal-Token on server-to-server calls. The
     * service treats such calls as the system user (all permissions). Must
     * match H5P_INTERNAL_TOKEN of the service.
     */
    'internal_token' => env('H5P_INTERNAL_TOKEN'),

    /*
     * Request timeout in seconds (uploads and exports of large packages).
     */
    'timeout' => (int) env('H5P_SERVICE_TIMEOUT', 300),
];
