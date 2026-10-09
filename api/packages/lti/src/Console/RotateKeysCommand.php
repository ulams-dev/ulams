<?php

namespace Ulams\Lti\Console;

use Illuminate\Console\Command;
use Ulams\Lti\Services\KeyService;
use Ulams\Lti\Services\NonceStore;

class RotateKeysCommand extends Command
{
    protected $signature = 'ulams:lti:rotate-keys {--init : only create the key set when missing (tenant provisioning)}';

    protected $description = 'Rotate the tenant LTI signing keys: next becomes active, a new next key is generated, old retired keys are removed';

    public function handle(KeyService $keys, NonceStore $nonces): int
    {
        if ($this->option('init')) {
            $keys->ensureKeys();
            $this->info('LTI key set ready.');

            return self::SUCCESS;
        }

        $result = $keys->rotate();
        $pruned = $nonces->pruneExpired();
        $this->info(sprintf(
            'Active key %s, next key %s, retired %s; %d old keys and %d expired nonces removed.',
            $result['activated'],
            $result['next'],
            $result['retired'] ?? '-',
            $result['deleted'],
            $pruned
        ));

        return self::SUCCESS;
    }
}
