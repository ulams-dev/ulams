<?php

namespace Ulams\Scorm\Commands;

use Illuminate\Console\Command;
use Ulams\Scorm\Services\Contracts\ScormContentServiceContract;

class PublishContentPlayerCommand extends Command
{
    protected $signature = 'ulams:scorm:publish-player';

    protected $description = 'Write the content-origin SCORM player (player.html, player.js, scorm-again) to scorm/_player on the SCORM disk';

    public function handle(ScormContentServiceContract $contentService): int
    {
        $contentService->publishPlayer(true);
        $this->info('Published scorm/_player on the "' . config('scorm.disk') . '" disk.');

        return self::SUCCESS;
    }
}
