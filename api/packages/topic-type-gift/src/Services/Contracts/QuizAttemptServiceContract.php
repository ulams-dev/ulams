<?php

namespace Ulams\TopicTypeGift\Services\Contracts;

use Ulams\Core\Dtos\OrderDto;
use Ulams\TopicTypeGift\Dtos\Criteria\PageDto;
use Ulams\TopicTypeGift\Dtos\Criteria\QuizAttemptCriteriaDto;
use Ulams\TopicTypeGift\Dtos\QuizAttemptDto;
use Ulams\TopicTypeGift\Exceptions\TooManyAttemptsException;
use Ulams\TopicTypeGift\Models\QuizAttempt;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface QuizAttemptServiceContract
{
    /**
     * @throws TooManyAttemptsException
     */
    public function getActive(QuizAttemptDto $dto): QuizAttempt;
    public function findByUser(QuizAttemptCriteriaDto $criteriaDto, PageDto $paginationDto, int $userId): LengthAwarePaginator;
    public function findAll(QuizAttemptCriteriaDto $criteriaDto, PageDto $paginationDto, ?OrderDto $orderDto = null): LengthAwarePaginator;
    public function findById(int $id): QuizAttempt;
    public function updateFeedback(int $id, ?string $feedback): QuizAttempt;
}
