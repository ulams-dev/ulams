<?php

namespace Ulams\TopicTypeGift\Database\Factories;

use Ulams\Auth\Models\User;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\TopicTypeGift\Models\QuizAttempt;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class QuizAttemptFactory extends Factory
{
    protected $model = QuizAttempt::class;

    public function definition(): array
    {
        return [
            'topic_gift_quiz_id' => GiftQuiz::factory(),
            'user_id' => User::factory(),
            'started_at' => Carbon::now(),
            'end_at' => Carbon::now()->addMinutes(20),
        ];
    }
}
