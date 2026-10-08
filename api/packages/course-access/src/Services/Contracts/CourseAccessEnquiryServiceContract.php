<?php

namespace Ulams\CourseAccess\Services\Contracts;

use Ulams\Core\Dtos\PaginationDto;
use Ulams\CourseAccess\Dtos\CourseAccessEnquiry\CreateCourseAccessEnquiryDto;
use Ulams\CourseAccess\Dtos\CriteriaDto;
use Ulams\CourseAccess\Exceptions\EnquiryAlreadyExistsException;
use Ulams\CourseAccess\Models\CourseAccessEnquiry;
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
