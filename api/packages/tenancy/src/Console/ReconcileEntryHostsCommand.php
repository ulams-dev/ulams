<?php

namespace Ulams\Tenancy\Console;

use Illuminate\Console\Command;
use Ulams\Tenancy\Services\EntryHostPublisher;
use Ulams\Tenancy\Support\TenantContext;

/** Publishes the entry hosts to every academy whose copy is out of date (a failed publication, a restored database). */
class ReconcileEntryHostsCommand extends Command
{
    protected $signature = 'ulams:entry-points:reconcile {--tenant=* : Limit to these slugs}';

    protected $description = 'Bring every academy\'s copy of its active entry hosts up to date with the platform';

    public function handle(EntryHostPublisher $publisher): int
    {
        if (!TenantContext::isPlatform()) {
            $this->error('Run this on the platform, without --domain.');

            return self::FAILURE;
        }

        $result = $publisher->reconcile(array_filter((array) $this->option('tenant')) ?: null);
        foreach ($result['published'] as $slug) {
            $this->line("  <info>published</info> {$slug}");
        }
        foreach ($result['failed'] as $slug => $message) {
            $this->error("  {$slug}: {$message}");
        }

        return $result['failed'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
