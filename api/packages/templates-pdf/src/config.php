<?php

return [
    /*
     * PDF renderer service (api/pdf, pdfme). Laravel is its only client: every
     * call carries X-Internal-Token, which must match PDF_INTERNAL_TOKEN of the
     * service. Nothing is sent outside the installation.
     */
    'pdf' => [
        'service_url' => env('PDF_SERVICE_URL', 'http://pdf:3000'),
        'internal_token' => env('PDF_INTERNAL_TOKEN'),
        'timeout' => (int) env('PDF_SERVICE_TIMEOUT', 30),
    ],

    /*
     * Rendered certificates are stored once (at creation) and served from the
     * disk afterwards; if storing fails they are rendered on download.
     * `disk` null means the default filesystem disk.
     */
    'storage' => [
        'disk' => env('PDF_STORAGE_DISK'),
        'directory' => env('PDF_STORAGE_DIRECTORY', 'pdfs'),
        'render_on_create' => (bool) env('PDF_RENDER_ON_CREATE', true),
    ],

    /*
     * Verification URL encoded in the certificate QR code (@VarCertificateVerifyUrl).
     * Placeholders: {APP_URL}, {FRONTEND_URL}, {id} (the certificate id).
     */
    /*
     * Public verification endpoint (GET /api/certificates/verify/{id}). `holder_name`: full, initials or
     * none; `throttle`: requests per minute and client.
     */
    'verify' => [
        'holder_name' => env('PDF_CERTIFICATE_VERIFY_NAME', 'full'),
        'throttle' => (int) env('PDF_CERTIFICATE_VERIFY_THROTTLE', 60),
    ],

    /*
     * Default certificate validity in days (0 = never expires). The compliance package overrides it per
     * course through CertificateValidityContract.
     */
    'validity_days' => (int) env('PDF_CERTIFICATE_VALIDITY_DAYS', 0),

    'verify_url' => env('PDF_CERTIFICATE_VERIFY_URL', '{APP_URL}/certificates/verify/{id}'),
];
