<?php

namespace Ulams\Tenancy\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Ulams\Tenancy\Models\PublishedEntryHost;

/**
 * The academy's copy of its active entry hosts, in its own database (ADR 0195). Read on every request that needs to
 * decide whether an origin is trusted; replaced as a whole by the platform (`ulams:entry-points:publish`).
 */
class EntryHostStore
{
    private static ?bool $exists = null;

    /** @return list<string> the origins of the active entry hosts */
    public function origins(): array
    {
        if (!$this->available()) {
            return [];
        }

        return PublishedEntryHost::query()->orderBy('host')->pluck('origin')->all();
    }

    /**
     * Replaces the list. A transaction, so a request never sees half of it.
     *
     * @param list<array{host: string, origin: string, kind: string, key: string}> $hosts
     */
    public function replace(array $hosts): void
    {
        DB::transaction(function () use ($hosts) {
            PublishedEntryHost::query()->delete();
            foreach ($hosts as $row) {
                PublishedEntryHost::query()->create(['host' => $row['host'], 'origin' => $row['origin'], 'kind' => $row['kind'], 'key' => $row['key']]);
            }
        });
    }

    /** False until the migration ran: a deployment that is half way through an upgrade serves requests without a list. */
    private function available(): bool
    {
        return self::$exists ??= Schema::hasTable('tenant_entry_hosts');
    }

    /** For tests and long-lived workers after a migration. */
    public static function forgetSchemaCheck(): void
    {
        self::$exists = null;
    }
}
