<?php

return [
    'api_url' => env('RECOMMENDER_API_URL', 'http://recommender:9000/recommender/'),
    'signature_secret' => env('RECOMMENDER_SIGNATURE_SECRET', ''),
    'frames_microservice_url' => env('RECOMMENDER_FRAMES_MICROSERVICE_URL', ''),
    'satisfaction_models' => [
        ['model' => 'prod_elasticnet', 'enabled' => true],
        ['model' => 'retrained_ridge3', 'enabled' => false],
        ['model' => 'retrained_rf13', 'enabled' => false],
    ],
];
