<?php

namespace EscolaLms\ConsultationAccess\Repositories;

use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiryProposedTerm;
use EscolaLms\ConsultationAccess\Repositories\Contracts\ConsultationAccessEnquiryProposedTermRepositoryContract;
use EscolaLms\Core\Repositories\BaseRepository;

class ConsultationAccessEnquiryProposedTermRepository extends BaseRepository implements ConsultationAccessEnquiryProposedTermRepositoryContract
{
    public function model(): string
    {
        return ConsultationAccessEnquiryProposedTerm::class;
    }

    public function getFieldsSearchable(): array
    {
        return [];
    }

    public function findById(int $id): ConsultationAccessEnquiryProposedTerm
    {
        /** @var ConsultationAccessEnquiryProposedTerm */
        return $this->model->newQuery()->findOrFail($id);
    }

    public function firstOrCreate(array $attributes = [], array $values = []): ConsultationAccessEnquiryProposedTerm
    {
        /** @var ConsultationAccessEnquiryProposedTerm */
        return $this->model->newQuery()->firstOrCreate($attributes, $values);
    }
}
