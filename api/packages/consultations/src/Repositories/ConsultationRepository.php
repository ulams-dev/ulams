<?php

namespace EscolaLms\Consultations\Repositories;

use EscolaLms\Consultations\Models\Consultation;
use EscolaLms\Consultations\Repositories\Contracts\ConsultationRepositoryContract;
use EscolaLms\Core\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

class ConsultationRepository extends BaseRepository implements ConsultationRepositoryContract
{
    protected $fieldSearchable = [];

    public function getFieldsSearchable(): array
    {
        return $this->fieldSearchable;
    }

    public function model(): string
    {
        return Consultation::class;
    }

    public function allQueryBuilder(array $search = [], array $criteria = []): Builder
    {
        $query = $this->allQuery($search);
        if (!empty($criteria)) {
            $query = $this->applyCriteria($query, $criteria);
        }
        return $query;
    }

    public function forCurrentUser(array $search = [], array $criteria = []): Builder
    {
        $q = $this->allQuery($search);
        $this->getBoughtConsultationsByQuery($q);
        $q->orderBy('consultation_user.created_at', 'desc');
        if (!empty($criteria)) {
            $q = $this->applyCriteria($q, $criteria);
        }
        return $q;
    }

    public function updateModel(Consultation $consultation, array $data): Consultation
    {
        $consultation->fill($data);
        $consultation->save();
        return $consultation;
    }

    public function getBoughtConsultationsByQuery(Builder $query): Builder
    {
        return $query
            ->select(
                'consultations.*',
                'consultation_user.id as consultation_user_id',
                'consultation_user_terms.executed_status',
                'consultation_user_terms.executed_at',
                'consultation_user.product_id',
                'consultation_user.created_at',
            )
            ->leftJoin('consultation_user', 'consultation_user.consultation_id', '=', 'consultations.id')
            ->leftJoin('consultation_user_terms', 'consultation_user_terms.consultation_user_id', '=', 'consultation_user.id')
            ->where(['consultation_user.user_id' => auth()->user()->getKey()]);
    }
}
