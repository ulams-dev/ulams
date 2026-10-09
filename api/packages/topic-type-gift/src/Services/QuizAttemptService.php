<?php

namespace Ulams\TopicTypeGift\Services;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Repositories\Criteria\Primitives\EqualCriterion;
use Ulams\TopicTypeGift\Dtos\Criteria\PageDto;
use Ulams\TopicTypeGift\Dtos\Criteria\QuizAttemptCriteriaDto;
use Ulams\TopicTypeGift\Dtos\QuizAttemptDto;
use Ulams\TopicTypeGift\Events\QuizAttemptStartedEvent;
use Ulams\TopicTypeGift\Exceptions\TooManyAttemptsException;
use Ulams\TopicTypeGift\Jobs\MarkAttemptAsEnded;
use Ulams\TopicTypeGift\Models\GiftQuiz;
use Ulams\TopicTypeGift\Models\QuizAttempt;
use Ulams\TopicTypeGift\Providers\SettingsServiceProvider;
use Ulams\TopicTypeGift\Repositories\Contracts\QuizAttemptRepositoryContract;
use Ulams\TopicTypeGift\Services\Contracts\QuizAttemptServiceContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

class QuizAttemptService implements QuizAttemptServiceContract
{
    private QuizAttemptRepositoryContract $attemptRepository;

    public function __construct(QuizAttemptRepositoryContract $attemptRepository)
    {
        $this->attemptRepository = $attemptRepository;
    }

    public function findAll(QuizAttemptCriteriaDto $criteriaDto, PageDto $paginationDto, ?OrderDto $orderDto = null): LengthAwarePaginator
    {
        return $this->attemptRepository->findByCriteria($criteriaDto->toArray(), $paginationDto->getPerPage(), $orderDto);
    }

    public function findByUser(QuizAttemptCriteriaDto $criteriaDto, PageDto $paginationDto, int $userId): LengthAwarePaginator
    {
        $criteria = $criteriaDto->toArray();
        $criteria[] = new EqualCriterion('user_id', $userId);

        return $this->attemptRepository->findByCriteria($criteria, $paginationDto->getPerPage());
    }

    public function findById(int $id): QuizAttempt
    {
        /** @var QuizAttempt */
        return $this->attemptRepository->find($id);
    }

    public function updateFeedback(int $id, ?string $feedback): QuizAttempt
    {
        /** @var QuizAttempt */
        return $this->attemptRepository->update([
            'tutor_feedback' => $feedback === '' ? null : $feedback,
        ], $id);
    }

    /**
     * @throws TooManyAttemptsException
     */
    public function getActive(QuizAttemptDto $dto): QuizAttempt
    {
        /** @var ?QuizAttempt $active */
        $active = $this->attemptRepository->findActive($dto->getUserId(), $dto->getQuizId());
        if ($active) {
            return $active;
        }

        /** @var GiftQuiz $quiz */
        $quiz = GiftQuiz::findOrFail($dto->getQuizId());
        $userAttempts = $this->attemptRepository->queryByUserIdAndQuizId($dto->getUserId(), $dto->getQuizId());
        if (is_numeric($quiz->max_attempts) && $userAttempts->count() >= $quiz->max_attempts) {
            throw new TooManyAttemptsException();
        }

        /** @var QuizAttempt $attempt */
        $attempt =  $this->attemptRepository->create(array_merge($dto->toArray(), [
            'end_at' => $quiz->max_execution_time
                ? Carbon::now()->addMinutes($quiz->max_execution_time)
                : Carbon::now()->addMinutes((int) Config::get(SettingsServiceProvider::KEY . '.max_quiz_time', 120)),
        ]));

        event(new QuizAttemptStartedEvent($attempt->user, $attempt));
        MarkAttemptAsEnded::dispatch($attempt->getKey())->delay($attempt->end_at);

        return $attempt;
    }
}
