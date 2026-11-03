<?php

namespace Ulams\Auth\Console\Commands;

use Illuminate\Console\Command;
use Ulams\Auth\Services\LoginHandoffService;

class PruneLoginHandoffCommand extends Command
{
    protected $signature = 'ulams:auth:prune-login-handoff';

    protected $description = 'Delete login states and one-time login codes that expired more than a day ago';

    public function handle(LoginHandoffService $handoff): int
    {
        $this->info('Deleted ' . $handoff->prune() . ' expired login states and codes.');

        return self::SUCCESS;
    }
}
