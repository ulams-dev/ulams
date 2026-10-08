<?php

namespace Ulams\Questionnaire\Services;

use Ulams\Questionnaire\Models\Question;
use Ulams\Questionnaire\Repository\Contracts\QuestionAnswerRepositoryContract;
use Ulams\Questionnaire\Repository\Contracts\QuestionRepositoryContract;
use Ulams\Questionnaire\Services\Contracts\QuestionServiceContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class QuestionService implements QuestionServiceContract
{
    private QuestionAnswerRepositoryContract $questionAnswerRepository;
    private QuestionRepositoryContract $questionRepository;

    public function __construct(
        QuestionAnswerRepositoryContract $questionAnswerRepository,
        QuestionRepositoryContract $questionRepository
    ) {
        $this->questionAnswerRepository = $questionAnswerRepository;
        $this->questionRepository = $questionRepository;
    }

    public function deleteQuestion(Question $question): bool
    {
        DB::transaction(function () use ($question) {
            $this->questionAnswerRepository->deleteByQuestionId($question->id);
            $this->questionRepository->delete($question->id);
        });

        return true;
    }

    public function createQuestion(array $data): Question
    {
        /** @var Question $question */
        $question = $this->questionRepository->create($data);
        return $question;
    }

    public function updateQuestion(Question $question, array $data): Question
    {
        /** @var Question $question */
        $question = $this->questionRepository->update($data, $question->getKey());
        return $question;
    }

    public function getAllQuestionnaireQuestions(int $id): Collection
    {
        return $this->questionRepository->getAllQuestionnaireQuestions($id);
    }

    public function getQuestionMaxScore(int $id): int
    {
        return (int)$this->questionRepository->find($id)->getAttribute('max_score');
    }
}
