<?php

namespace Ulams\Consultations\Repositories\Contracts;

use Ulams\Consultations\Dto\FilterConsultationTermsListDto;
use Ulams\Consultations\Models\ConsultationUserPivot;
use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

interface ConsultationUserRepositoryContract extends BaseRepositoryContract
{
    public function allQueryBuilder(
        array $search = [],
        ?FilterConsultationTermsListDto $filterConsultationTermsListDto = null
    ): Builder;
    public function updateModel(ConsultationUserPivot $consultationUserPivot, array $data): ConsultationUserPivot;
    public function getIncomingTerm(array $criteria = []): Collection;
    public function getBusyTerms(int $consultationId, ?string $date = null): Collection;
}
