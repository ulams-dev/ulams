<?php

return [
    'images' => [
        // Largest image an admin can upload from the visual e-mail editor, in KB
        'max_kb'    => (int) env('TEMPLATES_EMAIL_IMAGE_MAX_KB', 2048),
        'directory' => 'email-images',
    ],
    'mjml' => [
        'api_url'          => env('MJML_API_URL', false),
        'use_api'          => env('MJML_USE_API', false),
        // Seconds; the render service is internal, so keep them short
        'connect_timeout'  => (int) env('MJML_CONNECT_TIMEOUT', 5),
        'timeout'          => (int) env('MJML_TIMEOUT', 20),
        //
        'binary_path'      => env('MJML_BINARY_PATH'),
        //
        'api_id'           => env('MJML_API_ID'),
        'api_secret'       => env('MJML_API_SECRET'),
        //
        'default_template' => <<<MJML_TEMPLATE
                                <mjml>
                                <mj-body>
                                    <mj-section background-color="white">
                                        <mj-column>
                                            @VarTemplateContent
                                        </mj-column>
                                    </mj-section>
                                </mj-body>
                                </mjml>
                                MJML_TEMPLATE,

    ]
];
