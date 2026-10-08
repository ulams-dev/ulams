<?php

namespace EscolaLms\TopicTypeGift\Services;

use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Repositories\Criteria\Primitives\EqualCriterion;
use EscolaLms\TopicTypeGift\Dtos\Criteria\PageDto;
use EscolaLms\TopicTypeGift\Dtos\Criteria\QuizAttemptCriteriaDto;
use EscolaLms\TopicTypeGift\Dtos\QuizAttemptDto;
use EscolaLms\TopicTypeGift\Events\QuizAttemptStartedEvent;
use EscolaLms\TopicTypeGift\Exceptions\TooManyAttemptsException;
use EscolaLms\TopicTypeGift\Jobs\MarkAttemptAsEnded;
use EscolaLms\TopicTypeGift\Models\GiftQuiz;
use EscolaLms\TopicTypeGift\Models\QuizAttempt;
use EscolaLms\TopicTypeGift\Providers\SettingsServiceProvider;
use EscolaLms\TopicTypeGift\Repositories\Contracts\QuizAttemptRepositoryContract;
use EscolaLms\TopicTypeGift\Services\Contracts\QuizAttemptServiceContract;
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
                : Carbon::now()->addMinutes(Config::get(SettingsServiceProvider::KEY . 'max_quiz_time', 120)),
        ]));

        event(new QuizAttemptStartedEvent($attempt->user, $attempt));
        MarkAttemptAsEnded::dispatch($attempt->getKey())->delay($attempt->end_at);

        return $attempt;
    }
}
