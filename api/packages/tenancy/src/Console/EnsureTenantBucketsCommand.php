<?php

namespace Ulams\Tenancy\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;
use Ulams\Tenancy\Models\Tenant;
use Ulams\Tenancy\Services\Contracts\BucketProvisionerContract;
use Ulams\Tenancy\Support\TenantContext;

class EnsureTenantBucketsCommand extends Command
{
    protected $signature = 'ulams:tenant:ensure-buckets';

    protected $description = 'Create the platform bucket and every tenant bucket when missing and re-apply their public-read policy (after moving to another object store)';

    public function handle(BucketProvisionerContract $buckets): int
    {
        if (!TenantContext::isPlatform()) {
            $this->error('Run tenant commands on the platform, without --domain.');

            return self::FAILURE;
        }

        $names = array_filter([(string) config('filesystems.disks.s3.bucket')]);
        if (Schema::hasTable((new Tenant())->getTable())) {
            $names = array_merge($names, Tenant::query()->orderBy('slug')->pluck('bucket')->all());
        }

        $failed = false;
        foreach (array_unique($names) as $bucket) {
            try {
                $buckets->ensure($bucket);
                $this->line("  <info>ready</info> {$bucket}");
            } catch (Throwable $exception) {
                $failed = true;
                $this->error("  {$bucket}: {$exception->getMessage()}");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
