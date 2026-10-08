<?php

return [
    'mimes' => env('FILES_MIMES', 'avi,mov,mp3,mp4,wmv,pdf,doc,docx,ppt,png,jpg,jpeg,gif,webp,svg,xlsx,zip'),
    // largest single upload in kilobytes (PHP accepts bodies up to 2 GB)
    'max_size_kb' => (int) env('FILES_MAX_SIZE_MB', 512) * 1024,
];
