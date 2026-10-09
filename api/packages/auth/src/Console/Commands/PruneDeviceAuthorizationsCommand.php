<?php

namespace Ulams\Auth\Console\Commands;

use Illuminate\Console\Command;
use Ulams\Auth\Services\Contracts\DeviceAuthorizationServiceContract;

class PruneDeviceAuthorizationsCommand extends Command
{
    protected $signature = 'ulams:auth:prune-device-authorizations';

    protected $description = 'Revoke device-login tokens nobody collected and delete device requests expired for over a day';

    public function handle(DeviceAuthorizationServiceContract $devices): int
    {
        $result = $devices->prune();
        $this->info("Revoked {$result['revoked']} uncollected tokens, deleted {$result['deleted']} requests.");

        return self::SUCCESS;
    }
}
