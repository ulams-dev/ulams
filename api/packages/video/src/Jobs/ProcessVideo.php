<?php

namespace EscolaLms\Video\Jobs;

use EscolaLms\Courses\Models\Topic;
use EscolaLms\Video\Enums\VideoProcessState;
use EscolaLms\Video\Events\ProcessVideoFailed;
use EscolaLms\Video\Events\ProcessVideoFinished;
use EscolaLms\Video\Events\ProcessVideoStarted;
use EscolaLms\Video\Events\ProcessVideoState;
use EscolaLms\Video\Models\Video;
use Exception;
use FFMpeg\Format\Video\X264;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use ProtoneMedia\LaravelFFMpeg\Exporters\HLSExporter;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;

class ProcessVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;
    public $timeout = 18000;

    protected Video $video;
    protected Topic $topic;
    protected ?Authenticatable $user;
    protected string $disk;
    public function __construct(Video $video, ?Authenticatable $user, string $disk = null)
    {
        $this->video = $video;
        $this->topic = $video->topic;
        $this->user = $user;
        $this->disk = $disk ?? config('escolalms_video.disk');

        $this->onConnection(config('escolalms_video.queue_connection'));
        $this->onQueue(config('escolalms_video.queue'));
    }
    public function handle(): bool
    {
        $video = $this->video;
        $topic = $this->topic;
        $input = $video->value;
        $dir = $video->generateStoragePath();
        $hlsPath = $dir . '/hls.m3u8';

        $this->clearDirectory($dir, $video);

        $this->updateVideoState(['ffmpeg' => [
            'state' => VideoProcessState::STARTING
        ]]);
        $topic->save();

        ProcessVideoStarted::dispatch($this->user, $topic);

        $this->process($video, $input, $hlsPath);
        $this->makeFilesVisible($dir);

        $this->updateVideoState(['ffmpeg' => [
            'state' => VideoProcessState::FINISHED,
            'path' => $hlsPath,
        ]]);

        ProcessVideoFinished::dispatch($this->user, $this->topic);

        return true;
    }

    public function failed(Exception $exception)
    {
        $this->updateVideoState(['ffmpeg' => [
            'state' => VideoProcessState::ERROR,
            'message' => $exception->getMessage()
        ]]);

        Log::error($exception->getMessage());

        ProcessVideoFailed::dispatch($this->user, $this->topic);
    }

    private function getFFMpeg(string $input): HLSExporter
    {
        $bitRates = config('escolalms_video.bitrates');

        $ffmpeg = FFMpeg::fromDisk($this->disk)
            ->open($input)
            ->exportForHLS();

        if (empty($bitRates)) {
            return $ffmpeg;
        }

        foreach ($bitRates as $bitRate) {
            if (isset($bitRate['kiloBitrate'])) {
                $value = (new X264)->setKiloBitrate($bitRate['kiloBitrate']);

                if (isset($bitRate['scale'])) {
                    $ffmpeg->addFormat($value, function ($media) use ($bitRate) {
                        $scale = 'scale=' . $bitRate['scale'];
                        $media->addFilter($scale);
                    });
                } else {
                    $ffmpeg->addFormat($value);
                }
            }
        }

        return $ffmpeg;
    }

    private function process(Video $video, string $input, string $hlsPath): void {
        $this
            ->getFFMpeg($input)
            ->onProgress(function ($percentage) use ($video) {
                $this->updateVideoState(['ffmpeg' => [
                    'state' => VideoProcessState::CODING,
                    'percentage' => $percentage,
                    'date_time' => Carbon::now()
                ]]);
            })
            ->save($hlsPath);
    }

    private function updateVideoState($state): void
    {
        $topic = $this->topic;
        $data = is_array($topic->json) ? array_merge($topic->json, $state) : array_merge([], $state);

        if (!Arr::get($data, 'progress_notification') && Arr::get($data, 'ffmpeg.percentage') >= 35 && Arr::get($data, 'ffmpeg.percentage') <= 85) {
            ProcessVideoState::dispatch($this->user, $this->topic, Arr::get($data, 'ffmpeg.percentage'));
            $data['progress_notification'] = true;
        }

        $topic->json = $data;
        $topic->save();

        if ($data['ffmpeg']['state'] === VideoProcessState::FINISHED) {
            $topic->active = true;
            $topic->save();
        }
    }

    private function clearDirectory(string $dir, Video $video): bool
    {
        $storage = Storage::disk($this->disk);

        if ($storage->exists($dir)) {
            $files = $storage->allFiles($dir);
            foreach ($files as $file) {
                if (
                    ($video->poster !== $file && $video->value !== $file)
                    || in_array(File::extension($file), ['ts', 'm3u8'])
                ) {
                    $storage->delete($file);
                }
            }
        }

        return true;
    }

    private function makeFilesVisible(string $dir): void
    {
        foreach (Storage::files($dir) as $file) {
            Storage::disk($this->disk)->setVisibility($file, 'public');
        }
    }
}
