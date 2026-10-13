<?php

namespace Ulams\Core\Support;

use Illuminate\Support\Facades\Log;
use ProtoneMedia\LaravelFFMpeg\Support\FFMpeg;
use Throwable;

/**
 * laravel-ffmpeg removes its temporary folders only at the end of a web request, so queue workers and
 * long commands must do it themselves. Cleanup is housekeeping: it runs in `finally` blocks and must never
 * fail the work it follows (the provider may not even be registered, as in package tests).
 */
final class FfmpegTemporaryFiles
{
    public static function clean(): void
    {
        try {
            FFMpeg::cleanupTemporaryFiles();
        } catch (Throwable $exception) {
            Log::warning('ffmpeg temporary files were not cleaned: ' . $exception->getMessage());
        }
    }
}
