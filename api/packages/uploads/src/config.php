<?php

$mb = fn (string $name, int $default): int => (int) env($name, $default) * 1024 * 1024;

$zipMimes = ['application/zip', 'application/x-zip', 'application/x-zip-compressed'];

return [
    /*
     * Upload policies per kind. `max_size` is the size of the uploaded file in bytes. Archives are
     * also checked by the zip inspector with the `zip` limits before anything is extracted.
     * `mimes` are compared with the type sniffed from the content (finfo), never the client header.
     * The zip inspector runs for `.zip` uploads of policies with a `zip` profile.
     */
    'policies' => [
        'scorm' => [
            'extensions' => ['zip'],
            'mimes' => $zipMimes,
            'max_size' => $mb('UPLOADS_SCORM_MAX_MB', 512),
            'zip' => 'package',
        ],
        'cmi5' => [
            'extensions' => ['zip'],
            'mimes' => $zipMimes,
            'max_size' => $mb('UPLOADS_CMI5_MAX_MB', 512),
            'zip' => 'package',
        ],
        // Markdown, or a zip with Markdown and assets (packages/liascript)
        'liascript' => [
            'extensions' => ['md', 'markdown', 'zip'],
            'mimes' => [...$zipMimes, 'text/plain', 'text/markdown', 'text/x-markdown', 'text/x-c', 'text/x-c++', 'text/html', 'text/x-java'],
            'max_size' => $mb('UPLOADS_LIASCRIPT_MAX_MB', 128),
            'zip' => 'package',
        ],
        // Interactive packages (packages/interactive, ADR 0086): a zip with index.html, assets and a manifest
        'interactive' => [
            'extensions' => ['zip'],
            'mimes' => $zipMimes,
            'max_size' => $mb('UPLOADS_INTERACTIVE_MAX_MB', 50),
            'zip' => 'package',
        ],
        'course-import' => [
            'extensions' => ['zip'],
            'mimes' => $zipMimes,
            'max_size' => $mb('UPLOADS_COURSE_IMPORT_MAX_MB', 1024),
            'zip' => 'course-import',
        ],
    ],

    /*
     * Zip limits. `max_ratio` is the largest allowed uncompressed/compressed ratio of a single
     * entry (zip bombs compress at 1000:1 and more; real content rarely exceeds 20:1).
     */
    'zip' => [
        'package' => [
            'max_entries' => (int) env('UPLOADS_ZIP_MAX_ENTRIES', 5000),
            'max_uncompressed' => $mb('UPLOADS_ZIP_MAX_UNCOMPRESSED_MB', 2048),
            'max_entry_size' => $mb('UPLOADS_ZIP_MAX_ENTRY_MB', 1024),
            'max_ratio' => (int) env('UPLOADS_ZIP_MAX_RATIO', 200),
        ],
        'course-import' => [
            'max_entries' => (int) env('UPLOADS_COURSE_IMPORT_MAX_ENTRIES', 10000),
            // exports made before 2026-10 have a leading "/" on every entry
            'strip_leading_slash' => true,
            'max_uncompressed' => $mb('UPLOADS_COURSE_IMPORT_MAX_UNCOMPRESSED_MB', 4096),
            'max_entry_size' => $mb('UPLOADS_ZIP_MAX_ENTRY_MB', 1024),
            'max_ratio' => (int) env('UPLOADS_ZIP_MAX_RATIO', 200),
        ],
    ],

    /*
     * Virus scanning before extraction. `null` (default) accepts everything; `clamd` streams the
     * file to a clamd daemon (optional compose profile `av`, ClamAV runs as a separate process).
     */
    'scanner' => env('UPLOADS_SCANNER', 'null'),
    'clamd' => [
        'host' => env('UPLOADS_CLAMD_HOST', 'clamav'),
        'port' => (int) env('UPLOADS_CLAMD_PORT', 3310),
        'timeout' => (int) env('UPLOADS_CLAMD_TIMEOUT', 60),
        // reject uploads when clamd cannot be reached (true) or let them through with a warning
        'fail_closed' => filter_var(env('UPLOADS_CLAMD_FAIL_CLOSED', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
     * Files that a browser would render as active content (scripts run on the bucket origin) are
     * stored with `Content-Disposition: attachment` on S3 disks, except under the package
     * prefixes, which are served from the per-tenant content origin with its own CSP.
     */
    'attachment_extensions' => ['svg', 'svgz', 'html', 'htm', 'xhtml', 'xht', 'xml', 'xsl'],
    'package_prefixes' => ['scorm/', 'cmi5/', 'adapt/', 'liascript/', 'interactive/', 'h5p/'],

    /*
     * Tenant content origin (e.g. http://coffee.content.localhost), written to every tenant env file.
     * Package files are served to it by GET /api/content/<prefix>/<path> from the disk configured
     * under the config key named here (empty: the default disk). See api/docs/content-origin.md.
     */
    'content_origin' => env('CONTENT_ORIGIN'),
    'content_disks' => [
        'scorm' => 'scorm.disk',
        'cmi5' => 'ulams_cmi5.disk',
        'liascript' => 'ulams_liascript.disk',
        'interactive' => 'ulams_interactive.disk',
    ],
    // Package types that set response headers (the CSP) for their files themselves: prefix => class
    // implementing Ulams\Uploads\Http\ContentHeaderProvider. The generic CSP of the proxy only applies
    // to files that come back without one.
    'content_headers' => [
        'interactive' => \Ulams\Interactive\Services\InteractiveCsp::class,
    ],
];
