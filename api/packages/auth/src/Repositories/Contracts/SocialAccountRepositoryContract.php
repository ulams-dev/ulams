<?php

namespace Ulams\Auth\Repositories\Contracts;

use Ulams\Auth\Models\SocialAccount;
use Ulams\Core\Repositories\Contracts\BaseRepositoryContract;

interface SocialAccountRepositoryContract extends BaseRepositoryContract
{
    public function findByProviderAndProviderId(string $provider, string $providerId): ?SocialAccount;
}
