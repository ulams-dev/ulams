<?php

return [
    // Package files live on this disk and are served from the tenant content origin
    // (GET /api/content/cmi5/...). Follows SCORM_DISK (the tenant bucket in production);
    // `php artisan cmi5:move-to-bucket` copies packages that were uploaded to `local`.
    'disk' => env('CMI5_DISK', env('SCORM_DISK', 'local')),
];
