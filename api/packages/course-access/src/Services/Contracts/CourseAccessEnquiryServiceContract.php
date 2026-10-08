<?php

namespace EscolaLms\CourseAccess\Services\Contracts;

use EscolaLms\Core\Dtos\PaginationDto;
use EscolaLms\CourseAccess\Dtos\CourseAccessEnquiry\CreateCourseAccessEnquiryDto;
use EscolaLms\CourseAccess\Dtos\CriteriaDto;
use EscolaLms\CourseAccess\Exceptions\EnquiryAlreadyExistsException;
use EscolaLms\CourseAccess\Models\CourseAccessEnquiry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CourseAccessEnquiryServiceContract
{
    public function findByUser(CriteriaDto $criteriaDto, PaginationDto $paginationDto, int $userId): LengthAwarePaginator;

    public function findAll(CriteriaDto $criteriaDto, PaginationDto $paginationDto): LengthAwarePaginator;

    /**
     * @throws EnquiryAlreadyExistsException
     */
    public function create(CreateCourseAccessEnquiryDto $dto): CourseAccessEnquiry;

    public function delete(CourseAccessEnquiry $courseAccessEnquiry): void;

    public function approve(CourseAccessEnquiry $courseAccessEnquiry): void;
}
