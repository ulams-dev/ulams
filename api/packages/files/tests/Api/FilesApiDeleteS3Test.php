<?php

namespace Ulams\Files\Tests\Api;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Ulams\Core\Tests\Mocks\FakeS3;
use Ulams\Files\Tests\TestCase;

/**
 * Deleting through the file manager on an S3 disk: a "directory" there is a key prefix, so it is found
 * through the disk and not by looking at a path on this machine (ADR 0041).
 */
class FilesApiDeleteS3Test extends TestCase
{
    private string $url = '/api/admin/file/delete';
    private FakeS3 $s3;

    protected function setUp(): void
    {
        parent::setUp();
        $this->s3 = FakeS3::install();
    }

    public function testDeleteAFile(): void
    {
        Storage::put('directory/test.png', 'png');

        $this->deleteWithQuery($this->url, ['url' => 'directory/test.png'], [], true)->assertOk();

        $this->assertArrayNotHasKey('directory/test.png', $this->s3->objects);
    }

    public function testDeleteADirectoryWithItsFiles(): void
    {
        Storage::put('directory/a.png', 'a');
        Storage::put('directory/nested/b.png', 'b');
        Storage::put('other/c.png', 'c');

        $this->deleteWithQuery($this->url, ['url' => 'directory'], [], true)->assertOk();

        $this->assertSame(['other/c.png'], array_keys($this->s3->objects));
    }

    public function testDeleteNonExistentFile(): void
    {
        $this->deleteWithQuery($this->url, ['url' => 'missing.txt'], [], true)->assertStatus(400);
    }
}
