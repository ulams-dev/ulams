<?php

namespace EscolaLms\AssignWithoutAccount\Listeners;

use EscolaLms\AssignWithoutAccount\Enums\UserSubmissionStatusEnum;
use EscolaLms\AssignWithoutAccount\Repositories\Contracts\UserSubmissionRepositoryContract;
use EscolaLms\AssignWithoutAccount\Strategies\Contracts\AssignStrategy;
use EscolaLms\AssignWithoutAccount\Strategies\StrategyContext;
use EscolaLms\Auth\Events\AccountRegistered;
use EscolaLms\Cart\Models\User;
use EscolaLms\Core\Repositories\Criteria\Primitives\EqualCriterion;

class AccountRegisteredListener
{
    private UserSubmissionRepositoryContract $userSubmissionRepository;

    public function __construct(
        UserSubmissionRepositoryContract $userSubmissionRepository
    )
    {
        $this->userSubmissionRepository = $userSubmissionRepository;
    }

    public function handle(AccountRegistered $event)
    {
        $user = new User($event->user->toArray());
        $user->id = $event->user->getKey();

        $criteria = [
            new EqualCriterion('email', $user->email),
            new EqualCriterion('status', UserSubmissionStatusEnum::SENT)
        ];

        $results = $this->userSubmissionRepository->searchByCriteria($criteria);

        foreach ($results as $result) {
            $this->getStrategy($result->morphable_type)->assign($result->morphable_type, $result->morphable_id, $user);
            $result->update(['status' => UserSubmissionStatusEnum::ACCEPTED]);
        }
    }

    private function getStrategy(string $morphType): ?AssignStrategy
    {
        return (new StrategyContext($morphType))->getAssignStrategy();
    }
}
