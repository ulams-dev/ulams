<?php

namespace EscolaLms\TopicTypes\Commands;

use EscolaLms\TopicTypes\Services\Contracts\TopicTypeServiceContract;
use Illuminate\Console\Command;

class FixTopicTypeColumnName extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'escolalms:fix-type-column-name';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fixes Topicable DBs column name from old
    `EscolaLms\Courses\Models\TopicContent\XXX` to `EscolaLms\TopicTypes\Models\TopicContent\XXX`';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(TopicTypeServiceContract $service)
    {
        $topics = $service->fixTopicTypeColumnName();
        $this->info('The command was successful! Number of fixed Topics ' . $topics);
    }
}
