<?php

namespace EscolaLms\AssignWithoutAccount\Services;

use EscolaLms\AssignWithoutAccount\Dto\UserSubmissionDto;
use EscolaLms\AssignWithoutAccount\Dto\UserSubmissionSearchDto;
use EscolaLms\AssignWithoutAccount\Enums\UserSubmissionStatusEnum;
use EscolaLms\AssignWithoutAccount\Events\UnassignProduct;
use EscolaLms\AssignWithoutAccount\Events\UnassignProductable;
use EscolaLms\AssignWithoutAccount\Models\UserSubmission;
use EscolaLms\AssignWithoutAccount\Repositories\Contracts\UserSubmissionRepositoryContract;
use EscolaLms\AssignWithoutAccount\Services\Contracts\UserSubmissionServiceContract;
use EscolaLms\AssignWithoutAccount\Strategies\Contracts\AssignStrategy;
use EscolaLms\AssignWithoutAccount\Strategies\StrategyContext;
use EscolaLms\Cart\Contracts\Productable;
use EscolaLms\Cart\Models\Product;
use EscolaLms\Core\Dtos\OrderDto;
use EscolaLms\Core\Dtos\PaginationDto;
use EscolaLms\Core\Models\User;
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
