<?php

use Ulams\Images\Enum\ConstantEnum;
use Ulams\Images\Enum\PackageStatusEnum;

return [
    /**
     * What imports and the topic-types markdown helper write into stored content for an image:
     * 'api'      a /api/images/img?path=... URL (default; today's behaviour),
     * 'original' the plain public storage URL of the file (the hosted demo tenants; the front and admin
     *            resize at display time with their own image service).
     * Read once per deployment (IMAGES_CONTENT_URLS), not per tenant. Existing /api/images/img URLs in
     * content keep working in both modes.
     */
    'content_urls' => env('IMAGES_CONTENT_URLS', 'api'),
    'private' => [
        'rate_limit_global' => ConstantEnum::RATE_LIMIT_GLOBAL,
        'rate_limit_per_ip' => ConstantEnum::RATE_LIMIT_PER_IP,
        'rate_limiter_status' => PackageStatusEnum::ENABLED,
    ],
    'public' => [
        /**
         * Arrays of allowed height and widths, leave empty if any size between min and max can be used
         */
        'allowed_heights' => [],
        'allowed_widths' => [],
        /**
         * Max and min sizes;
         * if min size is not set, and requested size is less or equal to 0 it will be ignored during croping/resizing
         * if max size is not set, original image max width/height will be the constraint;
         */
        'max_height' => 1000,
        'max_width' => 1000,
        'min_height' => 10,
        'min_width' => 10,
        /**
         *  Predefined image sizes, which can be requested by frontend instead of specyfing width/height of image
         */
        'size_definitions' => [
            'thumbnail' => [
                'w' => 400,
                'h' => 300
            ],
        ]
    ]
];
