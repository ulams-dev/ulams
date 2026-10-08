<?php

namespace Ulams\Auth\Listeners;

use Ulams\Auth\Events\AccountDeleted;
use Ulams\Auth\Repositories\Contracts\SocialAccountRepositoryContract;

class RemoveUserSocialAccounts
{
    private SocialAccountRepositoryContract $socialAccountRepository;

    public function __construct(SocialAccountRepositoryContract $socialAccountRepository)
    {
        $this->socialAccountRepository = $socialAccountRepository;
    }

    public function handle(AccountDeleted $event): void
    {
        $this->socialAccountRepository->deleteWhere([
            'user_id' => $event->getUser()->getKey(),
        ]);
    }
}
