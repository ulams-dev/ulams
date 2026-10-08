<?php

namespace Ulams\Images\Tests\Command;

use Ulams\Images\Console\ClearImagesCacheCommand;
use Ulams\Images\Enum\ConstantEnum;
use Ulams\Images\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ClearImageCacheCommandTest extends TestCase
{
    public function testClearCacheCommand(): void
    {
        Storage::fake();

        Storage::makeDirectory(ConstantEnum::CACHE_DIRECTORY);
        UploadedFile::fake()->image('dummy.jpg')->storeAs(ConstantEnum::CACHE_DIRECTORY, 'dummy.jpg');
        Storage::assertExists(ConstantEnum::CACHE_DIRECTORY . DIRECTORY_SEPARATOR . 'dummy.jpg');

        $this->artisan(ClearImagesCacheCommand::class)
            ->assertSuccessful();

        Storage::assertMissing(ConstantEnum::CACHE_DIRECTORY . DIRECTORY_SEPARATOR . 'dummy.jpg');
        Storage::assertMissing(ConstantEnum::CACHE_DIRECTORY);
    }
}
