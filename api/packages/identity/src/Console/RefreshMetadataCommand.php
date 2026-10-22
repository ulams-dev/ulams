<?php

namespace Ulams\Identity\Console;

use Illuminate\Console\Command;
use Ulams\Identity\Models\IdentityProvider;
use Ulams\Identity\Services\ProviderService;

/** Daily: fetches the metadata of every SAML provider that has a metadata URL (plan 4.3, question 5). */
class RefreshMetadataCommand extends Command
{
    protected $signature = 'identity:refresh-metadata {--provider= : Only this provider slug}';

    protected $description = 'Refresh the SAML identity provider metadata (new signing certificates are added; other changes wait for an administrator)';

    public function handle(ProviderService $providers): int
    {
        $query = IdentityProvider::query()->where('protocol', IdentityProvider::SAML)->where('enabled', true)->whereNotNull('saml');
        if ($this->option('provider')) {
            $query->where('slug', (string) $this->option('provider'));
        }

        $failed = 0;
        foreach ($query->orderBy('slug')->get() as $provider) {
            if ((string) (((array) $provider->saml)['metadata_url'] ?? '') === '') {
                continue;
            }
            $result = $providers->refreshMetadata($provider);
            $this->line($provider->slug . ': ' . $result['status'] . ($result['reason'] ? ' (' . $result['reason'] . ')' : '')
                . ($result['added'] !== [] ? ', ' . count($result['added']) . ' certificate(s) added' : '')
                . ($result['removed'] !== [] ? ', ' . count($result['removed']) . ' removed' : ''));
            if (in_array($result['status'], ['failed', 'attention'], true)) {
                $failed++;
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
