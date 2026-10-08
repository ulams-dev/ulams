<?php

namespace Ulams\Questionnaire\Services\Contracts;

use Ulams\Core\Models\User;
use Ulams\Questionnaire\Models\QuestionAnswer;
use Ulams\Questionnaire\Models\QuestionnaireModel;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Interface QuestionnaireAnswerServiceContract
 * @package Ulams\Questionnaire\Http\Services\Contracts
 */
interface QuestionnaireAnswerServiceContract
{
    public function getReport(int $id, ?int $modelTypeId = null, ?int $modelId = null): Collection;

    public function getStars(int $modelTypeId, int $modelId): array;

    public function saveAnswer(QuestionnaireModel $questionnaireModel, array $data, User $user): ?array;
    public function publicQuestionAnswers(array $criteria, ?int $perPage = null): LengthAwarePaginator;
    public function getReviewStars(array $criteria): array;
    public function searchAndPaginate(array $search = [], ?int $perPage = null, string $orderDirection = 'asc', string $orderColumn = 'id'): LengthAwarePaginator;
    public function update(array $input, int $id): QuestionAnswer;
}
