<?php

namespace Ulams\ConsultationAccess\Repositories\Contracts;

use Ulams\ConsultationAccess\Models\ConsultationAccessEnquiryProposedTerm;
use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;

interface ConsultationAccessEnquiryProposedTermRepositoryContract extends BaseRepositoryContract
{
    public function findById(int $id): ConsultationAccessEnquiryProposedTerm;
    public function firstOrCreate(array $attributes = [], array $values = []): ConsultationAccessEnquiryProposedTerm;
}
