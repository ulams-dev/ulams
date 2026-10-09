<?php

namespace Ulams\Auth\Services\Contracts;

use Ulams\Auth\Models\DeviceAuthorization;
use Ulams\Auth\Models\User;

interface DeviceAuthorizationServiceContract
{
    /**
     * Start a device authorization.
     *
     * @param  list<string>  $scopes
     * @return array{device_code:string,user_code:string,authorization:DeviceAuthorization}
     */
    public function start(string $clientName, array $scopes, ?string $agentName, ?string $ip, ?string $userAgent): array;

    /** Pending, unexpired request for a user code as typed by a person (any case, with or without the dash). */
    public function findPendingByUserCode(string $userCode): ?DeviceAuthorization;

    /**
     * Approve: mint the scoped token for `$approver` with approved ∩ requested scopes.
     *
     * @param  list<string>  $scopes
     *
     * @throws \InvalidArgumentException when nothing is left after the intersection, or the lifetime is invalid
     */
    public function approve(DeviceAuthorization $authorization, User $approver, array $scopes, int $expiresInDays): DeviceAuthorization;

    public function deny(DeviceAuthorization $authorization): DeviceAuthorization;

    /**
     * One poll of `POST /api/auth/device/token`.
     *
     * @return array{error:string}|array{access_token:string,token_type:string,expires_at:?string,scopes:list<string>,token_id:string}
     */
    public function poll(string $deviceCode): array;

    /** Revoke tokens that were approved but never collected, expire stale rows, delete old rows. @return array{revoked:int,deleted:int} */
    public function prune(): array;

    public static function normalizeUserCode(string $code): string;
}
