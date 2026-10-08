<?php

namespace EscolaLms\ConsultationAccess\Repositories\Contracts;

use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiryProposedTerm;
use EscolaLms\Core\Repositories\Contracts\BaseRepositoryContract;

interface ConsultationAccessEnquiryProposedTermRepositoryContract extends BaseRepositoryContract
{
    public function findById(int $id): ConsultationAccessEnquiryProposedTerm;
    public function firstOrCreate(array $attributes = [], array $values = []): ConsultationAccessEnquiryProposedTerm;
}
