<?php

namespace EscolaLms\ConsultationAccess\Services\Contracts;

use EscolaLms\ConsultationAccess\Dtos\ApproveConsultationAccessEnquiryDto;
use EscolaLms\ConsultationAccess\Dtos\ConsultationAccessEnquiryDto;
use EscolaLms\ConsultationAccess\Dtos\CriteriaDto;
use EscolaLms\ConsultationAccess\Dtos\PageDto;
use EscolaLms\ConsultationAccess\Dtos\UpdateConsultationAccessEnquiryDto;
use EscolaLms\ConsultationAccess\Exceptions\ConsultationAccessException;
use EscolaLms\ConsultationAccess\Models\ConsultationAccessEnquiry;
use EscolaLms\Core\Dtos\OrderDto;
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
