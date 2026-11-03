<?php

namespace Ulams\Identity\Console;

use Illuminate\Console\Command;
use Ulams\Identity\Models\IdentitySession;
use Ulams\Identity\Services\Saml\SamlService;

/** Daily: drops replay records that can no longer matter and IdP sessions whose tokens expired long ago. */
class PruneIdentityCommand extends Command
{
    protected $signature = 'identity:prune';

    protected $description = 'Delete expired SAML replay records and old identity sessions';

    public function handle(SamlService $saml): int
    {
        $replays = $saml->pruneReplays();
        $sessions = IdentitySession::query()->where('created_at', '<', now()->subDays(60))->delete();
        $this->info("Deleted {$replays} replay record(s) and {$sessions} session(s).");

        return self::SUCCESS;
    }
}
