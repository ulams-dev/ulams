<?php

namespace Ulams\Tenancy\Console;

use Illuminate\Console\Command;
use Ulams\Tenancy\Services\EntryHostStore;
use Ulams\Tenancy\Support\EntryHost;
use Ulams\Tenancy\Support\TenantContext;

/**
 * Replaces an academy's copy of its active entry hosts (ADR 0195). Run by the platform in a process of the academy
 * (`--domain=<api host>`); it is not an interface for people, and no API route calls it.
 */
class PublishEntryHostsCommand extends Command
{
    protected $signature = 'ulams:entry-points:publish {--payload= : base64 JSON list of {host, origin, kind, key}}';

    protected $description = 'Replace this academy\'s copy of its active entry hosts (called by the platform)';

    protected $hidden = true;

    public function handle(EntryHostStore $store): int
    {
        if (TenantContext::isPlatform()) {
            $this->error('Run this in an academy (--domain=<api host>), not on the platform.');

            return self::FAILURE;
        }

        $payload = json_decode((string) base64_decode((string) $this->option('payload'), true), true);
        if (!is_array($payload)) {
            $this->error('The payload is not a base64 JSON list.');

            return self::FAILURE;
        }

        $hosts = [];
        foreach ($payload as $row) {
            $host = is_array($row) && is_string($row['host'] ?? null) ? EntryHost::normalize($row['host']) : '';
            $origin = is_array($row) && is_string($row['origin'] ?? null) ? $row['origin'] : '';
            $parts = parse_url($origin);
            // the origin must be exactly this host over http or https: nothing else is ever trusted from a payload
            if (EntryHost::syntaxError($host) !== null || !is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
                || strtolower((string) ($parts['host'] ?? '')) !== $host || isset($parts['path']) || isset($parts['query']) || isset($parts['user'])) {
                $this->error('Refused an entry host that is not a plain origin.');

                return self::FAILURE;
            }
            $hosts[] = ['host' => $host, 'origin' => $origin, 'kind' => (string) ($row['kind'] ?? 'academy'), 'key' => (string) ($row['key'] ?? '')];
        }

        $store->replace($hosts);
        $this->line('Published ' . count($hosts) . ' entry host(s).');

        return self::SUCCESS;
    }
}
