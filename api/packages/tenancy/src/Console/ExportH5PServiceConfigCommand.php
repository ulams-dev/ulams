<?php

namespace Ulams\Tenancy\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\Contracts\DomainRegistryContract;
use Ulams\Tenancy\Services\H5PServiceConfigExporter;
use Ulams\Tenancy\Support\TenantContext;

class ExportH5PServiceConfigCommand extends Command
{
    protected $signature = 'ulams:h5p:export-config';

    protected $description = 'Write the least-privilege env files and Passport public keys the H5P service mounts in production (H5P_SERVICE_CONFIG_DIR)';

    public function handle(H5PServiceConfigExporter $exporter, DomainRegistryContract $domains): int
    {
        if (!TenantContext::isPlatform()) {
            $this->error('Run tenant commands on the platform, without --domain.');

            return self::FAILURE;
        }
        if (!$exporter->enabled()) {
            $this->warn('H5P_SERVICE_CONFIG_DIR is not set; nothing to export (development mounts the API directory).');

            return self::SUCCESS;
        }

        $failed = false;
        try {
            $exporter->exportPlatform();
            $this->line('  <info>exported</info> platform');
        } catch (Throwable $exception) {
            $failed = true;
            $this->error("  platform: {$exception->getMessage()}");
        }

        if (Schema::hasTable((new Tenant())->getTable())) {
            foreach (Tenant::query()->orderBy('slug')->get() as $tenant) {
                if (!$tenant->hasCompleted('env')) {
                    continue;
                }
                try {
                    $exporter->exportTenant($tenant->api_host, $domains->storagePath($tenant->api_host));
                    $this->line("  <info>exported</info> {$tenant->api_host}");
                } catch (Throwable $exception) {
                    $failed = true;
                    $this->error("  {$tenant->api_host}: {$exception->getMessage()}");
                }
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
