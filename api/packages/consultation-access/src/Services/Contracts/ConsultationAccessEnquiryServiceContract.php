<?php

namespace Ulams\ConsultationAccess\Services\Contracts;

use Ulams\ConsultationAccess\Dtos\ApproveConsultationAccessEnquiryDto;
use Ulams\ConsultationAccess\Dtos\ConsultationAccessEnquiryDto;
use Ulams\ConsultationAccess\Dtos\CriteriaDto;
use Ulams\ConsultationAccess\Dtos\PageDto;
use Ulams\ConsultationAccess\Dtos\UpdateConsultationAccessEnquiryDto;
use Ulams\ConsultationAccess\Exceptions\ConsultationAccessException;
use Ulams\ConsultationAccess\Models\ConsultationAccessEnquiry;
use Ulams\Core\Dtos\OrderDto;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ConsultationAccessEnquiryServiceContract
{
    public function findAll(CriteriaDto $criteriaDto, PageDto $paginationDto, int $userId, ?OrderDto $orderDto = null): LengthAwarePaginator;

    /**
     * @throws ConsultationAccessException
     */
    public function approveByProposedTerm(ApproveConsultationAccessEnquiryDto $dto): void;

    public function disapprove(int $id, ?string $message): void;

    public function findByUser(CriteriaDto $criteriaDto, PageDto $paginationDto, int $userId): LengthAwarePaginator;

    public function create(ConsultationAccessEnquiryDto $dto): ConsultationAccessEnquiry;

    public function delete(int $id): void;

    public function update(int $id, UpdateConsultationAccessEnquiryDto $dto): ConsultationAccessEnquiry;
}
