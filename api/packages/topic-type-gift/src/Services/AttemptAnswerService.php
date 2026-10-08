<?php

namespace Ulams\TopicTypeGift\Services;

use Ulams\TopicTypeGift\Dtos\AdminUpdateAttemptAnswerDto;
use Ulams\TopicTypeGift\Dtos\SaveAllAttemptAnswersDto;
use Ulams\TopicTypeGift\Dtos\SaveAttemptAnswerDto;
use Ulams\TopicTypeGift\Events\QuizAttemptJournalGradeReadyEvent;
use Ulams\TopicTypeGift\Jobs\MarkAttemptAsEnded;
use Ulams\TopicTypeGift\Models\AttemptAnswer;
use Ulams\TopicTypeGift\Models\GiftQuestion;
use Ulams\TopicTypeGift\Repositories\AttemptAnswerRepository;
use Ulams\TopicTypeGift\Repositories\Contracts\GiftQuestionRepositoryContract;
use Ulams\TopicTypeGift\Services\Contracts\AttemptAnswerServiceContract;
use Ulams\TopicTypeGift\Strategies\GiftQuestionStrategyFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttemptAnswerService implements AttemptAnswerServiceContract
{
    private AttemptAnswerRepository $answerRepository;
    private GiftQuestionRepositoryContract $questionRepository;

    public function __construct(
        AttemptAnswerRepository $answerRepository,
        GiftQuestionRepositoryContract $questionRepository
    ) {
        $this->answerRepository = $answerRepository;
        $this->questionRepository = $questionRepository;
    }

    public function saveAnswer(SaveAttemptAnswerDto $dto): AttemptAnswer
    {
        /** @var GiftQuestion $question */
        $question = $this->questionRepository->find($dto->getQuestionId());
        $strategy = GiftQuestionStrategyFactory::create($question);

        $result = $strategy->checkAnswer($dto->getAnswer());

        return $this->answerRepository->updateOrCreate($dto->toArray(), [
            'answer' => $dto->getAnswer(),
            'feedback' => $result->getFeedback(),
            'score' => $result->getScore(),
            'graded_at' => $strategy->requiresManualGrading() ? null : Carbon::now(),
        ]);
    }

    public function saveAllAnswers(SaveAllAttemptAnswersDto $dto): Collection
    {
        return DB::transaction(function () use ($dto) {
            $result = collect($dto->getAnswers())->map(function ($answer) use ($dto) {
                return $this->saveAnswer(new SaveAttemptAnswerDto(
                    $dto->getAttemptId(),
                    $answer['topic_gift_question_id'],
                    $answer['answer'],
                ));
            });

            MarkAttemptAsEnded::dispatch($dto->getAttemptId());

            return $result;
        });
    }

    public function adminUpdate(int $id, AdminUpdateAttemptAnswerDto $dto): AttemptAnswer
    {
        /** @var AttemptAnswer $answer */
        $answer = $this->answerRepository->update(
            array_merge($dto->toArray(), ['graded_at' => Carbon::now()]),
            $id
        );

        $attempt = $answer->attempt;

        // Once the last open question is graded (or a graded attempt is re-graded),
        // signal the journal so it can upsert the partial grade.
        if ($attempt->isEnded() && $attempt->giftQuiz->counts_to_grade && $attempt->isFullyGraded()) {
            event(new QuizAttemptJournalGradeReadyEvent($attempt->user, $attempt));
        }

        return $answer;
    }
}
