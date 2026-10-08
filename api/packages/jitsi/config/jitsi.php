<?php

use Ulams\Jitsi\Enum\PackageStatusEnum;

return [

    'jitsi_host' => env('JITSI_HOST', 'meet-stage.ulams.app'),
    'app_id' => env('JITSI_APP_ID', 'meet-id'),
    'secret' => env('JITSI_APP_SECRET', 'Test'),

    'package_status' => PackageStatusEnum::ENABLED,

    'jaas_host' => env('JAAS_HOST', 'https://8x8.vc/'),
    'aud' => env('JAAS_AUD', 'jitsi'),
    'iss' => env('JAAS_ISS', 'chat'),
    'sub' => env('JAAS_SUB', ''),
    'kid' => env('JAAS_KEY_ID', ''),
    'private_key' => env('JAAS_PRIVATE_KEY', ''),
    'recording' => env('JAAS_RECORDING', false),

    /**
     * Recorded-video webhook (POST api/jitsi/recorded-video). Requests are rejected unless one of these is set:
     * - webhook_secret: JaaS webhook signing secret, checked against the X-Jaas-Signature header;
     * - webhook_token: shared token for a self-hosted Jitsi, sent as "Authorization: Bearer <token>".
     */
    'webhook_secret' => env('JITSI_WEBHOOK_SECRET'),
    'webhook_token' => env('JITSI_WEBHOOK_TOKEN'),
    'webhook_tolerance' => (int) env('JITSI_WEBHOOK_TOLERANCE', 300),

    /**
     * Recordings are only downloaded over https from these hosts (comma-separated; "*.example.com" matches
     * subdomains), never from private or loopback addresses, and up to recording_max_bytes.
     */
    'recording_hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('JITSI_RECORDING_HOSTS', ''))))),
    'recording_extensions' => ['mp4', 'webm'],
    'recording_max_bytes' => (int) env('JITSI_RECORDING_MAX_BYTES', 2 * 1024 * 1024 * 1024),
    'recording_download_timeout' => (int) env('JITSI_RECORDING_DOWNLOAD_TIMEOUT', 600),
];
