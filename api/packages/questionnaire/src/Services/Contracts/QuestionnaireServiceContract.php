<?php

namespace Ulams\Questionnaire\Services\Contracts;

use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Models\User;
use Ulams\Questionnaire\Models\Questionnaire;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Interface QuestionnaireServiceContract
 * @package Ulams\Questionnaire\Http\Services\Contracts
 */
interface QuestionnaireServiceContract
{
    public function deleteQuestionnaire(Questionnaire $questionnaire): bool;

    public function searchForFront(array $filters, ?bool $publicAnswers = null): LengthAwarePaginator;

    public function list(array $criteria, OrderDto $orderDto, int $perPage = 15): LengthAwarePaginator;

    public function findForFront(array $filters, User $user): ?array;

    public function answer(array $params, User $user): bool;

    public function createQuestionnaire(array $data): Questionnaire;

    public function updateQuestionnaire(Questionnaire $questionnaire, array $data): Questionnaire;
}
