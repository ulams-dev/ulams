<?php

return [
    'disk' => env('VIDEO_DISK', config('filesystems.default')),
    'enable' => env('VIDEO_PROCESSING_ENABLE', true),
    'non_strict_value' => env('VIDEO_NON_STRICT_VALUE', false),
    'bitrates' => [
        [
            'kiloBitrate' => 250,
        ],
        [
            'kiloBitrate' => 500,
        ],
        [
            'kiloBitrate' => 1000,
        ]
    ],
    'queue' => env('VIDEO_QUEUE', 'queue-long-job'),
    'queue_connection' =>  env('VIDEO_QUEUE_CONNECTION', 'redis-long-job'),
];
