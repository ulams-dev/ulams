<?php

namespace EscolaLms\CourseAccess\Services;

use EscolaLms\Auth\Enums\SettingStatusEnum;
use EscolaLms\Auth\Models\User;
use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Dtos\PaginationDto;
use EscolaLms\Core\Repositories\Criteria\Primitives\EqualCriterion;
use EscolaLms\CourseAccess\Dtos\CourseAccessEnquiry\CreateCourseAccessEnquiryDto;
use EscolaLms\CourseAccess\Dtos\CriteriaDto;
use EscolaLms\CourseAccess\Enum\CourseAccessPermissionEnum;
use EscolaLms\CourseAccess\Enum\EnquiryStatusEnum;
use EscolaLms\CourseAccess\EscolaLmsCourseAccessServiceProvider;
use EscolaLms\CourseAccess\Events\CourseAccessEnquiryAdminCreatedEvent;
use EscolaLms\CourseAccess\Events\CourseAccessEnquiryStudentCreatedEvent;
use EscolaLms\CourseAccess\Exceptions\EnquiryAlreadyExistsException;
use EscolaLms\CourseAccess\Models\CourseAccessEnquiry;
use EscolaLms\CourseAccess\Repositories\Contracts\CourseAccessEnquiryRepositoryContract;
use EscolaLms\CourseAccess\Services\Contracts\CourseAccessEnquiryServiceContract;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Config;

class CourseAccessEnquiryService implements CourseAccessEnquiryServiceContract
{
    private CourseAccessEnquiryRepositoryContract $repository;
    private CourseAccessService $courseAccessService;

    public function __construct(
        CourseAccessEnquiryRepositoryContract $repository,
        CourseAccessService                   $courseAccessService
    )
    {
        $this->repository = $repository;
        $this->courseAccessService = $courseAccessService;
    }

    public function findByUser(CriteriaDto $criteriaDto, PaginationDto $paginationDto, int $userId): LengthAwarePaginator
    {
        $criteria = $criteriaDto->toArray();
        $criteria[] = new EqualCriterion('user_id', $userId);

        return $this->repository->findByCriteria($criteria, $paginationDto->getLimit());
    }

    public function findAll(CriteriaDto $criteriaDto, PaginationDto $paginationDto, ?OrderDto $orderDto = null, ?int $perPage = 20): LengthAwarePaginator
    {
        return $this->repository
            ->queryWithAppliedCriteria($criteriaDto->toArray())
            ->orderBy($orderDto?->getOrderBy() ?? 'id', $orderDto->getOrder() ?? 'ASC')
            ->paginate($perPage);
    }

    /**
     * @throws EnquiryAlreadyExistsException
     */
    public function create(CreateCourseAccessEnquiryDto $dto): CourseAccessEnquiry
    {
        if ($this->repository->findByCourseIdAndUserId($dto->getCourseId(), $dto->getUserId())) {
            throw new EnquiryAlreadyExistsException();
        }

        /** @var CourseAccessEnquiry $entity */
        $entity = $this->repository->create($dto->toArray());
        $this->dispatchEventToAdminsAboutCreatingCourseAccessEnquiry($entity);
        event(new CourseAccessEnquiryStudentCreatedEvent($entity->user, $entity));

        if (Config::get(EscolaLmsCourseAccessServiceProvider::CONFIG_KEY . '.auto_accept_access_request', SettingStatusEnum::DISABLED) === SettingStatusEnum::ENABLED) {
            $this->approve($entity);
        }

        return $entity;
    }

    public function delete(CourseAccessEnquiry $courseAccessEnquiry): void
    {
        $this->repository->remove($courseAccessEnquiry);
    }

    public function approve(CourseAccessEnquiry $courseAccessEnquiry): void
    {
        $this->courseAccessService->addAccessForUsers($courseAccessEnquiry->course, [$courseAccessEnquiry->user->getKey()]);

        $this->repository->update([
            'status' => EnquiryStatusEnum::APPROVED,
        ], $courseAccessEnquiry->getKey());
    }

    private function dispatchEventToAdminsAboutCreatingCourseAccessEnquiry(CourseAccessEnquiry $courseAccessEnquiry): void
    {
        User::permission(CourseAccessPermissionEnum::APPROVE_COURSE_ACCESS_ENQUIRY)
            ->get()
            ->each(fn(User $admin) => event(new CourseAccessEnquiryAdminCreatedEvent($admin, $courseAccessEnquiry)));
    }
}
