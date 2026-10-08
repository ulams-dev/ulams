<?php

namespace Ulams\Scorm\Commands;

use Illuminate\Console\Command;
use Ulams\Scorm\Services\Contracts\ScormServiceContract;

class CopyServiceWorkerJSCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'scorm:copy-serviceworker';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Copy serviceworker.js to s3 bucket';

    private ScormServiceContract $scormService;




    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(ScormServiceContract $scormService)
    {
        $scormService->uploadServiceWorkerToBucket();
    }
}
