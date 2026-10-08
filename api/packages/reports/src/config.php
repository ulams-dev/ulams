<?php

return [
    /**
     * By modyfing this list, you can add or remove available Metrics for which Reports can be calculated
     */
    'metrics' => [
        \EscolaLms\Reports\Metrics\CoursesMoneySpentMetric::class,
        \EscolaLms\Reports\Metrics\CoursesPopularityMetric::class,
        \EscolaLms\Reports\Metrics\CoursesSecondsSpentMetric::class,
        \EscolaLms\Reports\Metrics\TutorsPopularityMetric::class,
        \EscolaLms\Reports\Metrics\CoursesBestRatedMetric::class,
        \EscolaLms\Reports\Metrics\CoursesTopSellingMetric::class,
        \EscolaLms\Reports\Metrics\CoursesAuthoredPopularityMetric::class,
        \EscolaLms\Reports\Metrics\CoursesAuthoredMoneySpentMetric::class,
        \EscolaLms\Reports\Metrics\CoursesAuthoredSecondsSpentMetric::class,
    ],
    /**
     * For each Metric class you can specify settings:
     * @param bool history - should this metric be automatically measured (default: true)
     * @param int limit    - how many data points should be saved in database and/or retrieved in api call (default: 10)
     * @param string cron  - cron expression determining how often this metric will be measured and saved in DB (default: midnight every day)
     */
    'metric_configuration' => [
        \EscolaLms\Reports\Metrics\CoursesMoneySpentMetric::class => [
            'limit' => 10,
            'history' => false,
            'cron' => '0 0 * * *',
        ],
        \EscolaLms\Reports\Metrics\CoursesPopularityMetric::class => [
            'limit' => 10,
            'history' => false,
            'cron' => '0 0 * * *',
        ],
        \EscolaLms\Reports\Metrics\CoursesSecondsSpentMetric::class => [
            'limit' => 10,
            'history' => false,
            'cron' => '0 0 * * *',
        ],
        \EscolaLms\Reports\Metrics\TutorsPopularityMetric::class => [
            'limit' => 10,
            'history' => false,
            'cron' => '0 0 * * *',
        ],
        \EscolaLms\Reports\Metrics\CoursesTopSellingMetric::class => [
            'limit' => 10,
            'history' => false,
            'cron' => '0 0 * * *',
        ],
        \EscolaLms\Reports\Metrics\CoursesBestRatedMetric::class => [
            'limit' => 10,
            'history' => false,
            'cron' => '0 0 * * *',
        ],
        \EscolaLms\Reports\Metrics\CoursesAuthoredPopularityMetric::class => [
            'limit' => 10,
            'history' => false,
            'cron' => '0 0 * * *',
        ],
        \EscolaLms\Reports\Metrics\CoursesAuthoredMoneySpentMetric::class => [
            'limit' => 10,
            'history' => false,
            'cron' => '0 0 * * *',
        ],
        \EscolaLms\Reports\Metrics\CoursesAuthoredSecondsSpentMetric::class => [
            'limit' => 10,
            'history' => false,
            'cron' => '0 0 * * *',
        ],
    ],
    /**
     * By modyfing this associative array, you can add or remove available Stats which can be returned for single objects of given class
     */
    'stats' => [
        \EscolaLms\Courses\Models\Course::class => [
            \EscolaLms\Reports\Stats\Course\AverageTime::class,
            \EscolaLms\Reports\Stats\Course\AverageTimePerTopic::class,
            \EscolaLms\Reports\Stats\Course\MoneyEarned::class,
            \EscolaLms\Reports\Stats\Course\PeopleBought::class,
            \EscolaLms\Reports\Stats\Course\PeopleFinished::class,
            \EscolaLms\Reports\Stats\Course\PeopleStarted::class,
            \EscolaLms\Reports\Stats\Course\FinishedTopics::class,
            \EscolaLms\Reports\Stats\Course\FinishedCourse::class,
            \EscolaLms\Reports\Stats\Course\AttendanceList::class,
        ],
        \EscolaLms\Courses\Models\Topic::class => [
            \EscolaLms\Reports\Stats\Topic\AverageTime::class,
            \EscolaLms\Reports\Stats\Topic\QuizSummaryForTopicTypeGIFT::class,
        ],
        \EscolaLms\Cart\Models\Cart::class => [
            \EscolaLms\Reports\Stats\Cart\NewCustomers::class,
            \EscolaLms\Reports\Stats\Cart\SpendPerCustomer::class,
            \EscolaLms\Reports\Stats\Cart\ReturningCustomers::class,
        ],
        \EscolaLms\Reports\ValueObject\DateRange::class => [
            \EscolaLms\Reports\Stats\User\NewUsers::class,
            \EscolaLms\Reports\Stats\User\ActiveUsers::class,
            \EscolaLms\Reports\Stats\Course\Started::class,
            \EscolaLms\Reports\Stats\Course\Finished::class,
        ]
    ]
];
