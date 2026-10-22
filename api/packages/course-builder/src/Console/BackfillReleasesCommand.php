<?php

namespace Ulams\CourseBuilder\Console;

use Illuminate\Console\Command;
use Ulams\CourseBuilder\Models\Session;
use Ulams\CourseBuilder\Services\ReleaseRecorder;

/**
 * Gives every session that was applied before releases existed its first release (ADR 0192). It works on the
 * database it runs against (one tenant: run it per tenant) and can be repeated: a session that has a release is skipped.
 */
class BackfillReleasesCommand extends Command
{
    protected $signature = 'course-builder:backfill-releases {--dry-run : Count the sessions without writing}';

    protected $description = 'Record one release for every applied builder session that has none yet';

    public function handle(ReleaseRecorder $releases): int
    {
        $written = 0;
        $dry = (bool) $this->option('dry-run');
        Session::query()->whereNotNull('course_id')->whereNotNull('applied_version_id')->orderBy('id')->each(function (Session $session) use ($releases, $dry, &$written) {
            if ($dry) {
                $written += $releases->latest($session->id) === null ? 1 : 0;

                return;
            }
            $written += $releases->backfill($session) !== null ? 1 : 0;
        });
        $this->info(($dry ? 'Would record ' : 'Recorded ') . "{$written} release(s).");

        return self::SUCCESS;
    }
}
