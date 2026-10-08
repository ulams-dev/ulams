<?php

namespace Ulams\Files\Tests\Api;

use Ulams\Files\Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class FilesApiUploadTest extends TestCase
{
    public function testSingleFileUpload()
    {
        $target = '/';
        $file = UploadedFile::fake()->image('file.jpg');
        $response = $this->actingAs(auth()->user(), 'api')->post(
            '/api/admin/file/upload',
            [
                'file' => [$file],
                'target' => $target,
            ],
        );
        $response->assertStatus(200);

        $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
        $this->disk->assertExists($filename);
    }

    public function testSingleFileUploadInvalidContentType()
    {
        $target = '/';
        $file = UploadedFile::fake()->image('file.jpg');
        $response = $this->actingAs(auth()->user(), 'api')->post(
            '/api/admin/file/upload',
            [
                'file' => [$file],
                'target' => $target,
            ],
            ['Content-Type' => 'application/json'],
        );
        $response->assertStatus(302);
        $this->disk->assertMissing($file->getClientOriginalName());
    }

    public function testSingleFileUploadMissingTarget()
    {
        $file = UploadedFile::fake()->image('file.jpg');
        $response = $this->actingAs(auth()->user(), 'api')->post(
            '/api/admin/file/upload',
            [
                'file' => [$file],
            ],
        );
        $response->assertStatus(302);
    }

    public function testSingleFileUploadMissingFile()
    {
        $target = '/';
        $response = $this->actingAs(auth()->user(), 'api')->post(
            '/api/admin/file/upload',
            [
                'target' => $target,
            ],
        );
        $response->assertStatus(302);
    }

    public function testSingleDuplicateFileUpload()
    {
        $target = '/';
        $file = UploadedFile::fake()->image('duplicate.jpg');
        $response = $this->actingAs(auth()->user(), 'api')->post(
            '/api/admin/file/upload',
            [
                'file' => [$file],
                'target' => $target,
            ],
        );
        $response->assertStatus(200);

        $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();

        $this->disk->assertExists($filename);

        $file = UploadedFile::fake()->image('duplicate.jpg');
        $response = $this->actingAs(auth()->user(), 'api')->post(
            '/api/admin/file/upload',
            [
                'file' => [$file],
                'target' => $target,
            ],
        );
        $response->assertStatus(200);
        $this->disk->assertExists($filename);
    }

    /**
     * @todo change status code to invalid input
     */
    public function testSingleFileDirectoryTraversalUpload()
    {
        $target = '../../';
        $file = UploadedFile::fake()->image('file.jpg');
        $response = $this->actingAs(auth()->user(), 'api')->post(
            '/api/admin/file/upload',
            [
                'file' => [$file],
                'target' => $target,
            ],
        );
        $response->assertStatus(500);
        $this->disk->assertMissing($file->getClientOriginalName());
    }

    public static function excludedFileExtensionProvider(): array
    {
        return [
            ['html'],
            ['php'],
            ['css'],
            ['java'],
            ['h5p'],
            ['m3u8'],
            ['heic'],
        ];
    }

    public static function allowedFileExtensionProvider(): array
    {
        // Data providers run before the application boots (PHPUnit 10), so read the package config file.
        $config = require __DIR__ . '/../../src/config/files.php';

        return array_map(fn ($item) => [$item], explode(',', $config['mimes']));
    }

    /**
     * @dataProvider allowedFileExtensionProvider
     */
    public function testAllowedFileExtensions(string $ext)
    {
        $file = UploadedFile::fake()->create('file.' . $ext);
        $response = $this->actingAs(auth()->user(), 'api')->post(
            '/api/admin/file/upload',
            [
                'file' => [$file],
                'target' => '/',
            ],
        );

        $response->assertStatus(200);
    }

    /**
     * @dataProvider excludedFileExtensionProvider
     */
    public function testExcludedFileExtensions(string $ext)
    {
        $file = UploadedFile::fake()->create('file.' . $ext);
        $response = $this->actingAs(auth()->user(), 'api')->post(
            '/api/admin/file/upload',
            [
                'file' => [$file],
                'target' => '/',
            ],
        );

        $response->assertStatus(302);
    }
}
