<?php

namespace EscolaLms\Consultations\Repositories\Contracts;

use EscolaLms\Consultations\Dto\FilterConsultationTermsListDto;
use EscolaLms\Consultations\Models\ConsultationUserPivot;
use EscolaLms\Core\Repositories\Contracts\BaseRepositoryContract;
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
