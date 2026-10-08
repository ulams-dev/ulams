<?php

namespace EscolaLms\TopicTypeGift\Services\Contracts;

use EscolaLms\TopicTypeGift\Dtos\QuizDto;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use Illuminate\Database\Eloquent\Collection;

interface GiftQuizServiceContract
{
    public function update(int $id, QuizDto $dto): GiftQuiz;

    public function getQuizzesByCourse(int $courseId): Collection;
}
