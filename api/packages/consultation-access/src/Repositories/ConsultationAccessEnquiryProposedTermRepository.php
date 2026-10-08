<?php

namespace Ulams\ConsultationAccess\Repositories;

use Ulams\ConsultationAccess\Models\ConsultationAccessEnquiryProposedTerm;
use Ulams\ConsultationAccess\Repositories\Contracts\ConsultationAccessEnquiryProposedTermRepositoryContract;
use Ulams\Core\Repositories\BaseRepository;

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
