<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue API supports an assortment of back-ends via a single
    | API, giving you convenient access to each back-end using the same
    | syntax for every one. Here you may define a default connection.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'sync'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection information for each server that
    | is used by your application. A default configuration has been added
    | for each back-end shipped with Laravel. You are free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis", "null"
    |
    */

    /*
    | Where long jobs that are not part of a package with its own setting run (course clone/import):
    | `<driver>-long-job` on the `database` or `redis` default connection, ADR 0083. The connection name is
    | LONG_JOB_QUEUE_CONNECTION (the same name workers.sh reads); LONG_JOB_CONNECTION is the old name, still accepted.
    */

    'long_job' => [
        'connection' => env('LONG_JOB_QUEUE_CONNECTION', env('LONG_JOB_CONNECTION', in_array(env('QUEUE_CONNECTION'), ['database', 'redis'], true) ? env('QUEUE_CONNECTION') . '-long-job' : null)),
        'queue' => env('LONG_JOB_QUEUE', in_array(env('QUEUE_CONNECTION'), ['database', 'redis'], true) ? 'queue-long-job' : null),
    ],

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => 'localhost',
            'queue' => 'default',
            'retry_after' => 90,
            'block_for' => 0,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'your-queue-name'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for' => null,
        ],

        // Long jobs. `retry_after` must stay above the longest `$timeout` of a job on the connection
        // plus a margin, or a second worker picks up a job that is still running (a double LLM call,
        // a double clone). Guarded by tests/Integrations/QueueRetryAfterConfigTest.php (ADR 0083).

        // Course Builder, Living Course and the Adapt build: jobs run up to 1800 s.
        'database-builder' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'builder',
            'retry_after' => (int) env('BUILDER_QUEUE_RETRY_AFTER', 2400),
        ],

        'redis-builder' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'builder',
            'retry_after' => (int) env('BUILDER_QUEUE_RETRY_AFTER', 2400),
            'block_for' => null,
        ],

        // Video processing and course clone/import: jobs run up to 18000 s.
        'database-long-job' => [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'queue-long-job',
            'retry_after' => 19000,
        ],

        'redis-long-job' => [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => 19000,
            'block_for' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control which database and table are used to store the jobs that
    | have failed. You may change them to any database / table you wish.
    |
    */

    'failed' => [
        'database' => env('DB_CONNECTION', 'mysql'),
        'table' => 'failed_jobs',
    ],

];
