<?php

namespace Ulams\TopicTypeGift\Services;

use Ulams\TopicTypeGift\Dtos\QuizDto;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\TopicTypeGift\Repositories\Contracts\GiftQuizRepositoryContract;
use Ulams\TopicTypeGift\Services\Contracts\GiftQuizServiceContract;
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
