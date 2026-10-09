<?php

$list = fn (?string $value): array => array_values(array_filter(array_map(
    fn ($item) => strtolower(trim($item)),
    explode(',', (string) $value)
)));

return [
    /*
     * Slug of the tenant this process serves. Set in every `.env.<host>` written by
     * `ulams:tenant:create`; empty on the platform.
     */
    'tenant_slug' => env('TENANT_SLUG') ?: null,

    /*
     * Requests whose host is neither a platform host nor a provisioned tenant get a 404
     * instead of silently falling back to the platform `.env`.
     */
    'enforce_known_hosts' => filter_var(env('TENANCY_ENFORCE_HOSTS', true), FILTER_VALIDATE_BOOLEAN),
    'platform_hosts' => $list(env('TENANCY_PLATFORM_HOSTS', 'api.localhost,localhost,127.0.0.1,caddy,api')),

    /*
     * Naming of tenant resources. `{slug}` is replaced with the tenant slug.
     */
    'scheme' => env('TENANCY_SCHEME', 'http'),
    'api_host' => env('TENANCY_API_HOST', '{slug}.localhost'),
    'front_host' => env('TENANCY_FRONT_HOST', '{slug}.app.localhost'),
    'admin_host' => env('TENANCY_ADMIN_HOST', '{slug}.admin.localhost'),
    // Content origin: serves SCORM/cmi5/... packages and their players, nothing else (no cookies,
    // no API). Written as CONTENT_ORIGIN to the tenant env file. Production: a separate registrable
    // domain ({slug}.ulams-content.net, strongest) or a subdomain of the app's site
    // ({slug}.content.ulams.app, supported with the mitigations of api/docs/content-origin.md).
    'content_host' => env('TENANCY_CONTENT_HOST', '{slug}.content.localhost'),
    'email_domain' => env('TENANCY_EMAIL_DOMAIN', '{slug}.ulams.app'),
    'database' => env('TENANCY_DATABASE', 'ulams_{slug}'),
    'bucket' => env('TENANCY_BUCKET', 'ulams-{slug}'),
    'redis_prefix' => env('TENANCY_REDIS_PREFIX', 'ulams_{slug}_'),
    // Public base URL of the object store; the bucket name is appended.
    'storage_public_url' => env('TENANCY_STORAGE_PUBLIC_URL', 'http://storage.localhost'),

    /*
     * Password of every demo user created by `ulams:tenant:create`. DEV ONLY.
     */
    'demo_password' => env('TENANT_DEMO_PASSWORD', 'secret'),

    /*
     * Object store used to create tenant buckets (MinIO or S3).
     */
    's3' => [
        'endpoint' => env('TENANCY_S3_ENDPOINT', env('AWS_ENDPOINT')),
        'region' => env('TENANCY_S3_REGION', env('AWS_DEFAULT_REGION', 'us-east-1')),
        'key' => env('TENANCY_S3_KEY', env('AWS_ACCESS_KEY_ID')),
        'secret' => env('TENANCY_S3_SECRET', env('AWS_SECRET_ACCESS_KEY')),
        'use_path_style_endpoint' => filter_var(env('AWS_USE_PATH_STYLE_ENDPOINT', true), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
     * Connection with CREATEROLE/CREATEDB rights (see `pgsql_admin` in config/database.php).
     */
    'admin_connection' => env('TENANCY_ADMIN_CONNECTION', 'pgsql_admin'),

    'php_binary' => env('TENANCY_PHP_BINARY', PHP_BINARY ?: 'php'),
    'process_timeout' => (int) env('TENANCY_PROCESS_TIMEOUT', 900),

    /*
     * Production: directory with the least-privilege copy of the env files and Passport public
     * keys that the H5P service mounts instead of the whole API directory
     * (H5PServiceConfigExporter, `ulams:h5p:export-config`). Empty = not exported (development).
     */
    'h5p_service_config_dir' => env('H5P_SERVICE_CONFIG_DIR') ?: null,
];
