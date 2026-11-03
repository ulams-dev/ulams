<?php

namespace Ulams\Tenancy\Console;

use Illuminate\Console\Command;
use Throwable;
use Ulams\Auth\Models\User;
use Ulams\Auth\Services\Contracts\PersonalAccessTokenServiceContract;
use Ulams\Tenancy\Support\TenantContext;

/**
 * Runs inside a tenant (`--domain`), started by the platform API for a preview academy: mints a one-day token of the
 * tenant's administrator for course-as-code (`builder:read`, `builder:write`) and prints one JSON line. The kind is `cli`:
 * the preview is throwaway, and its own imports may be approved by the job that made them.
 */
class PreviewTokenCommand extends Command
{
    protected $signature = 'ulams:tenant:preview-token';

    protected $description = 'Mint a one-day administrator token for a preview academy (run with --domain, prints JSON)';

    public function handle(PersonalAccessTokenServiceContract $tokens): int
    {
        if (TenantContext::isPlatform()) {
            $this->error('Run this inside a tenant (--domain), not on the platform.');

            return self::FAILURE;
        }
        $admin = User::query()->where('email', (string) env('INITIAL_USER_EMAIL'))->first();
        if ($admin === null) {
            $this->error('The administrator of this tenant does not exist yet.');

            return self::FAILURE;
        }
        try {
            $issued = $tokens->issue($admin, 'preview academy', ['builder:read', 'builder:write'], 1, 'cli', null, 'cli');
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->line((string) json_encode(['token' => $issued->secret, 'expires_at' => $issued->token->expires_at?->toIso8601String(), 'scopes' => ['builder:read', 'builder:write']]));

        return self::SUCCESS;
    }
}
