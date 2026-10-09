<?php

return [
    'default' => 'default',
    'documentations' => [
        'default' => [
            'api' => [
                'title' => 'Ulams LMS',
            ],
            'routes' => [
                /*
                 * Route for accessing api documentation interface
                */
                'api' => 'api/documentation',
            ],
            'paths' => [
                /*
                 * File name of the generated json documentation file
                */
                'docs_json' => 'api-docs.json',

                /*
                 * File name of the generated YAML documentation file
                */
                'docs_yaml' => 'api-docs.yaml',

                /*
                * Set this to `json` or `yaml` to determine which documentation file to use in UI
                */
                'format_to_use_for_docs' => env('L5_FORMAT_TO_USE_FOR_DOCS', 'json'),

                /*
                 * Absolute paths to directory containing the swagger annotations are stored.
                */
                'annotations' => [
                    app_path(),
                    base_path('packages/auth/src'),
                    base_path('packages/cart/src'),
                    base_path('packages/categories/src'),
                    base_path('packages/courses/src'),
                    base_path('packages/courses-import-export/src'),
                    base_path('packages/core/src'),
                    base_path('packages/csv-users/src'),
                    base_path('packages/files/src'),
                    base_path('packages/h5p/src'),
                    base_path('packages/lrs/src'),
                    base_path('packages/lti/src'),
                    base_path('packages/liascript/src'),
                    base_path('packages/interactive/src'),
                    base_path('packages/adapt/src'),
                    base_path('packages/notifications/src'),
                    base_path('packages/mattermost/src'),
                    base_path('packages/model-fields/src'),
                    base_path('packages/pages/src'),
                    base_path('packages/payments/src'),
                    base_path('packages/permissions/src'),
                    base_path('packages/tags/src'),
                    base_path('packages/scorm/src'),
                    base_path('packages/settings/src'),
                    base_path('packages/stationary-events/src'),
                    base_path('packages/reports/src'),
                    base_path('packages/templates/src'),
                    base_path('packages/templates-pdf/src'),
                    base_path('packages/assign-without-account/src'),
                    base_path('packages/translations/src'),
                    base_path('packages/demo/src'),
                    base_path('packages/questionnaire/src'),
                    base_path('packages/consultations/src'),
                    base_path('packages/consultation-access/src'),
                    base_path('packages/webinar/src'),
                    base_path('packages/tenancy/src'),
                    base_path('packages/cmi5/src'),
                    base_path('packages/vouchers/src'),
                    base_path('packages/invoices/src'),
                    base_path('packages/video/src'),
                    base_path('packages/course-access/src'),
                    base_path('packages/course-builder/src'),
                    base_path('packages/living-course/src'),
                    base_path('packages/tasks/src'),
                    base_path('packages/bookmarks_notes/src'),
                    base_path('packages/topic-type-layout/src'),
                    base_path('packages/topic-type-project/src'),
                    base_path('packages/topic-type-gift/src'),
                    base_path('packages/bulk-notifications/src'),
                    base_path('packages/dictionaries/src'),
                ],
            ],
        ],
    ],
    'defaults' => [
        'routes' => [
            /*
             * Route for accessing parsed swagger annotations.
            */
            'docs' => 'docs',

            /*
             * Route for Oauth2 authentication callback.
            */
            'oauth2_callback' => 'api/oauth2-callback',

            /*
             * Middleware allows to prevent unexpected access to API documentation
            */
            'middleware' => [
                'api' => [],
                'asset' => [],
                'docs' => [],
                'oauth2_callback' => [],
            ],

            /*
             * Route Group options
            */
            'group_options' => [],
        ],

        'paths' => [
            /*
             * Absolute path to location where parsed annotations will be stored
            */
            'docs' => storage_path('api-docs'),

            /*
             * Absolute path to directory where to export views
            */
            'views' => base_path('resources/views/vendor/l5-swagger'),

            /*
             * Edit to set the api's base path
            */
            'base' => env('L5_SWAGGER_BASE_PATH', null),

            /*
             * Edit to set path where swagger ui assets should be stored
            */
            'swagger_ui_assets_path' => env('L5_SWAGGER_UI_ASSETS_PATH', 'vendor/swagger-api/swagger-ui/dist/'),

            /*
             * Absolute path to directories that should be exclude from scanning
            */
            'excludes' => [],
        ],

        /*
         * API security definitions. Will be generated into documentation file.
        */
        'securityDefinitions' => [
            'securitySchemes' => [
                /*
                 * Examples of Security schemes
                */
                /*
                'api_key_security_example' => [ // Unique name of security
                    'type' => 'apiKey', // The type of the security scheme. Valid values are "basic", "apiKey" or "oauth2".
                    'description' => 'A short description for security scheme',
                    'name' => 'api_key', // The name of the header or query parameter to be used.
                    'in' => 'header', // The location of the API key. Valid values are "query" or "header".
                ],
                'oauth2_security_example' => [ // Unique name of security
                    'type' => 'oauth2', // The type of the security scheme. Valid values are "basic", "apiKey" or "oauth2".
                    'description' => 'A short description for oauth2 security scheme.',
                    'flow' => 'implicit', // The flow used by the OAuth2 security scheme. Valid values are "implicit", "password", "application" or "accessCode".
                    'authorizationUrl' => 'http://example.com/auth', // The authorization URL to be used for (implicit/accessCode)
                    //'tokenUrl' => 'http://example.com/auth' // The authorization URL to be used for (password/application/accessCode)
                    'scopes' => [
                        'read:projects' => 'read your projects',
                        'write:projects' => 'modify projects in your account',
                    ]
                ],
                */

                /* Open API 3.0 support
                'passport' => [ // Unique name of security
                    'type' => 'oauth2', // The type of the security scheme. Valid values are "basic", "apiKey" or "oauth2".
                    'description' => 'Laravel passport oauth2 security.',
                    'in' => 'header',
                    'scheme' => 'https',
                    'flows' => [
                        "password" => [
                            "authorizationUrl" => config('app.url') . '/oauth/authorize',
                            "tokenUrl" => config('app.url') . '/oauth/token',
                            "refreshUrl" => config('app.url') . '/token/refresh',
                            "scopes" => []
                        ],
                    ],
                ],
                */],
            'security' => [
                /*
                 * Examples of Securities
                */
                [
                    /*
                    'oauth2_security_example' => [
                        'read',
                        'write'
                    ],

                    'passport' => []
                    */],
            ],
        ],

        /*
         * Set this to `true` in development mode so that docs would be regenerated on each request
         * Set this to `false` to disable swagger generation on production
        */
        'generate_always' => env('L5_SWAGGER_GENERATE_ALWAYS', true),

        /*
         * Set this to `true` to generate a copy of documentation in yaml format
        */
        'generate_yaml_copy' => env('L5_SWAGGER_GENERATE_YAML_COPY', false),

        /*
         * Edit to trust the proxy's ip address - needed for AWS Load Balancer
         * string[]
        */
        'proxy' => false,

        /*
         * Configs plugin allows to fetch external configs instead of passing them to SwaggerUIBundle.
         * See more at: https://github.com/swagger-api/swagger-ui#configs-plugin
        */
        'additional_config_url' => null,

        /*
         * Apply a sort to the operation list of each API. It can be 'alpha' (sort by paths alphanumerically),
         * 'method' (sort by HTTP method).
         * Default is the order returned by the server unchanged.
        */
        'operations_sort' => env('L5_SWAGGER_OPERATIONS_SORT', null),

        /*
         * Pass the validatorUrl parameter to SwaggerUi init on the JS side.
         * A null value here disables validation.
        */
        'validator_url' => null,

        /*
         * Uncomment to add constants which can be used in annotations
         */
        'constants' => [
            'L5_SWAGGER_CONST_HOST' => env('L5_SWAGGER_CONST_HOST', 'http://my-default-host.com'),
        ],
    ],
];
