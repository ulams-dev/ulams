<?php

namespace Ulams\AssignWithoutAccount\Services;

use Ulams\AssignWithoutAccount\Dto\UserSubmissionDto;
use Ulams\AssignWithoutAccount\Dto\UserSubmissionSearchDto;
use Ulams\AssignWithoutAccount\Enums\UserSubmissionStatusEnum;
use Ulams\AssignWithoutAccount\Events\UnassignProduct;
use Ulams\AssignWithoutAccount\Events\UnassignProductable;
use Ulams\AssignWithoutAccount\Models\UserSubmission;
use Ulams\AssignWithoutAccount\Repositories\Contracts\UserSubmissionRepositoryContract;
use Ulams\AssignWithoutAccount\Services\Contracts\UserSubmissionServiceContract;
use Ulams\AssignWithoutAccount\Strategies\Contracts\AssignStrategy;
use Ulams\AssignWithoutAccount\Strategies\StrategyContext;
use Ulams\Cart\Contracts\Productable;
use Ulams\Cart\Models\Product;
use Ulams\Core\Dtos\OrderDto;
use Ulams\Core\Dtos\PaginationDto;
use Ulams\Core\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserSubmissionService implements UserSubmissionServiceContract
{
    private UserSubmissionRepositoryContract $userSubmissionRepository;

    public function __construct(
        UserSubmissionRepositoryContract $userSubmissionRepository
    ) {
        $this->userSubmissionRepository = $userSubmissionRepository;
    }

    public function create(UserSubmissionDto $dto): UserSubmission
    {
        $strategy = $this->getStrategy($dto->getMorphableType());
        $model = $strategy->getModelInstance($dto->getMorphableType(), $dto->getMorphableId());

        $dto->setStatus(UserSubmissionStatusEnum::SENT);
        $submission =  $this->userSubmissionRepository->create($dto->toArray());

        $strategy->dispatch($dto->getEmail(), $model);

        return $submission;
    }

    public function update(UserSubmissionDto $dto, int $id): UserSubmission
    {
        return $this->userSubmissionRepository->update($dto->toArray(), $id);
    }

    public function delete(int $id): bool
    {
        /** @var UserSubmission $submission */
        $submission = $this->userSubmissionRepository->find($id);
        $this->dispatchUnassignEvent($submission);

        return $this->userSubmissionRepository->delete($id);
    }

    public function searchAndPaginate(UserSubmissionSearchDto $searchDto, ?PaginationDto $paginationDto, ?OrderDto $orderDto = null): LengthAwarePaginator
    {
        return $this->userSubmissionRepository->searchAndPaginateByCriteria($searchDto, $paginationDto, $orderDto);
    }

    private function getStrategy(string $morphType): ?AssignStrategy
    {
        return (new StrategyContext($morphType))->getAssignStrategy();
    }

    private function dispatchUnassignEvent(UserSubmission $userSubmission): void
    {
        $user = new User();
        $user->email = $userSubmission->email;

        if (is_a($userSubmission->morphable_type, Productable::class, true)) {
            UnassignProductable::dispatch($user, $userSubmission->morphable);
        } elseif (is_a($userSubmission->morphable_type, Product::class, true)) {
            UnassignProduct::dispatch($user, $userSubmission->morphable);
        }
    }
}
