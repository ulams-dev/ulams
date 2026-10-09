<?php

return [
    // Tenant switch (ADR 0086, default pending #150): on by default, like SCORM. When off, launches
    // answer 404, the admin hides the type and existing topics show the text alternative.
    'enabled' => filter_var(env('INTERACTIVE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    // A package's manifest `network` allow-list is honoured only when this is true (default off).
    'allow_network' => filter_var(env('INTERACTIVE_ALLOW_NETWORK', false), FILTER_VALIDATE_BOOLEAN),
    // Disk for package files (default: FILESYSTEM_DRIVER, the tenant bucket). Served from the content
    // origin under interactive/ (api/docs/content-origin.md).
    'disk' => env('INTERACTIVE_DISK'),

    'manifest_file' => 'ulams-interactive.json',
    'max_manifest_bytes' => 256 * 1024,
    'max_steps' => 200,

    // SPDX ids accepted in the manifest
    'licences' => [
        'MIT', 'Apache-2.0', 'BSD-2-Clause', 'BSD-3-Clause', 'ISC', 'GPL-3.0-only', 'GPL-3.0-or-later',
        'CC-BY-4.0', 'CC-BY-SA-4.0', 'CC0-1.0', 'LicenseRef-Proprietary',
    ],

    // A package may contain these file types only (checked per zip entry before anything is stored)
    'allowed_extensions' => [
        'html', 'htm', 'js', 'mjs', 'css', 'json', 'map', 'txt', 'md', 'svg', 'png', 'jpg', 'jpeg', 'webp',
        'avif', 'gif', 'ico', 'woff', 'woff2', 'ttf', 'otf', 'mp3', 'ogg', 'wav', 'mp4', 'webm', 'glb',
        'gltf', 'bin', 'wasm', 'csv', 'tsv', 'geojson', 'topojson', 'xml',
    ],
    // Never accepted, whatever the allow-list says (checked on the file name)
    'denied_names' => ['/\.(php\d?|phtml|phar|svgz|htaccess|htpasswd|sh|exe|dll|so)$/i', '/(^|\/)\./'],

    'events' => [
        'max_batch' => 40,
        'throttle' => '60,1',
    ],
];
