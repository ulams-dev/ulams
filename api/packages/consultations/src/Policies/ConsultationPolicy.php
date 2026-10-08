<?php

namespace Ulams\Consultations\Policies;

use Ulams\Consultations\Enum\ConsultationsPermissionsEnum;
use Ulams\Consultations\Models\Consultation;
use Ulams\Auth\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConsultationPolicy
{
    use HandlesAuthorization;

    public function list(User $user): bool
    {
        return $user->can(ConsultationsPermissionsEnum::CONSULTATION_LIST) || $user->can(ConsultationsPermissionsEnum::CONSULTATION_LIST_OWN);
    }

    public function read(User $user, Consultation $consultation): bool
    {
        return $user->can(ConsultationsPermissionsEnum::CONSULTATION_READ)
            || ($user->can(ConsultationsPermissionsEnum::CONSULTATION_READ_OWN) && $consultation->author_id === $user->getKey());
    }

    public function create(User $user): bool
    {
        return $user->can(ConsultationsPermissionsEnum::CONSULTATION_CREATE);
    }

    public function update(User $user, Consultation $consultation): bool
    {
        return $user->can(ConsultationsPermissionsEnum::CONSULTATION_UPDATE)
            || ($user->can(ConsultationsPermissionsEnum::CONSULTATION_UPDATE_OWN) && $consultation->author_id === $user->getKey());
    }

    public function delete(User $user, Consultation $consultation): bool
    {
        return $user->can(ConsultationsPermissionsEnum::CONSULTATION_DELETE)
        || ($user->can(ConsultationsPermissionsEnum::CONSULTATION_DELETE_OWN) && $consultation->author_id === $user->getKey());
    }
}
