<?php

namespace Ulams\Questionnaire\Policies;

use Ulams\Core\Models\User;
use Ulams\Questionnaire\Enums\QuestionnairePermissionsEnum;
use Ulams\Questionnaire\Models\Questionnaire;
use Illuminate\Auth\Access\HandlesAuthorization;

class QuestionnairePolicy
{
    use HandlesAuthorization;

    public function list(User $user): bool
    {
        return $user->can(QuestionnairePermissionsEnum::QUESTIONNAIRE_LIST);
    }

    public function read(User $user, Questionnaire $questionnaire): bool
    {
        return $user->can(QuestionnairePermissionsEnum::QUESTIONNAIRE_READ);
    }

    public function listFront(User $user): bool
    {
        return true;
    }

    public function readFront(User $user, Questionnaire $questionnaire): bool
    {
        return $questionnaire->active;
    }

    public function create(User $user): bool
    {
        return $user->can(QuestionnairePermissionsEnum::QUESTIONNAIRE_CREATE);
    }

    public function delete(User $user, Questionnaire $questionnaire): bool
    {
        return $user->can(QuestionnairePermissionsEnum::QUESTIONNAIRE_DELETE);
    }

    public function update(User $user, Questionnaire $questionnaire): bool
    {
        return $user->can(QuestionnairePermissionsEnum::QUESTIONNAIRE_UPDATE);
    }
}
