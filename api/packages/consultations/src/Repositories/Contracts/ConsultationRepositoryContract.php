<?php

namespace Ulams\Consultations\Repositories\Contracts;

use Ulams\Consultations\Models\Consultation;
use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;
use Illuminate\Database\Eloquent\Builder;

interface ConsultationRepositoryContract extends BaseRepositoryContract
{
    public function allQueryBuilder(array $search = [], array $criteria = []): Builder;
    public function updateModel(Consultation $consultation, array $data): Consultation;
    public function getBoughtConsultationsByQuery(Builder $query): Builder;
    public function forCurrentUser(array $search = [], array $criteria = []): Builder;
}
