<?php

return [
    /**
     * By modyfing this list, you can add or remove available Metrics for which Reports can be calculated
     */
    'metrics' => [
        \Ulams\Reports\Metrics\CoursesMoneySpentMetric::class,
        \Ulams\Reports\Metrics\CoursesPopularityMetric::class,
        \Ulams\Reports\Metrics\CoursesSecondsSpentMetric::class,
        \Ulams\Reports\Metrics\TutorsPopularityMetric::class,
        \Ulams\Reports\Metrics\CoursesBestRatedMetric::class,
        \Ulams\Reports\Metrics\CoursesTopSellingMetric::class,
        \Ulams\Reports\Metrics\CoursesAuthoredPopularityMetric::class,
        \Ulams\Reports\Metrics\CoursesAuthoredMoneySpentMetric::class,
        \Ulams\Reports\Metrics\CoursesAuthoredSecondsSpentMetric::class,
    ],
    /**
     * For each Metric class you can specify settings:
     * @param bool history - should this metric be automatically measured (default: true)
     * @param int limit    - how many data points should be saved in database and/or retrieved in api call (default: 10)
     * @param string cron  - cron expression determining how often this metric will be measured and saved in DB (default: midnight every day)
     */
    'metric_configuration' => [
        \Ulams\Reports\Metrics\CoursesMoneySpentMetric::class => [
            'limit' => 10,
            'history' => false,
            'cron' => '0 0 * * *',
        ],
        \Ulams\Reports\Metrics\CoursesPopularityMetric::class => [
            'limit' => 10,
            'history' => false,
            'cron' => '0 0 * * *',
        ],
        \Ulams\Reports\Metrics\CoursesSecondsSpentMetric::class => [
            'limit' => 10,
            'history' => false,
            'cron' => '0 0 * * *',
        ],
        \Ulams\Reports\Metrics\TutorsPopularityMetric::class => [
            'limit' => 10,
            'history' => false,
            'cron' => '0 0 * * *',
        ],
        \Ulams\Reports\Metrics\CoursesTopSellingMetric::class => [
            'limit' => 10,
            'history' => false,
            'cron' => '0 0 * * *',
        ],
        \Ulams\Reports\Metrics\CoursesBestRatedMetric::class => [
            'limit' => 10,
            'history' => false,
            'cron' => '0 0 * * *',
        ],
        \Ulams\Reports\Metrics\CoursesAuthoredPopularityMetric::class => [
            'limit' => 10,
            'history' => false,
            'cron' => '0 0 * * *',
        ],
        \Ulams\Reports\Metrics\CoursesAuthoredMoneySpentMetric::class => [
            'limit' => 10,
            'history' => false,
            'cron' => '0 0 * * *',
        ],
        \Ulams\Reports\Metrics\CoursesAuthoredSecondsSpentMetric::class => [
            'limit' => 10,
            'history' => false,
            'cron' => '0 0 * * *',
        ],
    ],
    /**
     * By modyfing this associative array, you can add or remove available Stats which can be returned for single objects of given class
     */
    'stats' => [
        \Ulams\Courses\Models\Course::class => [
            \Ulams\Reports\Stats\Course\AverageTime::class,
            \Ulams\Reports\Stats\Course\AverageTimePerTopic::class,
            \Ulams\Reports\Stats\Course\MoneyEarned::class,
            \Ulams\Reports\Stats\Course\PeopleBought::class,
            \Ulams\Reports\Stats\Course\PeopleFinished::class,
            \Ulams\Reports\Stats\Course\PeopleStarted::class,
            \Ulams\Reports\Stats\Course\FinishedTopics::class,
            \Ulams\Reports\Stats\Course\FinishedCourse::class,
            \Ulams\Reports\Stats\Course\AttendanceList::class,
        ],
        \Ulams\Courses\Models\Topic::class => [
            \Ulams\Reports\Stats\Topic\AverageTime::class,
            \Ulams\Reports\Stats\Topic\QuizSummaryForTopicTypeGIFT::class,
        ],
        \Ulams\Cart\Models\Cart::class => [
            \Ulams\Reports\Stats\Cart\NewCustomers::class,
            \Ulams\Reports\Stats\Cart\SpendPerCustomer::class,
            \Ulams\Reports\Stats\Cart\ReturningCustomers::class,
        ],
        \Ulams\Reports\ValueObject\DateRange::class => [
            \Ulams\Reports\Stats\User\NewUsers::class,
            \Ulams\Reports\Stats\User\ActiveUsers::class,
            \Ulams\Reports\Stats\Course\Started::class,
            \Ulams\Reports\Stats\Course\Finished::class,
        ]
    ]
];
