<?php

namespace EscolaLms\TopicTypes\Commands;

use EscolaLms\TopicTypes\Services\Contracts\TopicTypeServiceContract;
use Illuminate\Console\Command;

class FixAssetPathsCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'escolalms:fix-topic-types-paths';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fixes all topic types paths so they are always in courses/{id} folder';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(TopicTypeServiceContract $service)
    {
        $files = $service->fixAssetPaths();

        // I hate imperative programming, but I'm so lazy ....
        foreach ($files as $file) {
            $this->info('moving file from '.$file[0].' to '.$file[1]);
        }
        $this->info('The command was successful! Number of fixed Topics '.count($files));
    }
}
