<?php

namespace Ulams\Consultations\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes the webcam frames that the removed meeting capture stored in the tenant's bucket:
 * `{consultation|webinar}/{id}/{term}/{user}/{timestamp}.jpg`. Only the numeric `{term}` folders
 * are removed; `{id}/images` (cover and logotype uploads) and everything else stays.
 * Runs once per tenant from `ulams:upgrade` (step meetings_purge_frames).
 */
class PurgeMeetingFrames extends Command
{
    private const ROOTS = ['consultation', 'webinar'];

    protected $signature = 'meetings:purge-frames {--dry-run : Only list what would be deleted}';

    protected $description = 'Delete stored consultation and webinar webcam frames (never the images folders)';

    public function handle(): int
    {
        $disk = Storage::disk();
        $dry = (bool) $this->option('dry-run');
        $folders = 0;
        $files = 0;

        foreach (self::ROOTS as $root) {
            foreach ($disk->directories($root) as $meeting) {
                if (!ctype_digit(basename($meeting))) {
                    continue;
                }
                foreach ($disk->directories($meeting) as $term) {
                    // `{id}/{term_timestamp}`; `images` and any other name is left alone
                    if (!ctype_digit(basename($term))) {
                        continue;
                    }
                    $count = count($disk->allFiles($term));
                    $folders++;
                    $files += $count;
                    $this->line(($dry ? 'would delete ' : 'delete ') . "{$term} ({$count} files)");
                    if (!$dry) {
                        $disk->deleteDirectory($term);
                    }
                }
            }
        }

        $this->info(($dry ? 'Dry run: ' : '') . "{$folders} term folder(s), {$files} file(s)" . ($dry ? ' would be deleted.' : ' deleted.'));

        return self::SUCCESS;
    }
}
