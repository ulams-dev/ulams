<?php

namespace Ulams\Questionnaire\Services\Contracts;

use Ulams\Questionnaire\Models\Question;
use Illuminate\Support\Collection;

/**
 * Interface QuestionServiceContract
 * @package Ulams\Questionnaire\Http\Services\Contracts
 */
interface QuestionServiceContract
{
    public function deleteQuestion(Question $question): bool;

    public function createQuestion(array $data): Question;

    public function updateQuestion(Question $question, array $data): Question;
    public function getAllQuestionnaireQuestions(int $id): Collection;
    public function getQuestionMaxScore(int $id): int;
}
