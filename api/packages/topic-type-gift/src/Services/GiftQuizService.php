<?php

namespace EscolaLms\TopicTypeGift\Services;

use EscolaLms\TopicTypeGift\Dtos\QuizDto;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypeGift\Repositories\Contracts\GiftQuizRepositoryContract;
use EscolaLms\TopicTypeGift\Services\Contracts\GiftQuizServiceContract;
use Illuminate\Database\Eloquent\Collection;

class GiftQuizService implements GiftQuizServiceContract
{
    private GiftQuizRepositoryContract $giftQuizRepository;

    public function __construct(GiftQuizRepositoryContract $giftQuizRepository)
    {
        $this->giftQuizRepository = $giftQuizRepository;
    }

    public function update(int $id, QuizDto $dto): GiftQuiz
    {
        /** @var GiftQuiz */
        return $this->giftQuizRepository->update($dto->toArray(), $id);
    }

    public function getQuizzesByCourse(int $courseId): Collection
    {
        return $this->giftQuizRepository->getByCourseId($courseId);
    }
}
