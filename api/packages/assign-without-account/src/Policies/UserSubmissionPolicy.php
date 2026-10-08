<?php

namespace EscolaLms\AssignWithoutAccount\Policies;

use EscolaLms\AssignWithoutAccount\Enums\AssignWithoutAccountPermissionEnum;
use EscolaLms\AssignWithoutAccount\Models\UserSubmission;
use EscolaLms\Core\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserSubmissionPolicy
{
    use HandlesAuthorization;

    public function list(User $user): bool
    {
        return $user->can(AssignWithoutAccountPermissionEnum::USER_SUBMISSION_LIST);
    }

    public function create(User $user): bool
    {
        return $user->can(AssignWithoutAccountPermissionEnum::USER_SUBMISSION_CREATE);
    }

    public function update(User $user, UserSubmission $userSubmission): bool
    {
        return $user->can(AssignWithoutAccountPermissionEnum::USER_SUBMISSION_UPDATE);
    }

    public function delete(User $user, UserSubmission $userSubmission): bool
    {
        return $user->can(AssignWithoutAccountPermissionEnum::USER_SUBMISSION_DELETE);
    }
}
