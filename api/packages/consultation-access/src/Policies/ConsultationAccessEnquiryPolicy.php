<?php

namespace EscolaLms\ConsultationAccess\Policies;

use EscolaLms\Auth\Models\User;
use EscolaLms\ConsultationAccess\Enum\ConsultationAccessPermissionEnum;
use EscolaLms\ConsultationAccess\Enum\EnquiryStatusEnum;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use Illuminate\Auth\Access\HandlesAuthorization;

class ConsultationAccessEnquiryPolicy
{
    use HandlesAuthorization;

    public function createOwn(User $user): bool
    {
        return $user->can(ConsultationAccessPermissionEnum::CREATE_OWN_CONSULTATION_ACCESS_ENQUIRY);
    }

    public function readOwn(User $user, ConsultationAccessEnquiry $enquiry): bool
    {
        return $user->can(ConsultationAccessPermissionEnum::READ_OWN_CONSULTATION_ACCESS_ENQUIRY)
            && $enquiry->user_id === $user->getKey();
    }

    public function listOwn(User $user): bool
    {
        return $user->can(ConsultationAccessPermissionEnum::LIST_OWN_CONSULTATION_ACCESS_ENQUIRY);
    }

    public function deleteOwn(User $user, ConsultationAccessEnquiry $enquiry): bool
    {
        return $user->can(ConsultationAccessPermissionEnum::DELETE_OWN_CONSULTATION_ACCESS_ENQUIRY)
            && $enquiry->user->getKey() === $user->getKey();
    }

    public function updateOwn(User $user, ConsultationAccessEnquiry $enquiry): bool
    {
        return $user->can(ConsultationAccessPermissionEnum::UPDATE_OWN_CONSULTATION_ACCESS_ENQUIRY)
            && $enquiry->user->getKey() === $user->getKey();
    }

    public function list(User $user): bool
    {
        return $user->can(ConsultationAccessPermissionEnum::LIST_OWN_CONSULTATION_ACCESS_ENQUIRY);
    }

    public function approve(User $user): bool
    {
        return $user->can(ConsultationAccessPermissionEnum::APPROVE_CONSULTATION_ACCESS_ENQUIRY);
    }

    public function disapprove(User $user): bool
    {
        return $user->can(ConsultationAccessPermissionEnum::DISAPPROVE_CONSULTATION_ACCESS_ENQUIRY);
    }
}
