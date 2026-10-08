<?php

return [
    // Disk for assets (default: FILESYSTEM_DRIVER, the tenant bucket). Served from the content
    // origin under liascript/ (api/docs/content-origin.md).
    'disk' => env('LIASCRIPT_DISK'),
    'max_markdown_bytes' => (int) env('LIASCRIPT_MAX_MARKDOWN_KB', 2048) * 1024,
];
