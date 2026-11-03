<?php

namespace Ulams\PolicyLifecycle\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Ulams\PolicyLifecycle\Models\PolicyVariant;
use Ulams\PolicyLifecycle\Services\DepartmentAcademy;

/** Creates the academy of a department and moves its course there (ADR 0141, amendment). */
class ProvisionAcademyJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly string $variantId)
    {
    }

    /** Provisioning outlasts the default queues' retry_after: the builder's queue settings apply (ADR 0083). */
    public static function dispatchFor(string $variantId): void
    {
        $job = new self($variantId);
        if ($c = config('course_builder.queue_connection')) {
            $job->onConnection($c);
        }
        if ($q = config('course_builder.queue')) {
            $job->onQueue($q);
        }
        dispatch($job);
    }

    public function handle(DepartmentAcademy $academy): void
    {
        $variant = PolicyVariant::query()->find($this->variantId);
        if ($variant !== null) {
            $academy->run($variant);
        }
    }
}
