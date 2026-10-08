<?php

namespace Ulams\Files\Tests\Helpers;

use Ulams\Files\Helpers\FileHelper;
use Ulams\Files\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class FileHelperTest extends TestCase
{
    public function testGetFilePath(): void
    {
        Storage::fake();
        $path = 'images';
        $file = 'test.jpg';
        $fullPath = $path . DIRECTORY_SEPARATOR . $file;
        Storage::makeDirectory($path);
        copy(__DIR__ . '/../mocks/test.jpg', Storage::path($fullPath));
        Storage::assertExists($fullPath);

        $pathToSavedFile = FileHelper::getFilePath(UploadedFile::fake()->image('new-file.jpg'));
        Storage::assertExists($pathToSavedFile);
        $this->assertEquals('new-file.jpg', basename($pathToSavedFile));

        $pathToSavedFile = FileHelper::getFilePath(UploadedFile::fake()->image('name with space.jpg'));
        Storage::assertExists($pathToSavedFile);
        $this->assertEquals('name-with-space.jpg', basename($pathToSavedFile));

        $this->assertEquals($fullPath, FileHelper::getFilePath($fullPath));
        $this->assertNull(FileHelper::getFilePath($file));
    }
}
