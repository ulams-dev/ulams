<?php

namespace Ulams\Auth\Services\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Ulams\Auth\Models\ApiTokenMeta;
use Ulams\Auth\Models\User;
use Ulams\Auth\Support\IssuedToken;

interface PersonalAccessTokenServiceContract
{
    /**
     * Mint a scoped personal access token for `$user`.
     *
     * @param  list<string>  $scopes   validated by TokenScopes::normalize
     * @param  list<string>|null  $callerScopes  scopes of the token making the call; the new token may not exceed them
     */
    public function issue(
        User $user,
        string $name,
        array $scopes,
        int $expiresInDays,
        string $kind = 'cli',
        ?string $agentName = null,
        string $createdVia = 'admin',
        ?int $rateLimitPerMinute = null,
        ?array $callerScopes = null,
    ): IssuedToken;

    /** @return Collection<int,ApiTokenMeta> the user's scoped tokens, newest first (token relation loaded) */
    public function listFor(User $user, bool $includeRevoked = false): Collection;

    public function paginateAll(?int $userId, ?string $kind, bool $includeRevoked, int $perPage): LengthAwarePaginator;

    public function find(string $tokenId): ?ApiTokenMeta;

    public function revoke(ApiTokenMeta $meta): void;
}
