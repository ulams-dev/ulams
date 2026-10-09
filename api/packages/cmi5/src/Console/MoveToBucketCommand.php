<?php

namespace Ulams\Cmi5\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Ulams\Cmi5\Models\Cmi5;

/**
 * Copies cmi5 packages that were uploaded to another disk (the old default, `local`) to the
 * configured cmi5 disk, normally the tenant bucket, where the content origin serves them from.
 * Safe to run again: a file that already exists with the same size is skipped. The source stays.
 */
class MoveToBucketCommand extends Command
{
    protected $signature = 'cmi5:move-to-bucket {--from=local : Disk the packages were uploaded to}';

    protected $description = 'Copy cmi5 packages from another disk to the configured cmi5 disk (idempotent)';

    public function handle(): int
    {
        $fromName = (string) $this->option('from');
        $toName = (string) config('ulams_cmi5.disk');

        if ($fromName === $toName) {
            $this->info("The cmi5 disk is already [{$toName}]; nothing to copy.");

            return self::SUCCESS;
        }

        $from = Storage::disk($fromName);
        $to = Storage::disk($toName);
        $copied = 0;
        $skipped = 0;

        foreach (Cmi5::query()->pluck('id') as $id) {
            foreach ($from->allFiles('cmi5/' . $id) as $file) {
                if ($to->exists($file) && $to->size($file) === $from->size($file)) {
                    $skipped++;
                    continue;
                }

                $stream = $from->readStream($file);
                $to->writeStream($file, $stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
                $copied++;
            }
        }

        $this->info("Copied {$copied} file(s) from [{$fromName}] to [{$toName}], skipped {$skipped} that were already there.");

        return self::SUCCESS;
    }
}
