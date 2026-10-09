<?php

namespace Ulams\LivingCourse\Console;

use Illuminate\Console\Command;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Models\Source;
use Ulams\LivingCourse\Models\Connection;
use Ulams\LivingCourse\Services\RevisionService;

/**
 * Records revision 1 (and the implicit upload connection) for sources of sessions that were built
 * before Phase 3. Idempotent; run once per tenant: `php artisan living-course:backfill --domain=<tenant>`.
 */
class BackfillCommand extends Command
{
    protected $signature = 'living-course:backfill {--session= : Only this builder session id}';

    protected $description = 'Record revision 1 for sources built before Living Course existed (idempotent)';

    public function handle(RevisionService $revisions): int
    {
        $sources = Source::query()->where('status', 'ready')
            ->when($this->option('session'), fn ($q, $id) => $q->where('session_id', $id))
            ->whereIn('session_id', Session::query()->select('id'))
            ->orderBy('created_at')->get();
        $created = 0;
        foreach ($sources as $source) {
            $existed = Connection::query()->where('source_id', $source->id)->whereNotNull('synced_revision_id')->exists();
            $revisions->ensureInitial($source);
            $created += $existed ? 0 : 1;
        }
        $this->info(sprintf('%d source(s) checked, %d new revision(s) recorded.', $sources->count(), $created));

        return self::SUCCESS;
    }
}
