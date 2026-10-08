<?php

namespace EscolaLms\Video\Jobs;

use EscolaLms\Auth\Models\User;
use EscolaLms\Video\Enums\VideoProcessState;
use EscolaLms\Video\Events\ProcessVideoFailed;
use EscolaLms\Video\Models\Video;
use EscolaLms\Video\Repositories\Contracts\VideoRepositoryContract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class DetectStuckVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(VideoRepositoryContract $repository): void
    {
        $repository
            ->getByProcessDateBefore(Carbon::now()->subHours(6), VideoProcessState::CODING)
            ->each(fn(Video $video) => $this->process($video));
    }

    private function process(Video $video): void
    {
        $topic = $video->topic;
        $topic->json = [
            'ffmpeg' => [
                'state' => VideoProcessState::ERROR,
                'message' => 'An unknown error occurred during video processing'
            ]
        ];
        $topic->save();

        $video->topic->lesson->course->authors
            ?->each(fn(User $user) => ProcessVideoFailed::dispatch($user, $video->topic));
    }
}

