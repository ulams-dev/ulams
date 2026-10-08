<?php

namespace Ulams\Consultations\Repositories\Contracts;

use Ulams\Consultations\Dto\FilterConsultationTermsListDto;
use Ulams\Consultations\Models\ConsultationUserPivot;
use Ulams\Consultations\Models\ConsultationUserTerm;
use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

interface ConsultationUserTermRepositoryContract extends BaseRepositoryContract
{
    public function createUserTerm(ConsultationUserPivot $consultationUserPivot, array $data): ConsultationUserTerm;

    public function updateUserTermByExecutedAt(ConsultationUserPivot $consultationUserPivot, string $executedAt, array $data): ConsultationUserTerm;
    public function updateByConsultationUserIdAndExecutedAt(int $consultationUserId, string $executedAt, array $data): ConsultationUserTerm;
    public function allQueryBuilder(?FilterConsultationTermsListDto $filterConsultationTermsListDto = null): Collection;
    /**
     * @return Collection<int, Model>
     */
    public function getBusyTerms(int $consultationId, ?string $date = null): Collection;
    public function getAllUserTermsByConsultationIdAndExecutedAt(int $consultationId, string $executedAt): Collection;
    public function getUserTermByUserIdAndExecutedAt(int $userId, string $executedAt): ConsultationUserTerm;
    public function updateModels(Collection $models, array $data): void;
    public function getByCurrentUserTutor(array $criteria = []): Collection;
    public function updateModel(ConsultationUserTerm $userTerm, array $data): ConsultationUserTerm;

}
