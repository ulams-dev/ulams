# Topic Type GIFT

[![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/Topic-Type-GIFT/)
[![codecov](https://codecov.io/gh/EscolaLMS/Topic-Type-GIFT/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/EscolaLMS/Topic-Type-GIFT)
[![phpunit](https://github.com/EscolaLMS/Topic-Type-GIFT/actions/workflows/test.yml/badge.svg)](https://github.com/EscolaLMS/Topic-Type-GIFT/actions/workflows/test.yml)
[![downloads](https://img.shields.io/packagist/dt/escolalms/topic-type-gift)](https://packagist.org/packages/escolalms/topic-type-gift)
[![downloads](https://img.shields.io/packagist/v/escolalms/topic-type-gift)](https://packagist.org/packages/escolalms/topic-type-gift)
[![downloads](https://img.shields.io/packagist/l/escolalms/topic-type-gift)](https://packagist.org/packages/escolalms/topic-type-gift)
[![Maintainability](https://api.codeclimate.com/v1/badges/0c9e2593fb30e2048f95/maintainability)](https://codeclimate.com/github/EscolaLMS/Topic-Type-GIFT/maintainability)

## What does it do

This package is another [TopicType](https://github.com/EscolaLMS/topic-types). It is used to make knowledge tests.
If you want to learn more about this format then see [Moodle GIFT format](https://docs.moodle.org/402/en/GIFT_format)

This package supports the following types of questions:
- multiple choice 
- multiple choice with multiple right answers
- true-false
- short answers
- matching
- numerical question 
- essay 
- description

Each question is stored in the database as a string. In the tests you can see examples of questions of different types. See [examples](https://github.com/EscolaLMS/Topic-Type-GIFT/blob/main/tests/GiftQuestionTesting.php)

The quiz can have a set maximum number of attempts for the user to solve the test and a maximum time for each attempt.
If the user doesn't complete the attempt then it is closed automatically after the time set by the variable `Config::get('escolalms_gift_quiz.max_quiz_time');`
The user will see the results when the attempt is finished.

The answer to an essay type question is not automatically graded. The teacher should do it.

When the quiz has `randomize_order` enabled, both the order of questions and the order of the
answer options within each question are randomized per attempt (single/multiple choice options, and
the two columns of matching questions). The shuffle is seeded with the attempt id, so the whole
layout is random but stable for a given attempt (refreshing the page returns the same order), while
different attempts of the same user may get a different one. When it is disabled (the default) the
questions keep their configured `order` and choice options keep their source order (matching
questions still shuffle their columns, as they always have, otherwise the pairs would line up).

## Installing

- `composer require escolalms/topic-type-gift`
- `php artisan migrate`
- `php artisan db:seed --class="EscolaLms\TopicTypeGift\Database\Seeders\TopicTypeGiftPermissionSeeder"`

## Endpoints

The endpoints are defined in [![swagger](https://img.shields.io/badge/documentation-swagger-green)](https://escolalms.github.io/Topic-Type-GIFT/)

## Database

See the database schema snippet for a better understanding of how it is made.

```mermaid
classDiagram
direction BT
class topic_gift_attempt_answers {
   bigint topic_gift_quiz_attempt_id
   bigint topic_gift_question_id
   json answer
   varchar feedback
   double precision score
   bigint id
}
class topic_gift_questions {
   bigint topic_gift_quiz_id
   text value
   varchar type
   integer score
   integer order
   integer category_id
   bigint id
}
class topic_gift_quiz_attempts {
   timestamp started_at
   timestamp end_at
   bigint user_id
   bigint topic_gift_quiz_id
   bigint id
}
class topic_gift_quizzes {
   text value
   integer max_attempts
   integer max_execution_time
   double precision min_pass_score
   boolean counts_to_grade
   boolean randomize_order
   bigint id
}

topic_gift_attempt_answers  -->  topic_gift_questions : topic_gift_question_id.id
topic_gift_attempt_answers  -->  topic_gift_quiz_attempts : topic_gift_quiz_attempt_id.id
topic_gift_questions  -->  topic_gift_quizzes : topic_gift_quiz_id.id
topic_gift_quiz_attempts  -->  topic_gift_quizzes : topic_gift_quiz_id.id

```

## Student flow

See diagram of how student flow works.

```mermaid
graph TD
A[Start] --> B[Is attempt active?]
B -- Yes --> E
B -- No --> C[Is attempt limit exceeded?]
C -- Yes --> K
C -- No --> D[Create new attempt]
D --> E[Return questions]
E -- Sequential saving --> F[End attempt]
E -- All-at-once saving --> G[Attempt closes automatically]
F --> H
G --> H[Show results]
H --> K[End]
```

## Tests

Run `./vendor/bin/phpunit` to run tests.
Test details [![codecov](https://codecov.io/gh/EscolaLMS/Topic-Type-GIFT/branch/main/graph/badge.svg?token=NRAN4R8AGZ)](https://codecov.io/gh/EscolaLMS/Topic-Type-GIFT)

## Events

- `QuizAttemptStartedEvent` - This event is dispatched when the user starts a new attempt to solve the test.
- `QuizAttemptFinishedEvent` - This event is dispatched when the user has finished solving the test.

## Listeners

This package does not listen for any events.

## Permissions

Permissions are defined in [seeder](https://github.com/EscolaLMS/Topic-Type-GIFT/blob/main/database/seeders/TopicTypeGiftPermissionSeeder.php).
