<?php

namespace EscolaLms\Recommender\Console\Commands;

use EscolaLms\Recommender\Jobs\ProcessingMeetingFramesJob;
use EscolaLms\Recommender\Models\MeetRecording;
use Illuminate\Console\Command;

class StartProcessingMeetFramesJobCommand extends Command
{
    protected $signature = 'recommender:start-processing {id? : ID MeetRecording}';
    protected $description = 'start processing meeting Frames';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $id = $this->argument('id');;

        if ($id) {
            $meetRecordings = MeetRecording::query()->where('id', $id)->get();
        } else {
            $meetRecordings = MeetRecording::query()->get();
        }

        if ($meetRecordings->isEmpty()) {
            $this->info('No results found');
            return;
        }

        foreach ($meetRecordings as $meetRecording) {
            $this->info('ProcessingMeetingFrames: ' . $meetRecording->getKey());
            ProcessingMeetingFramesJob::dispatch($meetRecording);
        }

        $this->info('Queued ' . count($meetRecordings) . ' satisfaction jobs');
    }
}
