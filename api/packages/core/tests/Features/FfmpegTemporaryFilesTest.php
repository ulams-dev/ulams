<?php

namespace Ulams\Core\Tests\Features;

use Ulams\Core\Support\FfmpegTemporaryFiles;
use Ulams\Core\Tests\TestCase;

class FfmpegTemporaryFilesTest extends TestCase
{
    /** The ffmpeg provider is not registered in the package tests: cleanup must not turn that into a failure. */
    public function testCleanupDoesNotThrowWhenTheFfmpegProviderIsMissing(): void
    {
        $this->assertFalse($this->app->bound('laravel-ffmpeg'));

        FfmpegTemporaryFiles::clean();

        $this->assertTrue(true);
    }
}
