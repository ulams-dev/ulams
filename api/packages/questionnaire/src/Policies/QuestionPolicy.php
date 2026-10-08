<?php

namespace Ulams\Questionnaire\Policies;

use Ulams\Core\Models\User;
use Ulams\Questionnaire\Enums\QuestionnairePermissionsEnum;
use Ulams\Questionnaire\Models\Question;
use Illuminate\Auth\Access\HandlesAuthorization;

class QuestionPolicy
{
    use HandlesAuthorization;

    public function list(User $user): bool
    {
        return $user->can(QuestionnairePermissionsEnum::QUESTION_LIST);
    }

    public function read(User $user, Question $question): bool
    {
        return $user->can(QuestionnairePermissionsEnum::QUESTION_READ);
    }

    public function create(User $user): bool
    {
        return $user->can(QuestionnairePermissionsEnum::QUESTION_CREATE);
    }

    public function delete(User $user, Question $question): bool
    {
        return $user->can(QuestionnairePermissionsEnum::QUESTION_DELETE);
    }

    public function update(User $user, Question $question): bool
    {
        return $user->can(QuestionnairePermissionsEnum::QUESTION_UPDATE);
    }

    public function readAnswers(?User $user, Question $question): bool
    {
        return $question->public_answers;
    }
}
