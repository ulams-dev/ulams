<?php

namespace EscolaLms\TopicTypes\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as ConsoleCommand;

class FillTopicTypeMetadataCommand extends Command
{
    protected $signature = 'escolalms:fill-topic-types-metadata {model}';

    protected $description = 'Fill the metadata for the specific model';

    public function handle(): int
    {
        $modelClass = $this->argument('model');
        $model = 'EscolaLms\TopicTypes\Models\TopicContent\\' . ucfirst($modelClass);

        if (!class_exists($model)) {
            $this->error('Model ' . ucfirst($modelClass) . ' does not exist');
            return ConsoleCommand::FAILURE;
        }

        if (!method_exists($model, 'processMetadataInfo')) {
            $this->error('Model ' . ucfirst($modelClass) . ' does not have processMetadataInfo method implemented');
            return ConsoleCommand::FAILURE;
        }

        $model::all()->each(function($m) {
            $m->processMetadataInfo();
            $m->save();
        });

        return ConsoleCommand::SUCCESS;
    }
}
