<?php

namespace Ulams\Questionnaire\Policies;

use Ulams\Core\Models\User;
use Ulams\Questionnaire\Enums\QuestionnairePermissionsEnum;
use Ulams\Questionnaire\Models\QuestionAnswer;
use Illuminate\Auth\Access\HandlesAuthorization;

class QuestionAnswerPolicy
{
    use HandlesAuthorization;

    public function changeVisibility(User $user, QuestionAnswer $answer): bool
    {
        return $user->can(QuestionnairePermissionsEnum::QUESTION_ANSWER_VISIBILITY_CHANGE);
    }
}
