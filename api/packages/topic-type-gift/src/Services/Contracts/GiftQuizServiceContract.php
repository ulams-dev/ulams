<?php

namespace Ulams\TopicTypeGift\Services\Contracts;

use Ulams\TopicTypeGift\Dtos\QuizDto;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Illuminate\Database\Eloquent\Collection;

interface GiftQuizServiceContract
{
    public function update(int $id, QuizDto $dto): GiftQuiz;

    public function getQuizzesByCourse(int $courseId): Collection;
}
